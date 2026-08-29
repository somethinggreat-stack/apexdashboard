<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the quick-log "canonical step per week" contract (the bug where a
 * 20-day client's week-2 quick-log sent a step that only exists in week 1, so
 * store() rejected it as "step_types.0 invalid"), plus the internal per-owner
 * Tasks View that — unlike the owner-facing one — shows the VA's name.
 */
class QuickLogAndAdminTasksTest extends TestCase
{
    use RefreshDatabase;

    private function super(): Admin
    {
        $a = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $a->role = 'super';
        $a->save();
        return $a;
    }

    private function bo(Admin $super, int $cycle): Client
    {
        return Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => "bo{$cycle}@test.com",
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'round_cycle_days' => $cycle, 'intake_enabled' => true,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function eu(Client $bo): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Worked', 'last_name' => 'Client',
            'suffix' => 'None', 'email' => 'w@test.com', 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => '2026-06-01', 'intake_status' => null, 'rounds' => ['1st Round', '2nd Round'],
        ]);
    }

    /**
     * The quick-log badge, for a past-due closeout, submits both closeout steps
     * as step_types[] on the last week — the store must accept and create both
     * (30-day Week 4). This is the path the incomplete badge now uses.
     */
    /** Fully log every step of a round's weeks 1..through (bypasses the endpoint). */
    private function completeWeeks(Admin $super, EndUser $eu, int $cycle, int $round, int $through): void
    {
        foreach (ProcessStep::stepTypesByWeek($cycle) as $w => $steps) {
            if ($w > $through) {
                break;
            }
            foreach (array_keys($steps) as $type) {
                ProcessStep::firstOrCreate(
                    ['end_user_id' => $eu->id, 'round' => $round, 'week' => $w, 'step_type' => $type],
                    ['step_date' => '2026-08-01', 'created_by_admin_id' => $super->id]
                );
            }
        }
    }

    public function test_closeout_steps_log_via_step_types_array(): void
    {
        $super = $this->super();
        $bo    = $this->bo($super, 30);
        $eu    = $this->eu($bo);

        // Weeks 1–3 must be complete before the Week 4 closeout can be logged.
        $this->completeWeeks($super, $eu, 30, 1, 3);

        $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post(route('admin.process-steps.store'), [
                'end_user_id' => $eu->id,
                'round'       => 1,
                'week'        => 4,
                'step_types'  => ['pull_latest_report', 'record_deletions'],
                'step_date'   => '2026-08-26',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('process_steps', ['end_user_id' => $eu->id, 'round' => 1, 'week' => 4, 'step_type' => 'pull_latest_report']);
        $this->assertDatabaseHas('process_steps', ['end_user_id' => $eu->id, 'round' => 1, 'week' => 4, 'step_type' => 'record_deletions']);
    }

    /**
     * For every cycle and week, the canonical (first) step the quick-log modal
     * pre-fills must be a step store() accepts for that week. This is exactly
     * what broke for 20-day week 2.
     */
    public function test_canonical_first_step_per_week_is_accepted_by_store(): void
    {
        $super = $this->super();

        foreach ([30, 20] as $cycle) {
            $bo = $this->bo($super, $cycle);
            $eu = $this->eu($bo);

            foreach (ProcessStep::stepTypesByWeek($cycle) as $week => $steps) {
                $canonical = array_key_first($steps);   // what the modal sends

                // Sequential lock: earlier weeks must be complete first.
                $this->completeWeeks($super, $eu, $cycle, 1, $week - 1);

                $resp = $this->actingAs($super, 'admin')
                    ->withSession(['selected_client_id' => $bo->id])
                    ->post(route('admin.process-steps.store'), [
                        'end_user_id' => $eu->id,
                        'round'       => 1,
                        'week'        => $week,
                        'step_type'   => $canonical,
                        'step_date'   => '2026-08-26',
                    ]);

                $resp->assertSessionHasNoErrors();
                $this->assertDatabaseHas('process_steps', [
                    'end_user_id' => $eu->id, 'round' => 1, 'week' => $week, 'step_type' => $canonical,
                ]);
            }
        }
    }

    public function test_admin_tasks_view_shows_va_name(): void
    {
        $super = $this->super();
        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'Val The VA']);
        $va->role = 'va';
        $va->save();

        $bo = $this->bo($super, 30);
        $eu = $this->eu($bo);

        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 2, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => now()->toDateString(),
            'created_by_admin_id' => $va->id,
        ]);

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->get(route('admin.tasks'));

        $resp->assertStatus(200);
        $resp->assertSee('Worked Client');
        $resp->assertSee('2nd Round started');
        $resp->assertSee('Val The VA');   // internal view DOES show the VA
    }
}
