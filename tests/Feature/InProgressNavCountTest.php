<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The In Progress sidebar item shows a count, like the error lists — it equals
 * the not-held, in-progress clients for the selected business owner.
 */
class InProgressNavCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_counts_in_progress_matches_the_list(): void
    {
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        $mk = function (string $suffix, ?string $status, bool $held = false) use ($bo) {
            EndUser::create([
                'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => $suffix, 'suffix' => 'None',
                'email' => "$suffix@t.com", 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
                'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
                'intake_status' => $status, 'held_at' => $held ? now() : null, 'rounds' => ['1st Round'],
            ]);
        };

        $mk('a', null);            // in progress
        $mk('b', null);            // in progress
        $mk('c', 'done');          // not in progress
        $mk('d', 'error');         // not in progress
        $mk('e', 'pending_review');// not in progress
        $mk('f', null, true);      // in progress status but HELD → excluded

        $this->assertSame(2, $bo->navCounts()['in_progress']);

        // And it renders as a badge on an admin per-BO page.
        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->get(route('admin.end-users.index'));
        $resp->assertStatus(200);
        $resp->assertSee('In Progress');
    }
}
