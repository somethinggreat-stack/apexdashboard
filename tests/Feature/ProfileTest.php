<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role, string $email = 'me@test.com'): Admin
    {
        $a = new Admin(['email' => $email, 'password' => 'current-pass-1', 'full_name' => 'Me']);
        $a->role = $role;
        $a->save();

        return $a;
    }

    public function test_every_role_can_open_their_profile(): void
    {
        foreach (['super', 'va', 'leads'] as $i => $role) {
            $a = $this->admin($role, "u{$i}@test.com");
            $this->actingAs($a, 'admin')->get('/admin/profile')->assertOk()->assertSee('My Profile');
        }
    }

    public function test_can_update_name_and_email(): void
    {
        $a = $this->admin('va');

        $this->actingAs($a, 'admin')->put('/admin/profile', [
            'full_name' => 'New Name', 'email' => 'new@test.com',
        ])->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame('New Name', $a->full_name);
        $this->assertSame('new@test.com', $a->email);
    }

    public function test_password_change_requires_correct_current_and_confirmation(): void
    {
        $a = $this->admin('va');

        // Wrong current password → rejected, password unchanged.
        $this->actingAs($a, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'me@test.com',
            'current_password' => 'wrong-pass', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('current-pass-1', $a->refresh()->password));

        // Mismatched confirmation → rejected.
        $this->actingAs($a, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'me@test.com',
            'current_password' => 'current-pass-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('current-pass-1', $a->refresh()->password));

        // Correct current + matching confirmation → changed.
        $this->actingAs($a, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'me@test.com',
            'current_password' => 'current-pass-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass', $a->refresh()->password));
    }

    public function test_cannot_take_another_admins_email(): void
    {
        $this->admin('super', 'taken@test.com');
        $me = $this->admin('va', 'me@test.com');

        $this->actingAs($me, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'taken@test.com',
        ])->assertSessionHasErrors('email');

        // Keeping my own email is fine.
        $this->actingAs($me, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'me@test.com',
        ])->assertSessionHasNoErrors();
    }

    public function test_role_cannot_be_changed_from_profile(): void
    {
        $a = $this->admin('va');

        $this->actingAs($a, 'admin')->put('/admin/profile', [
            'full_name' => 'Me', 'email' => 'me@test.com', 'role' => 'super',
        ])->assertSessionHasNoErrors();

        $this->assertSame('va', $a->refresh()->role);
    }
}
