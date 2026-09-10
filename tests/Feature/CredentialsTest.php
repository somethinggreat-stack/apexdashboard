<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BusinessOwnerCredential;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo;

    private function seedWorld(): void
    {
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Alin', 'email' => 'alin@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
    }

    private function va(bool $canManageCredentials = false): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => 'VA Sam']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->can_manage_credentials = $canManageCredentials;
        $va->save();

        return $va;
    }

    public function test_super_can_add_a_credential_for_the_selected_owner(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->post('/admin/credentials', [
                'software_name' => 'GoHighLevel',
                'login_url'     => 'app.gohighlevel.com',
                'username'      => 'alin',
                'email'         => 'alin@crm.com',
                'password'      => 'sup3r-secret',
                'notes'         => '2FA: 555-1234',
            ])->assertRedirect();

        $cred = BusinessOwnerCredential::where('client_id', $this->bo->id)->firstOrFail();
        $this->assertSame('GoHighLevel', $cred->software_name);
        $this->assertSame('sup3r-secret', $cred->password);
        $this->assertSame('app.gohighlevel.com', $cred->login_url);
    }

    public function test_a_link_only_resource_needs_no_login(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->post('/admin/credentials', [
                'software_name' => 'Intake Google Sheet',
                'login_url'     => 'https://docs.google.com/spreadsheets/d/abc',
            ])->assertRedirect();

        $cred = BusinessOwnerCredential::where('software_name', 'Intake Google Sheet')->firstOrFail();
        $this->assertNull($cred->password);
        $this->assertNull($cred->email);
    }

    public function test_name_is_required(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->post('/admin/credentials', ['software_name' => '', 'login_url' => 'https://x.com'])
            ->assertSessionHasErrors('software_name');

        $this->assertSame(0, BusinessOwnerCredential::count());
    }

    public function test_super_can_edit_and_delete(): void
    {
        $this->seedWorld();
        $cred = $this->bo->credentials()->create(['software_name' => 'Old CRM', 'password' => 'x']);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put("/admin/credentials/{$cred->id}", ['software_name' => 'New CRM', 'password' => 'y'])
            ->assertRedirect();
        $this->assertSame('New CRM', $cred->fresh()->software_name);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->delete("/admin/credentials/{$cred->id}")
            ->assertRedirect();
        $this->assertSame(0, BusinessOwnerCredential::count());
    }

    public function test_a_credential_of_another_owner_is_not_reachable(): void
    {
        $this->seedWorld();
        $other = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Benny', 'email' => 'benny@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
        $cred = $other->credentials()->create(['software_name' => 'Benny CRM']);

        // Selected owner is $this->bo, so Benny's credential must 404.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->delete("/admin/credentials/{$cred->id}")
            ->assertNotFound();
        $this->assertSame(1, BusinessOwnerCredential::count());
    }

    public function test_va_without_access_is_forbidden_but_with_access_can_view(): void
    {
        $this->seedWorld();

        $blocked = $this->va(false);
        $this->actingAs($blocked, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->get('/admin/credentials')->assertForbidden();

        $granted = $this->va(true);
        $this->actingAs($granted, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->get('/admin/credentials')->assertOk()->assertSee('Credentials');
    }

    public function test_super_toggles_a_vas_access(): void
    {
        $this->seedWorld();
        $va = $this->va(false);

        $this->actingAs($this->super, 'admin')
            ->put("/admin/users/{$va->id}/credentials-access")
            ->assertRedirect();
        $this->assertTrue((bool) $va->fresh()->can_manage_credentials);

        $this->actingAs($this->super, 'admin')
            ->put("/admin/users/{$va->id}/credentials-access")
            ->assertRedirect();
        $this->assertFalse((bool) $va->fresh()->can_manage_credentials);
    }

    public function test_a_va_cannot_reach_the_toggle(): void
    {
        $this->seedWorld();
        $va = $this->va(true);
        $other = $this->va(false);

        $this->actingAs($va, 'admin')
            ->put("/admin/users/{$other->id}/credentials-access")
            ->assertForbidden();
    }
}
