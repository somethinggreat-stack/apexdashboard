<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A round — and every day-count tied to it — starts ONLY when its first Week 1
 * step is logged. A freshly added client has no round marked, no start date, no
 * days active, no days left, no next-round date, and no "past due" warning until
 * then. Applies to every round (2nd+ included) and every business owner.
 */
class RoundStartsWhenMarkedTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $this->super->role = 'super';
        $this->super->save();
        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function client(array $attrs = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id' => $this->bo->id, 'first_name' => 'New', 'last_name' => 'Client',
            'suffix' => 'None', 'email' => uniqid() . '@t.com', 'current_address' => '1 St',
            'city' => 'T', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => now()->subDays(45)->toDateString(),   // added long ago…
            'intake_status' => null, 'rounds' => [],               // …but never marked
        ], $attrs));
    }

    private function reload(int $id): EndUser
    {
        return EndUser::with('processSteps')->find($id);
    }

    public function test_a_freshly_added_client_has_no_round_and_no_counts(): void
    {
        $eu = $this->reload($this->client()->id);

        $this->assertFalse($eu->ever_started, 'no round marked yet');
        $this->assertFalse($eu->round_started);
        $this->assertNull($eu->current_round_start_date);
        $this->assertNull($eu->roundStartDate(1));
        $this->assertNull($eu->days_active, 'no days count before the round is marked');
        $this->assertNull($eu->days_left_in_round);
        $this->assertNull($eu->next_round_date);
        $this->assertNull($eu->missing_week);
        $this->assertFalse($eu->needs_round_closeout, 'no false "past 30 days" warning');
        $this->assertFalse($eu->is_incomplete);
        $this->assertSame([], $eu->round_timeline);
    }

    public function test_intake_default_leaves_the_client_unmarked(): void
    {
        // Mirrors what the intake endpoints now store: rounds = [].
        $eu = $this->reload($this->client(['rounds' => [], 'intake_status' => 'pending_review'])->id);
        $this->assertFalse($eu->ever_started);
        $this->assertNull($eu->days_active);
    }

    public function test_logging_first_week1_step_marks_round_and_starts_the_clock(): void
    {
        $eu = $this->client();
        $stepDate = now()->subDays(5)->toDateString();

        $this->actingAs($this->super, 'admin')
            ->withSession(['selected_client_id' => $this->bo->id])
            ->post(route('admin.process-steps.store'), [
                'end_user_id' => $eu->id,
                'round'       => 1,
                'week'        => 1,
                'step_types'  => ['ex_tu_eq_letters_generated'],
                'step_date'   => $stepDate,
            ])->assertSessionHasNoErrors();

        $eu = $this->reload($eu->id);

        $this->assertTrue($eu->ever_started, 'the Week 1 step marks the round');
        $this->assertSame($stepDate, $eu->roundStartDate(1), 'start date = the step date, not the added date');
        $this->assertSame($stepDate, $eu->current_round_start_date);
        $this->assertSame(6, $eu->days_active, 'counts from the marked date (5 days ago, inclusive)');
        $this->assertContains('1st Round', $eu->rounds, 'round label is now marked');
        $this->assertNotNull($eu->next_round_date);
        $this->assertNotNull($eu->days_left_in_round);
    }

    public function test_next_round_is_measured_from_the_latest_started_round(): void
    {
        // 20-day owner. A client whose 3rd round is marked (Aug 19) — the next
        // round date and days-left must come from the LATEST started round, not
        // an earlier one. Regression: a client showed next-round from round 2
        // (Aug 4 + 20) while the timeline showed round 3 started Aug 19.
        $this->bo->update(['round_cycle_days' => 20]);
        $eu = $this->client([
            'rounds' => ['1st Round', '2nd Round'],
            'round_dates' => [
                '1st Round' => '2026-06-13',
                '2nd Round' => '2026-08-04',
                '3rd Round' => '2026-08-19',
            ],
        ]);
        $eu = $this->reload($eu->id);

        $this->assertSame(3, $eu->current_round, 'current round follows the latest started round');
        $this->assertSame('2026-08-19', $eu->current_round_start_date);
        $this->assertSame('2026-09-08', $eu->next_round_date, 'exactly 20 days after the 3rd round start');
        $this->assertSame(['1st Round', '2nd Round', '3rd Round'], array_keys($eu->round_timeline));
    }

    public function test_round_two_client_behind_is_flagged_incomplete(): void
    {
        // Round 1 fully complete; on Round 2 for 10 days but only Week 1 logged.
        // The old cross-round counters treated every week as "done forever" once
        // Round 1 had logged it, so this client silently fell off Needs Attention.
        $eu = $this->client([
            'rounds' => ['1st Round', '2nd Round'],
            'round_dates' => [
                '1st Round' => now()->subDays(40)->toDateString(),
                '2nd Round' => now()->subDays(10)->toDateString(),
            ],
        ]);
        foreach (ProcessStep::stepTypesByWeek(30) as $w => $steps) {
            foreach (array_keys($steps) as $type) {
                ProcessStep::create([
                    'end_user_id' => $eu->id, 'round' => 1, 'week' => $w, 'step_type' => $type,
                    'step_date' => now()->subDays(35)->toDateString(), 'created_by_admin_id' => $this->super->id,
                ]);
            }
        }
        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 2, 'week' => 1, 'step_type' => 'ex_tu_eq_letters_generated',
            'step_date' => now()->subDays(10)->toDateString(), 'created_by_admin_id' => $this->super->id,
        ]);

        $eu = $this->reload($eu->id);
        $this->assertSame(2, $eu->current_round);
        $this->assertSame(2, $eu->missing_week, 'Round 2 Week 2 is overdue and must be flagged');
        $this->assertTrue($eu->is_incomplete, 'behind on Round 2 → shows as incomplete');
    }

    public function test_started_rounds_short_label(): void
    {
        $eu = $this->reload($this->client([
            'rounds' => ['1st Round', '2nd Round'],
            'round_dates' => ['1st Round' => '2026-06-13', '2nd Round' => '2026-07-01'],
        ])->id);
        $this->assertSame('R1, R2', $eu->started_rounds_short);
        $this->assertSame('1st Round, 2nd Round', $eu->started_rounds_full);

        // A never-started client has no short label (shows "Not started" in views).
        $this->assertSame('', $this->reload($this->client()->id)->started_rounds_short);
    }

    public function test_second_round_does_not_count_until_its_week1_is_marked(): void
    {
        // Round 1 was worked 40 days ago; round 2 has been reached (advanced) but
        // not started — no round-2 step logged.
        $eu = $this->client([
            'rounds' => ['1st Round', '2nd Round'],
            'round_dates' => ['1st Round' => now()->subDays(40)->toDateString()],
        ]);
        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 1, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated',
            'step_date' => now()->subDays(40)->toDateString(), 'created_by_admin_id' => $this->super->id,
        ]);

        $eu = $this->reload($eu->id);
        $this->assertSame(2, $eu->current_round, 'on round 2');
        $this->assertTrue($eu->ever_started, 'the client HAS been worked (round 1)');
        $this->assertFalse($eu->round_started, 'but round 2 has not started');
        $this->assertNull($eu->roundStartDate(2));
        $this->assertNull($eu->days_left_in_round, 'no round-2 countdown yet');

        // Now log round 2, week 1 → round 2 starts today.
        $today = now()->toDateString();
        ProcessStep::create([
            'end_user_id' => $eu->id, 'round' => 2, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated',
            'step_date' => $today, 'created_by_admin_id' => $this->super->id,
        ]);

        $eu = $this->reload($eu->id);
        $this->assertTrue($eu->round_started);
        $this->assertSame($today, $eu->roundStartDate(2));
        $this->assertNotNull($eu->days_left_in_round);
    }
}
