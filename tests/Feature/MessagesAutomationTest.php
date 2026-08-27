<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * We never send automated messages TO business owners. The team adding a client
 * or starting a round posts nothing. The only messages are INBOUND — a new
 * client arriving via the owner's portal, the sign-up link or the API (a system
 * notice the team sees).
 */
class MessagesAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Test BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        return [$super, $bo];
    }

    public function test_team_add_posts_no_message(): void
    {
        [$super, $bo] = $this->world();

        $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', [
                'first_name' => 'New', 'last_name' => 'Client', 'suffix' => 'None',
                'email' => 'new@test.com', 'phone' => '555-0000', 'date_of_birth' => '1990-01-01',
                'ssn' => '111111111', 'current_address' => '2 Rd', 'city' => 'Town',
                'state' => 'ST', 'zipcode' => '99999', 'start_date' => '2026-02-01',
                'credit_monitoring_name' => 'MyScoreIQ', 'credit_monitoring_username' => 'user1',
                'credit_monitoring_password' => 'pass1', 'credit_monitoring_security_answer' => 'blue',
            ])->assertSessionHasNoErrors();

        $this->assertSame(0, Message::where('client_id', $bo->id)->count());
    }

    public function test_starting_a_round_posts_no_message(): void
    {
        [$super, $bo] = $this->world();
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Sarah', 'last_name' => 'C', 'suffix' => 'None',
            'email' => 's@test.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => null, 'rounds' => ['1st Round', '2nd Round'],
        ]);

        $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/process-steps', [
                'end_user_id' => $eu->id, 'round' => 2, 'week' => 1,
                'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => '2026-08-26',
            ])->assertSessionHasNoErrors();

        $this->assertSame(0, Message::where('client_id', $bo->id)->count());
    }

    public function test_inbound_new_client_notice_still_posts(): void
    {
        [, $bo] = $this->world();

        // The BO-add / intake / API paths post this system notice for the team.
        Message::postSystem($bo->id, 'New intake submission from Jane Doe is pending review in New Clients.');

        $msg = Message::where('client_id', $bo->id)->first();
        $this->assertNotNull($msg);
        $this->assertSame(Message::SENDER_SYSTEM, $msg->sender_type);
    }
}
