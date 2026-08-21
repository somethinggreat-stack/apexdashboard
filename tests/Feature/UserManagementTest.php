<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Admin
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $super->role = 'super';
        $super->save();

        return $super;
    }

    public function test_super_can_add_a_va_and_a_leads_agent_with_chosen_role(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super, 'admin')->post('/admin/users', [
            'full_name' => 'New Va', 'email' => 'newva@test.com', 'password' => 'longpassword1', 'role' => 'va',
        ])->assertSessionHasNoErrors();

        $this->actingAs($super, 'admin')->post('/admin/users', [
            'full_name' => 'New Lead', 'email' => 'newlead@test.com', 'password' => 'longpassword1', 'role' => 'leads',
        ])->assertSessionHasNoErrors();

        $va = Admin::where('email', 'newva@test.com')->first();
        $lead = Admin::where('email', 'newlead@test.com')->first();

        $this->assertSame('va', $va->role);
        $this->assertSame($super->id, $va->parent_admin_id);
        $this->assertSame('leads', $lead->role);
        $this->assertSame($super->id, $lead->parent_admin_id);
    }

    public function test_cannot_mint_a_second_super_from_this_screen(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super, 'admin')->post('/admin/users', [
            'full_name' => 'Sneaky', 'email' => 'sneaky@test.com', 'password' => 'longpassword1', 'role' => 'super',
        ])->assertSessionHasErrors('role');

        $this->assertNull(Admin::where('email', 'sneaky@test.com')->first());
    }

    public function test_a_deleted_user_stays_deleted_after_team_seeder_runs(): void
    {
        Config::set('team.super', ['email' => 'super@test.com', 'password' => 'secret-pass', 'name' => 'Umair']);

        // Seed the super (as a deploy would), then add + delete a leads agent.
        (new TeamSeeder())->run();
        $super = Admin::where('email', 'super@test.com')->firstOrFail();
        $this->assertSame('super', $super->role);

        $sanwal = new Admin(['email' => 'sanwal@test.com', 'password' => 'longpassword1', 'full_name' => 'Sanwal']);
        $sanwal->role = 'leads';
        $sanwal->parent_admin_id = $super->id;
        $sanwal->save();

        $this->actingAs($super, 'admin')->delete("/admin/users/{$sanwal->id}")->assertSessionHasNoErrors();
        $this->assertNull(Admin::where('email', 'sanwal@test.com')->first());

        // A subsequent deploy re-runs the seeder — it must NOT bring Sanwal back.
        (new TeamSeeder())->run();
        $this->assertNull(Admin::where('email', 'sanwal@test.com')->first());
        $this->assertNotNull(Admin::where('email', 'super@test.com')->first());
    }

    public function test_non_super_cannot_reach_user_management(): void
    {
        $super = $this->superAdmin();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'longpassword1', 'full_name' => 'VA']);
        $va->role = 'va';
        $va->parent_admin_id = $super->id;
        $va->save();

        $this->actingAs($va, 'admin')->get('/admin/users')->assertStatus(403);
        $this->actingAs($va, 'admin')->post('/admin/users', [
            'full_name' => 'X', 'email' => 'x@test.com', 'password' => 'longpassword1', 'role' => 'va',
        ])->assertStatus(403);
    }
}
