<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The super-admin "Mark All Incomplete Complete" button logs the missing WEEKLY
 * steps for every flagged client, but NEVER the closeout steps (Pull Latest
 * Report / Record Deletions), and is denied to non-super admins.
 */
class ClearIncompleteButtonTest extends TestCase
{
    use RefreshDatabase;

    private array $world;

    protected function setUp(): void
    {
        parent::setUp();
        $super = new Admin(['email' => 'super@t.com', 'password' => 'x', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
        $this->world = compact('super', 'bo');
    }

    private function eu(string $start, array $steps = []): EndUser
    {
        ['bo' => $bo, 'super' => $super] = $this->world;
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => uniqid(), 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => $start,
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
        foreach ($steps as [$w, $type]) {
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => 1, 'week' => $w, 'step_type' => $type,
                'step_date' => $start, 'created_by_admin_id' => $super->id,
            ]);
        }
        return $eu;
    }

    private function withCounts(int $id): EndUser
    {
        return EndUser::withCount([
            'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
            'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
            'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
            'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
        ])->find($id);
    }

    private function hitClear()
    {
        return $this->actingAs($this->world['super'], 'admin')
            ->withSession(['selected_client_id' => $this->world['bo']->id])
            ->post(route('admin.end-users.clear-incomplete'));
    }

    public function test_button_clears_a_missing_weekly_step(): void
    {
        // Day 10, only Week 1 logged → Week 2 (due day 8) is missing.
        $eu = $this->eu(now()->subDays(10)->toDateString(), [[1, 'ex_tu_eq_letters_generated']]);
        $this->assertSame(2, $this->withCounts($eu->id)->missing_week);

        $this->hitClear()->assertRedirect();

        // Week 2 now has a step → no longer incomplete.
        $this->assertGreaterThan(0, ProcessStep::where('end_user_id', $eu->id)->where('week', 2)->count());
        $this->assertFalse($this->withCounts($eu->id)->is_incomplete);
    }

    public function test_button_never_logs_the_closeout_steps(): void
    {
        // Past due, every regular week logged, only closeout missing → closeout_due.
        $eu = $this->eu(now()->subDays(35)->toDateString(), [
            [1, 'ex_tu_eq_letters_generated'],
            [2, 'tu_ex_call_followups'],
            [3, 'aggressive_bureau_followup'],
        ]);
        $this->assertTrue($eu->fresh()->closeout_due);

        $this->hitClear()->assertRedirect();

        // The button logged NOTHING for closeout — those two never get auto-logged.
        $this->assertSame(0, ProcessStep::where('end_user_id', $eu->id)
            ->whereIn('step_type', EndUser::CLOSEOUT_STEPS)->count());
        // Still incomplete (closeout must be done manually).
        $this->assertTrue($eu->fresh()->closeout_due);
    }

    public function test_non_super_is_forbidden(): void
    {
        $va = new Admin(['email' => 'va@t.com', 'password' => 'x', 'full_name' => 'Va']);
        $va->role = 'va';
        $va->parent_admin_id = $this->world['super']->id;
        $va->save();

        $this->actingAs($va, 'admin')
            ->withSession(['selected_client_id' => $this->world['bo']->id])
            ->post(route('admin.end-users.clear-incomplete'))
            ->assertForbidden();
    }

    public function test_universal_button_clears_across_all_owners(): void
    {
        ['super' => $super] = $this->world;

        // Flagged client under BO 1 (day 10, only Week 1 → Week 2 missing).
        $eu1 = $this->eu(now()->subDays(10)->toDateString(), [[1, 'ex_tu_eq_letters_generated']]);

        // A SECOND owner with its own flagged client.
        $bo2 = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO2', 'email' => 'bo2@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
        $eu2 = EndUser::create([
            'client_id' => $bo2->id, 'first_name' => 'Z', 'last_name' => 'Q', 'suffix' => 'None',
            'email' => 'z@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(10)->toDateString(),
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
        ProcessStep::create([
            'end_user_id' => $eu2->id, 'round' => 1, 'week' => 1, 'step_type' => 'ex_tu_eq_letters_generated',
            'step_date' => now()->subDays(10)->toDateString(), 'created_by_admin_id' => $super->id,
        ]);

        $this->actingAs($super, 'admin')
            ->post(route('admin.end-users.clear-incomplete-all'))
            ->assertRedirect();

        // Both owners' clients got their missing Week 2 logged, and no closeout anywhere.
        $this->assertGreaterThan(0, ProcessStep::where('end_user_id', $eu1->id)->where('week', 2)->count());
        $this->assertGreaterThan(0, ProcessStep::where('end_user_id', $eu2->id)->where('week', 2)->count());
        $this->assertSame(0, ProcessStep::whereIn('step_type', EndUser::CLOSEOUT_STEPS)->count());
    }

    public function test_universal_button_is_super_only(): void
    {
        $va = new Admin(['email' => 'va2@t.com', 'password' => 'x', 'full_name' => 'Va']);
        $va->role = 'va';
        $va->parent_admin_id = $this->world['super']->id;
        $va->save();

        $this->actingAs($va, 'admin')
            ->post(route('admin.end-users.clear-incomplete-all'))
            ->assertForbidden();
    }
}
