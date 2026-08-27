<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Support\WorkDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shared "work day" (shift) that every day-based feature uses. Verifies the
 * 4 PM → 10 AM PKT shift maps to one date labeled by the night it started, and
 * that EOD and Daily Task — the two that used to disagree (0 vs 3) — now count
 * the exact same shift.
 */
class WorkDayShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_maps_to_the_night_it_started(): void
    {
        // 4 PM Aug 26 PKT (= 11:00 UTC Aug 26) → work-day "2026-08-26".
        $evening = Carbon::parse('2026-08-26 16:00', WorkDay::TZ);
        $this->assertSame('2026-08-26', WorkDay::dateFor($evening));

        // 9 AM Aug 27 PKT (= 04:00 UTC Aug 27), still the same shift → "2026-08-26".
        $nextMorning = Carbon::parse('2026-08-27 09:00', WorkDay::TZ);
        $this->assertSame('2026-08-26', WorkDay::dateFor($nextMorning));

        // 3 PM Aug 27 PKT (off-hours, before the 4 PM rollover) is still the
        // just-ended shift → "2026-08-26".
        $offHours = Carbon::parse('2026-08-27 15:00', WorkDay::TZ);
        $this->assertSame('2026-08-26', WorkDay::dateFor($offHours));

        // 4 PM Aug 27 PKT — the new shift begins → "2026-08-27".
        $newShift = Carbon::parse('2026-08-27 16:00', WorkDay::TZ);
        $this->assertSame('2026-08-27', WorkDay::dateFor($newShift));

        // Bounds of Aug 26 work-day = 4 PM PKT Aug 26 → 4 PM PKT Aug 27 (UTC 11:00→11:00).
        [$start, $end] = WorkDay::bounds('2026-08-26');
        $this->assertSame('2026-08-26 11:00:00', $start->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-27 11:00:00', $end->utc()->format('Y-m-d H:i:s'));

        // The whole shift sits inside those bounds.
        $this->assertTrue($evening->utc()->gte($start) && $evening->utc()->lt($end));
        $this->assertTrue($nextMorning->utc()->gte($start) && $nextMorning->utc()->lt($end));
    }

    public function test_eod_and_daily_task_count_the_same_shift(): void
    {
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        // Results-tracking owner (so EOD includes it), like Clinecea.
        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Clinecea Phillips', 'email' => 'c@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active', 'results_tracking' => true,
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        // Three clients, each with a Week-1 step logged NOW (current shift).
        foreach (['Alpha', 'Bravo', 'Charlie'] as $i => $name) {
            $eu = EndUser::create([
                'client_id' => $bo->id, 'first_name' => $name, 'last_name' => 'C', 'suffix' => 'None',
                'email' => strtolower($name) . '@t.com', 'current_address' => '1 St', 'city' => 'T',
                'state' => 'ST', 'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
                'intake_status' => null, 'rounds' => ['1st Round'],
            ]);
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => 1, 'week' => 1,
                'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => now()->toDateString(),
                'created_by_admin_id' => $super->id,
            ]);
        }

        // Daily Task: 3 clients worked this shift.
        $this->actingAs($super, 'admin')->get('/admin/daily-task')
            ->assertOk()
            ->assertSee('3 clients');   // panel count "N owners · 3 clients"

        // EOD for the same owner/shift: also 3 — no more 0-vs-3 mismatch.
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/results/eod')
            ->assertOk()
            ->assertSeeText('Total clients worked: 3')
            ->assertSeeText('Rounds sent: 3');
    }
}
