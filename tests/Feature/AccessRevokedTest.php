<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRevokedTest extends TestCase
{
    use RefreshDatabase;

    private function bo(bool $revoked = false): Client
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        return Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'access_revoked' => $revoked,
        ]);
    }

    public function test_revoked_owner_is_blocked_at_login(): void
    {
        $this->bo(revoked: true);

        $resp = $this->post('/business-owner/login', ['email' => 'bo@test.com', 'password' => 'secret']);

        $resp->assertStatus(403);
        $resp->assertSee('Dashboard Access Suspended');
        $resp->assertSee('Amount Due');
        $this->assertGuest('client');   // no session established
    }

    public function test_active_owner_logs_in_normally(): void
    {
        $this->bo(revoked: false);

        $this->post('/business-owner/login', ['email' => 'bo@test.com', 'password' => 'secret'])
            ->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticated('client');
    }

    public function test_revoked_owner_in_session_is_walled_off(): void
    {
        $bo = $this->bo(revoked: true);

        // Even if already authenticated, every page returns the wall.
        $this->actingAs($bo, 'client')->get('/business-owner/dashboard')
            ->assertStatus(403)->assertSee('Dashboard Access Suspended');

        $this->actingAs($bo, 'client')->get('/business-owner/client-list')
            ->assertStatus(403);

        // Logout is still allowed so they can leave.
        $this->actingAs($bo, 'client')->post('/business-owner/logout')
            ->assertRedirect(route('client.login'));
    }

    public function test_super_admin_can_toggle_access_revoked(): void
    {
        $bo = $this->bo(revoked: false);
        $super = Admin::where('email', 'super@test.com')->first();

        $this->actingAs($super, 'admin')->put("/admin/clients/{$bo->id}", [
            'business_name' => 'BO', 'email' => 'bo@test.com', 'status' => 'active',
            'round_cycle_days' => 30, 'access_revoked' => '1',
            'access_revoked_message' => 'Please pay your July invoice.',
        ])->assertRedirect();

        $bo->refresh();
        $this->assertTrue($bo->access_revoked);
        $this->assertSame('Please pay your July invoice.', $bo->access_revoked_message);
    }
}
