<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The owner-facing Tasks View: a 30-day log of rounds started per day. Mirrors
 * the internal Daily Task signal (Week-1 steps), never leaks the VA's name, and
 * drops work older than the 30-day window.
 */
class ClientTasksViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_recent_rounds_started_without_leaking_va_names(): void
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'Secret VA Name']);
        $va->role = 'va';
        $va->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'round_cycle_days' => 30, 'intake_enabled' => true,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        $recent = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Worked', 'last_name' => 'Client',
            'suffix' => 'None', 'email' => 'worked@test.com', 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => '2026-06-01', 'intake_status' => null, 'rounds' => ['1st Round', '2nd Round'],
        ]);
        $old = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Ancient', 'last_name' => 'Client',
            'suffix' => 'None', 'email' => 'ancient@test.com', 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => '2026-01-01', 'intake_status' => null, 'rounds' => ['1st Round'],
        ]);

        // Recent Week-1 step (round started) — should appear, attributed to the team.
        ProcessStep::create([
            'end_user_id' => $recent->id, 'round' => 2, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => now()->toDateString(),
            'created_by_admin_id' => $va->id,
        ]);

        // A Week-2 step (not a round start) — must NOT count.
        ProcessStep::create([
            'end_user_id' => $recent->id, 'round' => 2, 'week' => 2,
            'step_type' => 'tu_ex_call_followups', 'step_date' => now()->toDateString(),
            'created_by_admin_id' => $va->id,
        ]);

        // An old Week-1 step (>30 days ago) — outside the window, must NOT appear.
        $oldStep = ProcessStep::create([
            'end_user_id' => $old->id, 'round' => 1, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => '2026-01-05',
            'created_by_admin_id' => $va->id,
        ]);
        $oldStep->forceFill(['created_at' => Carbon::now()->subDays(40)])->save();

        $resp = $this->actingAs($bo, 'client')->get(route('client.tasks'));

        $resp->assertStatus(200);
        $resp->assertSee('Worked Client');                 // recent round-start client shown
        $resp->assertSee('EX, TU, EQ Letter Generated');   // the step label
        $resp->assertSee('2nd Round started');             // round label
        $resp->assertDontSee('Secret VA Name');            // VA name never leaks
        $resp->assertDontSee('Ancient Client');            // >30 days old excluded
        $resp->assertDontSee('TransUnion & Experian Call Follow-Ups'); // Week-2 not counted
    }
}
