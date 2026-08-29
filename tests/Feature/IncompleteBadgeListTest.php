<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the Clients / In Progress list must load the step's WEEK, or the
 * "falling behind" check counts every step as week-less and wrongly flags every
 * started client as Incomplete. (A missing eager-load column caused exactly
 * that.) A client whose current round is up to date shows NO incomplete badge.
 */
class IncompleteBadgeListTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_to_date_client_has_no_incomplete_badge_on_the_clients_list(): void
    {
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        // 20-day owner (3 weeks; weeks due on days 1 / 7 / 13).
        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 20,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        // Round 1 started 12 days ago (day 13, not past due — 7 days left).
        $start = now()->subDays(12)->toDateString();
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Micheal', 'last_name' => 'Davis', 'suffix' => 'None',
            'email' => 'mike@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => $start,
            'intake_status' => 'done', 'rounds' => ['1st Round'],
            'round_dates' => ['1st Round' => $start],
        ]);

        // Every regular step of weeks 1, 2 and the aggressive step of week 3 —
        // exactly a client that's up to date (closeout isn't due until past due).
        $byWeek = ProcessStep::stepTypesByWeek(20);
        foreach ([1, 2] as $w) {
            foreach (array_keys($byWeek[$w]) as $type) {
                ProcessStep::create(['end_user_id' => $eu->id, 'round' => 1, 'week' => $w,
                    'step_type' => $type, 'step_date' => $start, 'created_by_admin_id' => $super->id]);
            }
        }
        ProcessStep::create(['end_user_id' => $eu->id, 'round' => 1, 'week' => 3,
            'step_type' => 'aggressive_bureau_followup', 'step_date' => $start, 'created_by_admin_id' => $super->id]);

        $html = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/client-list')
            ->assertOk()
            ->assertSee('Micheal')
            ->getContent();

        $this->assertStringNotContainsString('Incomplete · log', $html,
            'an up-to-date client must NOT show the incomplete badge on the list');
    }
}
