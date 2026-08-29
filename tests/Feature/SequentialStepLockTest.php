<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Steps must be logged strictly in order. No one — VA or super admin — can work
 * a round/week until every earlier week (and every earlier round in full,
 * closeout steps included) is complete.
 */
class SequentialStepLockTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function bo(int $cycle): Client
    {
        return Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'BO', 'email' => "bo{$cycle}@t.com",
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => $cycle,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function eu(Client $bo): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => 'X', 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => null, 'rounds' => [],
        ]);
    }

    private function log(Client $bo, EndUser $eu, int $round, int $week, array $types)
    {
        return $this->actingAs($this->super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/process-steps', [
                'end_user_id' => $eu->id, 'round' => $round, 'week' => $week,
                'step_types' => $types, 'step_date' => '2026-06-10',
            ]);
    }

    private function completeWeek(EndUser $eu, int $cycle, int $round, int $week): void
    {
        foreach (array_keys(ProcessStep::stepTypesByWeek($cycle)[$week]) as $type) {
            ProcessStep::firstOrCreate(
                ['end_user_id' => $eu->id, 'round' => $round, 'week' => $week, 'step_type' => $type],
                ['step_date' => '2026-06-01', 'created_by_admin_id' => $this->super->id]
            );
        }
    }

    public function test_cannot_skip_a_week(): void
    {
        $bo = $this->bo(30);
        $eu = $this->eu($bo);

        // Only Week 1 (canonical step) logged — Week 3 is blocked.
        $this->completeWeek($eu, 30, 1, 1);
        $this->log($bo, $eu, 1, 3, ['aggressive_bureau_followup'])
            ->assertSessionHasErrors('step_types');

        $this->assertDatabaseMissing('process_steps', [
            'end_user_id' => $eu->id, 'round' => 1, 'week' => 3,
        ]);
    }

    public function test_cannot_start_next_round_until_closeout_done(): void
    {
        $bo = $this->bo(20);   // closeout lives in Week 3
        $eu = $this->eu($bo);

        // Complete weeks 1 and 2, and only the aggressive step of week 3 —
        // Pull Latest Report + Record Deletions still missing.
        $this->completeWeek($eu, 20, 1, 1);
        $this->completeWeek($eu, 20, 1, 2);
        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 1, 'week' => 3,
            'step_type' => 'aggressive_bureau_followup', 'step_date' => '2026-06-05',
            'created_by_admin_id' => $this->super->id,
        ]);

        // Round 2 is blocked — round 1 isn't fully closed out.
        $this->log($bo, $eu, 2, 1, ['ex_tu_eq_letters_generated'])
            ->assertSessionHasErrors('step_types');
        $this->assertDatabaseMissing('process_steps', ['end_user_id' => $eu->id, 'round' => 2]);

        // Log the two closeout steps → round 1 complete → round 2 now allowed.
        $this->log($bo, $eu, 1, 3, ['pull_latest_report', 'record_deletions'])
            ->assertSessionHasNoErrors();
        $this->log($bo, $eu, 2, 1, ['ex_tu_eq_letters_generated'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('process_steps', [
            'end_user_id' => $eu->id, 'round' => 2, 'week' => 1, 'step_type' => 'ex_tu_eq_letters_generated',
        ]);
    }

    public function test_20day_closeout_in_week3_advances_to_next_round(): void
    {
        $bo = $this->bo(20);   // closeout lives in Week 3 (the last week)
        $eu = $this->eu($bo);
        $this->completeWeek($eu, 20, 1, 1);
        $this->completeWeek($eu, 20, 1, 2);

        // Finish Round 1 — aggressive + both closeout steps in Week 3.
        $this->log($bo, $eu, 1, 3, ['aggressive_bureau_followup', 'pull_latest_report', 'record_deletions'])
            ->assertSessionHasNoErrors();

        // Logging record_deletions in the cycle's LAST week auto-appends the next
        // round (this used to only fire for 30-day clients / Week 4).
        $this->assertContains('2nd Round', $eu->fresh()->rounds ?? []);
    }

    public function test_next_workable_points_at_the_frontier(): void
    {
        $bo = $this->bo(30);
        $eu = $this->eu($bo);
        $reload = fn () => EndUser::with('processSteps')->find($eu->id);

        // Fresh client → the frontier is Round 1, Week 1.
        $this->assertSame(['round' => 1, 'week' => 1], $reload()->nextWorkable());

        // Finish Week 1 → the frontier moves to Week 2.
        $this->completeWeek($eu, 30, 1, 1);
        $this->assertSame(['round' => 1, 'week' => 2], $reload()->nextWorkable());
    }

    public function test_in_order_logging_is_allowed(): void
    {
        $bo = $this->bo(30);
        $eu = $this->eu($bo);

        // Week 1 in full, then week 2 — allowed.
        $this->log($bo, $eu, 1, 1, array_keys(ProcessStep::stepTypesByWeek(30)[1]))
            ->assertSessionHasNoErrors();
        $this->log($bo, $eu, 1, 2, ['tu_ex_call_followups'])
            ->assertSessionHasNoErrors();
    }
}
