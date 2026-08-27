<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The closeout steps (Pull Latest Report + Record Deletions) — in 20-day Week 3
 * / 30-day Week 4 — must flag as "incomplete" ONLY once the round is past due
 * (days left negative), and independently of the aggressive-follow-up step that
 * shares the last week on a 20-day cycle. Every OTHER week flags on its normal
 * schedule, unchanged.
 */
class LastWeekIncompleteTest extends TestCase
{
    use RefreshDatabase;

    private function bo(int $cycle): Client
    {
        $super = new Admin(['email' => "s{$cycle}@t.com", 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        return Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => "bo{$cycle}@t.com",
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => $cycle,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
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

    /** A client with every REGULAR step logged (incl. aggressive) but NO closeout. */
    private function client(Client $bo, string $startDate): EndUser
    {
        $admin = Admin::first();
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => 'X', 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => $startDate,
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
        // First regular (non-closeout) step of each week — leaves ONLY closeout missing.
        foreach (ProcessStep::stepTypesByWeek($bo->roundCycleDays()) as $w => $steps) {
            $regular = array_values(array_diff(array_keys($steps), EndUser::CLOSEOUT_STEPS));
            if (! $regular) {
                continue;   // 30-day Week 4 — closeout only, nothing to log
            }
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => 1, 'week' => $w, 'step_type' => $regular[0],
                'step_date' => $startDate, 'created_by_admin_id' => $admin->id,
            ]);
        }
        return $eu;
    }

    public function test_closeout_flags_only_past_due_even_when_aggressive_is_logged_30day(): void
    {
        $bo = $this->bo(30);

        // Day 25: past the old Week-4 schedule day (22) but NOT past due → not incomplete.
        $notDue = $this->withCounts($this->client($bo, now()->subDays(25)->toDateString())->id);
        $this->assertGreaterThan(0, $notDue->days_left_in_round);
        $this->assertNull($notDue->missing_week);
        $this->assertFalse($notDue->is_incomplete);

        // Day 35: past due → closeout flags, even though aggressive (week 3) is logged.
        $pastDue = $this->withCounts($this->client($bo, now()->subDays(35)->toDateString())->id);
        $this->assertLessThan(0, $pastDue->days_left_in_round);
        $this->assertNull($pastDue->missing_week);          // all regular weeks done
        $this->assertTrue($pastDue->closeout_due);
        $this->assertTrue($pastDue->is_incomplete);
    }

    public function test_closeout_flags_only_past_due_on_20day(): void
    {
        $bo = $this->bo(20);   // closeout lives in Week 3 with the aggressive step

        $notDue = $this->withCounts($this->client($bo, now()->subDays(16)->toDateString())->id);
        $this->assertGreaterThan(0, $notDue->days_left_in_round);
        $this->assertFalse($notDue->is_incomplete);

        // Aggressive logged, closeout missing, past due → incomplete (the ADEOLA case).
        $pastDue = $this->withCounts($this->client($bo, now()->subDays(24)->toDateString())->id);
        $this->assertLessThan(0, $pastDue->days_left_in_round);
        $this->assertTrue($pastDue->closeout_due);
        $this->assertTrue($pastDue->is_incomplete);
    }

    public function test_earlier_weeks_still_flag_on_schedule(): void
    {
        $bo = $this->bo(30);
        $admin = Admin::first();
        // Day 10, only Week 1 logged → Week 2 (due day 8) flags, as before.
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'E', 'last_name' => 'Y', 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(10)->toDateString(),
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 1, 'week' => 1, 'step_type' => 'ex_tu_eq_letters_generated',
            'step_date' => now()->subDays(10)->toDateString(), 'created_by_admin_id' => $admin->id,
        ]);

        $this->assertSame(2, $this->withCounts($eu->id)->missing_week);
    }
}
