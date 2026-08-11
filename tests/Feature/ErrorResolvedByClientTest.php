<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorResolvedByClientTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Admin, 1: Client, 2: Client} super, bo, otherBo */
    private function world(): array
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Test BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);

        $other = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Other BO', 'email' => 'other@test.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);

        return [$super, $bo, $other];
    }

    private function roundError(Client $bo, string $email = 're@test.com'): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Rex', 'last_name' => 'Err', 'suffix' => 'None',
            'email' => $email, 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-01-01',
            'intake_status' => 'round_error', 'rounds' => ['1st Round', '2nd Round'],
            'credit_monitoring_name' => 'MyScoreIQ', 'credit_monitoring_username' => 'olduser',
            'credit_monitoring_password' => 'oldpass', 'credit_monitoring_security_answer' => 'oldans',
            'credit_monitoring_pin' => '1111',
        ]);
    }

    public function test_bo_resolve_sets_flag_and_updates_login(): void
    {
        [, $bo] = $this->world();
        $eu = $this->roundError($bo);

        $resp = $this->actingAs($bo, 'client')->put("/business-owner/round-errors/{$eu->id}/resolve", [
            'credit_monitoring_name' => 'IdentityIQ',
            'credit_monitoring_username' => 'newuser',
            'credit_monitoring_password' => 'newpass',
            'credit_monitoring_security_question' => 'City?',
            'credit_monitoring_security_answer' => 'newans',
            'credit_monitoring_pin' => '2222',
        ]);

        $resp->assertRedirect(route('client.errors-resolved'));
        $resp->assertSessionHas('confirm');

        $eu->refresh();
        $this->assertNotNull($eu->error_resolved_by_client_at, 'Resolved timestamp is set');
        $this->assertSame('IdentityIQ', $eu->credit_monitoring_name);
        $this->assertSame('newuser', $eu->credit_monitoring_username);
        $this->assertSame('newpass', $eu->credit_monitoring_password);
        $this->assertSame('newans', $eu->credit_monitoring_security_answer);
        $this->assertSame('2222', $eu->credit_monitoring_pin);
        // Still a round_error — only the resolved flag moves it between lists.
        $this->assertSame('round_error', $eu->intake_status);
    }

    public function test_blank_secret_keeps_existing_value(): void
    {
        [, $bo] = $this->world();
        $eu = $this->roundError($bo);

        $this->actingAs($bo, 'client')->put("/business-owner/round-errors/{$eu->id}/resolve", [
            'credit_monitoring_name' => 'IdentityIQ',
            'credit_monitoring_username' => 'newuser',
            'credit_monitoring_password' => '',   // blank -> keep old
            'credit_monitoring_security_answer' => '',
            'credit_monitoring_pin' => '',
        ])->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertSame('oldpass', $eu->credit_monitoring_password, 'Blank password keeps old secret');
        $this->assertSame('oldans', $eu->credit_monitoring_security_answer);
        $this->assertSame('1111', $eu->credit_monitoring_pin);
    }

    public function test_bo_cannot_resolve_another_owners_client(): void
    {
        [, $bo, $other] = $this->world();
        $eu = $this->roundError($other, 'other-client@test.com');

        $this->actingAs($bo, 'client')
            ->put("/business-owner/round-errors/{$eu->id}/resolve", [
                'credit_monitoring_username' => 'hijack',
            ])
            ->assertNotFound();

        $eu->refresh();
        $this->assertNull($eu->error_resolved_by_client_at);
        $this->assertSame('olduser', $eu->credit_monitoring_username);
    }

    public function test_bo_cannot_resolve_already_resolved_client(): void
    {
        [, $bo] = $this->world();
        $eu = $this->roundError($bo);
        $eu->update(['error_resolved_by_client_at' => now()]);

        $this->actingAs($bo, 'client')
            ->put("/business-owner/round-errors/{$eu->id}/resolve", [
                'credit_monitoring_username' => 'again',
            ])
            ->assertNotFound();
    }

    public function test_lists_split_pending_and_resolved(): void
    {
        [, $bo] = $this->world();
        $pending  = $this->roundError($bo, 'pending@test.com');
        $resolved = $this->roundError($bo, 'resolved@test.com');
        $resolved->update(['error_resolved_by_client_at' => now()]);

        $this->assertSame(1, EndUser::forClient($bo->id)->roundErrorPending()->count());
        $this->assertSame(1, EndUser::forClient($bo->id)->roundErrorResolvedByClient()->count());

        $this->actingAs($bo, 'client')->get('/business-owner/round-errors')
            ->assertOk()->assertSee('pending@test.com')->assertDontSee('resolved@test.com');

        $this->actingAs($bo, 'client')->get('/business-owner/errors-resolved')
            ->assertOk()->assertSee('resolved@test.com')->assertDontSee('pending@test.com');
    }

    public function test_va_resolve_clears_flag_and_moves_to_clients(): void
    {
        [$super, $bo] = $this->world();
        $eu = $this->roundError($bo);
        $eu->update(['error_resolved_by_client_at' => now()]);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post("/admin/end-users/{$eu->id}/resolve-round-error")
            ->assertRedirect();

        $eu->refresh();
        $this->assertNull($eu->error_resolved_by_client_at, 'Flag cleared after VA processes');
        $this->assertSame('done', $eu->intake_status);
    }

    public function test_va_errors_resolved_list_shows_resolved_client(): void
    {
        [$super, $bo] = $this->world();
        $eu = $this->roundError($bo, 'shown@test.com');
        $eu->update(['error_resolved_by_client_at' => now()]);

        // VA list shows the client name and the BO-entered login username (not the client email).
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/errors-resolved-by-client')
            ->assertOk()->assertSee('Rex Err')->assertSee('olduser');
    }

    public function test_round_error_client_still_appears_in_payments(): void
    {
        [$super, $bo] = $this->world();
        $eu = $this->roundError($bo, 'billme@test.com');   // round_error, reached 1st + 2nd

        // They dropped out of Round Errors' billing exclusion — Payments still lists them
        // so their already-completed rounds can be collected.
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/payments')
            ->assertOk()->assertSee('Rex Err');
    }

    public function test_pay_all_unpaid_bills_reached_rounds_of_round_error_client(): void
    {
        [$super, $bo] = $this->world();
        $eu = $this->roundError($bo, 'billme@test.com');   // rounds: 1st + 2nd

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/payments/pay-all-unpaid')
            ->assertSessionHas('confirm');

        // Both reached rounds must be billed even though the client sits in Round Errors.
        $this->assertSame(2, \App\Models\ClientPayment::where('end_user_id', $eu->id)->count());
    }

    public function test_new_client_and_new_client_error_stay_out_of_payments(): void
    {
        [$super, $bo] = $this->world();

        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Newby', 'last_name' => 'Pending', 'suffix' => 'None',
            'email' => 'newby@test.com', 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-01-01',
            'intake_status' => 'pending_review', 'rounds' => ['1st Round'],
        ]);
        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Errol', 'last_name' => 'Intake', 'suffix' => 'None',
            'email' => 'errol@test.com', 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-01-01',
            'intake_status' => 'error', 'rounds' => ['1st Round'],
        ]);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/payments')
            ->assertOk()->assertDontSee('Newby Pending')->assertDontSee('Errol Intake');
    }
}
