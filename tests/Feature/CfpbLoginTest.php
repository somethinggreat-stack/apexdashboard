<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfpbLoginTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo;

    private function seedWorld(): void
    {
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Gawd', 'email' => 'gawd@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
    }

    private function eu(string $name, string $email): EndUser
    {
        return EndUser::create([
            'client_id' => $this->bo->id, 'first_name' => $name, 'last_name' => 'X', 'suffix' => 'None',
            'email' => $email, 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => 'done', 'rounds' => ['1st Round'],
        ]);
    }

    private function payload(EndUser $eu, array $extra = []): array
    {
        return array_merge([
            'first_name' => $eu->first_name, 'last_name' => $eu->last_name, 'suffix' => 'None',
            'email' => $eu->email, 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'start_date' => '2026-06-01', 'status' => 'active',
        ], $extra);
    }

    public function test_entering_cfpb_stamps_and_shows_on_report(): void
    {
        $this->seedWorld();
        $eu = $this->eu('Selena Egues', 's@test.com');

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload($eu, [
                'cfpb_email' => 'selena@cfpb.com', 'cfpb_password' => 'pw12345',
            ]))->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertNotNull($eu->cfpb_logged_at);
        $this->assertSame($this->super->id, $eu->cfpb_logged_by_admin_id);

        $this->actingAs($this->super, 'admin')->get('/admin/cfpb-logins')
            ->assertOk()
            ->assertSee('Gawd')
            ->assertSee('Selena Egues')
            ->assertSee('Umair');   // the VA who entered it
    }

    public function test_plain_edit_without_cfpb_does_not_stamp(): void
    {
        $this->seedWorld();
        $eu = $this->eu('No Cfpb', 'n@test.com');

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload($eu, ['phone' => '555-0000']))
            ->assertSessionHasNoErrors();

        $this->assertNull($eu->refresh()->cfpb_logged_at);

        $this->actingAs($this->super, 'admin')->get('/admin/cfpb-logins')
            ->assertOk()->assertDontSee('No Cfpb');
    }

    public function test_per_round_cfpb_stamps_and_shows(): void
    {
        $this->seedWorld();
        $eu = $this->eu('Round Cfpb', 'r@test.com');
        $eu->forceFill(['rounds' => ['1st Round', '2nd Round']])->save();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload($eu, [
                'cfpb_rounds' => [2 => ['email' => 'r2@cfpb.com', 'password' => 'round2pw']],
            ]))->assertSessionHasNoErrors();

        $this->assertNotNull($eu->refresh()->cfpb_logged_at);

        $this->actingAs($this->super, 'admin')->get('/admin/cfpb-logins')
            ->assertOk()->assertSee('Round Cfpb');
    }

    public function test_window_is_12h(): void
    {
        $this->seedWorld();
        $recent = $this->eu('Recent Cfpb', 'rc@test.com');
        $recent->forceFill(['cfpb_logged_at' => now()->subHours(8), 'cfpb_logged_by_admin_id' => $this->super->id])->save();

        $old = $this->eu('Old Cfpb', 'o@test.com');
        $old->forceFill(['cfpb_logged_at' => now()->subHours(13), 'cfpb_logged_by_admin_id' => $this->super->id])->save();

        $this->actingAs($this->super, 'admin')->get('/admin/cfpb-logins')
            ->assertOk()
            ->assertSee('Recent Cfpb')   // 8h ago → inside 12h window
            ->assertDontSee('Old Cfpb'); // 13h ago → outside
    }

    public function test_super_and_va_can_open_but_leads_cannot(): void
    {
        $this->seedWorld();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'VA']);
        $va->role = 'va'; $va->parent_admin_id = $this->super->id; $va->save();

        $leads = new Admin(['email' => 'leads@test.com', 'password' => 'secret', 'full_name' => 'Leads']);
        $leads->role = 'leads'; $leads->parent_admin_id = $this->super->id; $leads->save();

        $this->actingAs($this->super, 'admin')->get('/admin/cfpb-logins')->assertOk();
        $this->actingAs($va, 'admin')->get('/admin/cfpb-logins')->assertOk();
        $this->actingAs($leads, 'admin')->get('/admin/cfpb-logins')->assertStatus(403);
    }
}
