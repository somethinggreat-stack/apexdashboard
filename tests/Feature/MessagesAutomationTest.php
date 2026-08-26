<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Automatic owner-facing messages: client added, round started (2+), and the
 * nightly 10 PM ET daily digest. All post as "Apex Growth Team" (an admin
 * message with no individual sender).
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

    private function addPayload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'New', 'last_name' => 'Client', 'suffix' => 'None',
            'email' => 'new@test.com', 'phone' => '555-0000', 'date_of_birth' => '1990-01-01',
            'ssn' => '111111111', 'current_address' => '2 Rd', 'city' => 'Town',
            'state' => 'ST', 'zipcode' => '99999', 'start_date' => '2026-02-01',
            'credit_monitoring_name' => 'MyScoreIQ', 'credit_monitoring_username' => 'user1',
            'credit_monitoring_password' => 'pass1', 'credit_monitoring_security_answer' => 'blue',
        ], $override);
    }

    private function eu(Client $bo, string $first, array $rounds = ['1st Round']): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => $first, 'last_name' => 'Client', 'suffix' => 'None',
            'email' => strtolower($first) . '@test.com', 'current_address' => '1 St', 'city' => 'Town',
            'state' => 'ST', 'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => null, 'rounds' => $rounds,
        ]);
    }

    public function test_adding_a_client_messages_the_owner_from_the_team(): void
    {
        [$super, $bo] = $this->world();

        $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload())
            ->assertSessionHasNoErrors();

        $msg = Message::where('client_id', $bo->id)->latest('id')->first();
        $this->assertNotNull($msg);
        $this->assertSame(Message::SENDER_ADMIN, $msg->sender_type);
        $this->assertNull($msg->sender_id);                       // no individual VA
        $this->assertStringContainsString('started working on their credit repair', $msg->body);
        $this->assertStringNotContainsString('Start working on it', $msg->body);
    }

    public function test_only_week1_first_step_of_a_round_messages_the_owner(): void
    {
        [$super, $bo] = $this->world();
        $eu = $this->eu($bo, 'Sarah', ['1st Round', '2nd Round']);

        $post = fn (int $round, int $week, string $type) => $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/process-steps', [
                'end_user_id' => $eu->id, 'round' => $round, 'week' => $week,
                'step_type' => $type, 'step_date' => '2026-08-26',
            ])->assertSessionHasNoErrors();

        // Round 1 Week 1 → one "started Round 1" message (any round counts), and
        // the very first message must NOT promise results yet.
        $post(1, 1, 'ex_tu_eq_letters_generated');
        $this->assertSame(1, Message::where('client_id', $bo->id)->count());
        $r1 = Message::where('client_id', $bo->id)->first()->body;
        $this->assertStringContainsString('started Round 1', $r1);
        $this->assertStringNotContainsStringIgnoringCase('results', $r1);

        // A second Round 1 Week-1 step → NO duplicate.
        $post(1, 1, 'phone_call_disputes');
        $this->assertSame(1, Message::where('client_id', $bo->id)->count());

        // Week 2 of Round 1 → NO message.
        $post(1, 2, 'tu_ex_call_followups');
        $this->assertSame(1, Message::where('client_id', $bo->id)->count());

        // Round 2 Week 1 → a second message ("started Round 2").
        $post(2, 1, 'ex_tu_eq_letters_generated');
        $this->assertSame(2, Message::where('client_id', $bo->id)->count());
        $this->assertStringContainsString('started Round 2', Message::where('client_id', $bo->id)->latest('id')->first()->body);
    }

    public function test_daily_digest_sends_only_to_owners_with_work_today(): void
    {
        [$super, $boActive] = $this->world();
        $boQuiet = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Quiet BO', 'email' => 'quiet@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        $worked = $this->eu($boActive, 'Jahkayah');
        // Today's round-2 start.
        ProcessStep::create([
            'end_user_id' => $worked->id, 'round' => 2, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => now()->toDateString(), 'created_by_admin_id' => $super->id,
        ]);

        // Quiet owner: a step from 40 days ago (outside today).
        $old = $this->eu($boQuiet, 'Ancient');
        $oldStep = ProcessStep::create([
            'end_user_id' => $old->id, 'round' => 2, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => '2026-01-01', 'created_by_admin_id' => $super->id,
        ]);
        $oldStep->forceFill(['created_at' => Carbon::now()->subDays(40)])->save();

        Artisan::call('messages:daily-digest');

        // Active owner got one team digest naming the client + round.
        $digest = Message::where('client_id', $boActive->id)->get();
        $this->assertCount(1, $digest);
        $this->assertSame(Message::SENDER_ADMIN, $digest->first()->sender_type);
        $this->assertStringContainsString('Jahkayah Client', $digest->first()->body);
        $this->assertStringContainsString('Round 2 started', $digest->first()->body);

        // Quiet owner got nothing.
        $this->assertSame(0, Message::where('client_id', $boQuiet->id)->count());
    }
}
