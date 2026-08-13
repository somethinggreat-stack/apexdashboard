<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundCycleTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Admin
    {
        if ($existing = Admin::where('email', 'super@test.com')->first()) {
            return $existing;
        }
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';   // role isn't mass-assignable
        $super->save();
        return $super;
    }

    private function bo(int $cycle = 30): Client
    {
        $super = $this->superAdmin();

        return Client::create([
            'admin_id' => $super->id, 'business_name' => "BO {$cycle}", 'email' => "bo{$cycle}@test.com",
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => $cycle,
        ]);
    }

    private function client(Client $bo, string $start = '2026-06-01'): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Jane', 'last_name' => 'Roe', 'suffix' => 'None',
            'email' => "c{$bo->id}@test.com", 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => $start,
            'intake_status' => 'done', 'rounds' => ['1st Round'],
        ]);
    }

    public function test_cycle_defaults_to_30_when_unset(): void
    {
        $super = $this->superAdmin();
        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Default BO', 'email' => 'def@test.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active', 'compensation_model' => 'per_round',
        ]);
        $this->assertSame(30, $bo->roundCycleDays());
        $this->assertSame(30, $this->client($bo)->roundCycleDays());
    }

    public function test_30_day_cycle_dates(): void
    {
        $eu = $this->client($this->bo(30));
        $this->assertSame(30, $eu->roundCycleDays());
        $this->assertSame(7, $eu->roundWeekLength());
        $this->assertSame('2026-07-01', $eu->round_end_date);   // 2026-06-01 + 30
        $this->assertSame('2026-07-01', $eu->next_round_date);
    }

    public function test_20_day_cycle_dates(): void
    {
        $eu = $this->client($this->bo(20));
        $this->assertSame(20, $eu->roundCycleDays());
        $this->assertSame(3, $eu->roundWeekCount());            // 20-day → 3 weeks
        $this->assertSame(6, $eu->roundWeekLength());           // intdiv(20, 3)
        $this->assertSame('2026-06-21', $eu->round_end_date);   // 2026-06-01 + 20
        $this->assertSame('2026-06-21', $eu->next_round_date);
    }

    public function test_step_structure_compresses_for_20_day(): void
    {
        $thirty = \App\Models\ProcessStep::stepTypesByWeek(30);
        $twenty = \App\Models\ProcessStep::stepTypesByWeek(20);

        $this->assertCount(4, $thirty, '30-day → 4 weeks');
        $this->assertCount(3, $twenty, '20-day → 3 weeks');

        // Same nine steps overall, just regrouped.
        $this->assertSame(
            array_keys(array_merge(...array_values($thirty))),
            array_keys(array_merge(...array_values($twenty))),
        );

        // 20-day week 3 is the closeout: aggressive follow-up, pull report, deletions.
        $this->assertSame(
            ['aggressive_bureau_followup', 'pull_latest_report', 'record_deletions'],
            array_keys($twenty[3]),
        );
        // All disputing steps land in weeks 1–2.
        $this->assertCount(3, $twenty[1]);
        $this->assertCount(3, $twenty[2]);
    }

    public function test_flipping_owner_reprices_existing_clients(): void
    {
        $bo = $this->bo(30);
        $eu = $this->client($bo);
        $this->assertSame('2026-07-01', $eu->round_end_date);

        // Flip the owner to 20 days — the existing client's dates recompute live.
        $bo->update(['round_cycle_days' => 20]);
        $eu->refresh()->load('client');
        $this->assertSame('2026-06-21', $eu->round_end_date);
    }

    public function test_missing_week_thresholds_scale_with_cycle(): void
    {
        // A client on day 7: 30-day Week 2 isn't due until day 8, but 20-day
        // Week 2 (week length 6) is due on day 7.
        $eu30 = $this->client($this->bo(30), now()->subDays(6)->toDateString()); // day 7
        $eu20 = $this->client($this->bo(20), now()->subDays(6)->toDateString());

        foreach (['week1_count', 'week2_count', 'week3_count', 'week4_count'] as $c) {
            $eu30->setAttribute($c, 0);
            $eu20->setAttribute($c, 0);
        }

        $this->assertSame(1, $eu30->missing_week, '30-day: Week 1 first missing');
        $this->assertSame(1, $eu20->missing_week, '20-day: Week 1 first missing');

        // Log Week 1 for both, then the difference shows.
        $eu30->setAttribute('week1_count', 1);
        $eu20->setAttribute('week1_count', 1);
        $this->assertNull($eu30->missing_week, '30-day: day 7 < 8 → on track');
        $this->assertSame(2, $eu20->missing_week, '20-day: day 7 ≥ 7 → Week 2 due');
    }

    public function test_20_day_client_accepts_week3_closeout_step(): void
    {
        $super = $this->superAdmin();
        $bo = $this->bo(20);
        $eu = $this->client($bo);

        // Week 3 closeout step is valid for a 20-day client...
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/process-steps', [
                'end_user_id' => $eu->id, 'round' => 1, 'week' => 3,
                'step_types' => ['record_deletions'], 'step_date' => '2026-06-18',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('process_steps', [
            'end_user_id' => $eu->id, 'week' => 3, 'step_type' => 'record_deletions',
        ]);

        // ...but week 4 does not exist on a 20-day cycle.
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/process-steps', [
                'end_user_id' => $eu->id, 'round' => 1, 'week' => 4,
                'step_types' => ['record_deletions'], 'step_date' => '2026-06-18',
            ])->assertSessionHasErrors('week');
    }

    public function test_add_business_owner_persists_cycle(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super, 'admin')->post('/admin/clients', [
            'business_name' => 'Twenty BO', 'email' => 'twenty@test.com', 'password' => 'secret1',
            'monthly_fee' => 149, 'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'round_cycle_days' => 20,
        ])->assertRedirect();

        $this->assertSame(20, Client::where('email', 'twenty@test.com')->value('round_cycle_days'));
    }
}
