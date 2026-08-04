<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientGuardsTest extends TestCase
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
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);

        return [$super, $bo];
    }

    private function makeEndUser(Client $bo, string $email, array $rounds = ['1st Round'], ?string $ssn = null): EndUser
    {
        return EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Jane', 'last_name' => 'Roe', 'suffix' => 'None',
            'email' => $email, 'ssn' => $ssn, 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-01-01',
            'intake_status' => 'done', 'rounds' => $rounds,
        ]);
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

    public function test_duplicate_email_is_rejected_on_add(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'dupe@test.com');

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload(['email' => 'DUPE@test.com'])); // case-insensitive

        $resp->assertSessionHasErrors('email');
        $this->assertSame(1, EndUser::where('client_id', $bo->id)->count(), 'No duplicate should be created');
    }

    public function test_unique_email_is_accepted_on_add(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'existing@test.com');

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload(['email' => 'fresh@test.com']));

        $resp->assertSessionHasNoErrors();
        $this->assertSame(2, EndUser::where('client_id', $bo->id)->count());
    }

    public function test_email_check_endpoint_reports_existing(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'known@test.com');

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->getJson('/admin/end-users-dup-check?field=email&value=known@test.com')
            ->assertJson(['exists' => true]);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->getJson('/admin/end-users-dup-check?field=email&value=nobody@test.com')
            ->assertJson(['exists' => false]);
    }

    public function test_ssn_check_matches_regardless_of_dashes(): void
    {
        [$super, $bo] = $this->world();
        // Stored WITH dashes (legacy format); a plain-digit query must still match.
        $this->makeEndUser($bo, 'known@test.com', ['1st Round'], '243-41-9724');

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->getJson('/admin/end-users-dup-check?field=ssn&value=243419724')
            ->assertJson(['exists' => true]);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->getJson('/admin/end-users-dup-check?field=ssn&value=000000000')
            ->assertJson(['exists' => false]);
    }

    public function test_duplicate_ssn_is_rejected_on_add(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'first@test.com', ['1st Round'], '243419724');

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload(['email' => 'second@test.com', 'ssn' => '243419724']));

        $resp->assertSessionHasErrors('ssn');
        $this->assertSame(1, EndUser::where('client_id', $bo->id)->count());
    }

    public function test_ssn_must_be_nine_digits(): void
    {
        [$super, $bo] = $this->world();

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload(['ssn' => '12345']))
            ->assertSessionHasErrors('ssn');

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/end-users', $this->addPayload(['ssn' => '123-45-6789']))
            ->assertSessionHasErrors('ssn');   // dashes are not digits
    }

    public function test_pay_all_unpaid_marks_every_active_round(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'a@test.com', ['1st Round', '2nd Round']);
        $this->makeEndUser($bo, 'b@test.com', ['1st Round']);

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/payments/pay-all-unpaid');

        $resp->assertSessionHas('confirm');
        // 2 rounds for A + 1 round for B = 3 payments, all at $15.
        $this->assertSame(3, ClientPayment::count());
        $this->assertEqualsWithDelta(45.0, (float) ClientPayment::sum('amount'), 0.001);
    }

    public function test_pay_all_unpaid_is_idempotent(): void
    {
        [$super, $bo] = $this->world();
        $this->makeEndUser($bo, 'a@test.com', ['1st Round', '2nd Round']);

        $req = fn () => $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/payments/pay-all-unpaid');

        $req();
        $req();   // second click must not double-charge

        $this->assertSame(2, ClientPayment::count(), 'Already-paid rounds are left as-is');
    }
}
