<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The last week of a round (Week 4 on a 30-day cycle, Week 3 on a 20-day cycle)
 * is the closeout — pull report / record deletions. Its "incomplete" flag must
 * NOT appear on the weekly schedule; it appears only once the round is PAST due
 * (days left negative). Earlier weeks keep their normal schedule.
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

    /** Reload with the per-week step counts the badge logic reads. */
    private function withWeekCounts(int $id): EndUser
    {
        return EndUser::withCount([
            'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
            'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
            'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
            'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
        ])->find($id);
    }

    private function client(Client $bo, string $startDate): EndUser
    {
        $admin = Admin::first();
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => 'X', 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => $startDate,
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
        // Log every NON-final week so only the closeout week could be "missing".
        $lastWeek = ProcessStep::weekCount($bo->roundCycleDays());   // 30→4, 20→3
        $types = [1 => 'ex_tu_eq_letters_generated', 2 => 'tu_ex_call_followups', 3 => 'aggressive_bureau_followup'];
        for ($w = 1; $w < $lastWeek; $w++) {
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => 1, 'week' => $w, 'step_type' => $types[$w],
                'step_date' => $startDate, 'created_by_admin_id' => $admin->id,
            ]);
        }
        return $eu;
    }

    public function test_week4_not_flagged_before_due_but_flagged_once_past_due_30day(): void
    {
        $bo = $this->bo(30);

        // Day 25: past the old Week-4 schedule (day 22) but round NOT past due (5 left).
        $notDue = $this->withWeekCounts($this->client($bo, now()->subDays(25)->toDateString())->id);
        $this->assertGreaterThan(0, $notDue->days_left_in_round);
        $this->assertNull($notDue->missing_week, 'Week 4 must NOT flag before the round is past due');

        // Day 35: round past due (days left negative) → Week 4 now flags.
        $pastDue = $this->withWeekCounts($this->client($bo, now()->subDays(35)->toDateString())->id);
        $this->assertLessThan(0, $pastDue->days_left_in_round);
        $this->assertSame(4, $pastDue->missing_week, 'Week 4 must flag once the round is past due');
    }

    public function test_week3_is_the_closeout_on_20day(): void
    {
        $bo = $this->bo(20);

        // Day 16: past old Week-3 schedule (day 13) but not past due (4 left).
        $notDue = $this->withWeekCounts($this->client($bo, now()->subDays(16)->toDateString())->id);
        $this->assertGreaterThan(0, $notDue->days_left_in_round);
        $this->assertNull($notDue->missing_week);

        // Day 24: past due → Week 3 flags.
        $pastDue = $this->withWeekCounts($this->client($bo, now()->subDays(24)->toDateString())->id);
        $this->assertLessThan(0, $pastDue->days_left_in_round);
        $this->assertSame(3, $pastDue->missing_week);
    }

    public function test_earlier_weeks_still_flag_on_schedule(): void
    {
        $bo = $this->bo(30);
        $admin = Admin::first();
        // Day 10, only Week 1 logged → Week 2 (due day 8) should flag as before.
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

        $this->assertSame(2, $this->withWeekCounts($eu->id)->missing_week);
    }
}
