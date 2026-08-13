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
        $this->assertSame(5, $eu->roundWeekLength());
        $this->assertSame('2026-06-21', $eu->round_end_date);   // 2026-06-01 + 20
        $this->assertSame('2026-06-21', $eu->next_round_date);
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
        // A client 6 days in with no steps: on a 30-day cycle only Week 1 is due;
        // on a 20-day cycle (week length 5) Week 2 is already due too.
        $eu30 = $this->client($this->bo(30), now()->subDays(5)->toDateString()); // day 6
        $eu20 = $this->client($this->bo(20), now()->subDays(5)->toDateString());

        // No steps logged → week counts are 0.
        foreach (['week1_count', 'week2_count', 'week3_count', 'week4_count'] as $c) {
            $eu30->setAttribute($c, 0);
            $eu20->setAttribute($c, 0);
        }

        $this->assertSame(1, $eu30->missing_week, '30-day: day 6 → Week 1 due');
        $this->assertSame(1, $eu20->missing_week, '20-day: Week 1 still first missing');

        // Give both a Week 1 step so the next gap shows.
        foreach (['week1_count'] as $c) { $eu30->setAttribute($c, 1); $eu20->setAttribute($c, 1); }
        $this->assertNull($eu30->missing_week, '30-day: day 6 with Week 1 done → on track');
        $this->assertSame(2, $eu20->missing_week, '20-day: day 6 ≥ 6 → Week 2 now due');
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
