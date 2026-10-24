<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminSignIn;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Authenticator-app (TOTP) sign-in for staff. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function on(array $roles = ['super']): void
    {
        config(['team.two_factor.enabled' => true, 'team.two_factor.required_roles' => $roles, 'session.driver' => 'array']);
    }

    private function admin(string $role = 'super', bool $enrolled = false, string $email = null): Admin
    {
        $a = new Admin(['email' => $email ?? $role . '@test.com', 'password' => 'a-long-password-1', 'full_name' => ucfirst($role)]);
        $a->role = $role;
        $a->save();

        if ($enrolled) {
            $a->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        }

        return $a;
    }

    private function codeFor(Admin $a, int $offset = 0): string
    {
        return Totp::codeAt($a->fresh()->two_factor_secret, Totp::stepFor() + $offset);
    }

    private function pending(Admin $a, bool $chat = false): array
    {
        return [AdminSignIn::PENDING => ['id' => $a->id, 'remember' => false, 'chat' => $chat, 'at' => now()->timestamp]];
    }

    // ---- the code generator itself ----

    public function test_totp_matches_the_rfc_6238_test_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');   // the RFC's SHA-1 test key
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);

        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471',
                  1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, Totp::codeAt($secret, Totp::stepFor($time), 8), "time {$time}");
        }
    }

    public function test_base32_round_trips_and_codes_are_checked_with_a_window_and_no_replay(): void
    {
        $raw = random_bytes(20);
        $this->assertSame($raw, Totp::base32Decode(Totp::base32Encode($raw)));

        $secret = Totp::generateSecret();
        $now    = Totp::stepFor(1_700_000_000);
        $code   = fn (int $off) => Totp::codeAt($secret, $now + $off);

        $this->assertSame($now, Totp::verify($secret, $code(0), null, 1, 1_700_000_000));
        $this->assertSame($now - 1, Totp::verify($secret, $code(-1), null, 1, 1_700_000_000), 'one step of clock drift is allowed');
        $this->assertNull(Totp::verify($secret, $code(3), null, 1, 1_700_000_000), 'far-future code is refused');
        $this->assertNull(Totp::verify($secret, $code(0), $now, 1, 1_700_000_000), 'the same step can not be used twice');
        $this->assertNull(Totp::verify($secret, 'abcdef', null, 1, 1_700_000_000));
        $this->assertNull(Totp::verify($secret, '12345', null, 1, 1_700_000_000));
    }

    // ---- signing in ----

    public function test_an_enrolled_account_is_not_signed_in_until_the_code_is_given(): void
    {
        $this->on();
        $a = $this->admin('super', true);

        $this->post('/admin/login', ['email' => $a->email, 'password' => 'a-long-password-1'])
            ->assertRedirect(route('admin.two-factor.challenge'))
            ->assertSessionHas(AdminSignIn::PENDING);

        $this->assertGuest('admin');
    }

    public function test_the_right_code_signs_in_and_a_wrong_one_does_not(): void
    {
        $this->on();
        $a = $this->admin('super', true);

        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');

        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['code' => $this->codeFor($a)])
            ->assertRedirect();
        $this->assertAuthenticatedAs($a->fresh(), 'admin');
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->on();
        $a    = $this->admin('super', true);
        $code = $this->codeFor($a);

        $this->withSession($this->pending($a))->post('/admin/two-factor/challenge', ['code' => $code])->assertRedirect();
        auth('admin')->logout();

        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_five_wrong_codes_lock_the_challenge_even_for_the_right_code(): void
    {
        $this->on();
        $a = $this->admin('super', true);

        foreach (range(1, 5) as $i) {
            $this->withSession($this->pending($a))->post('/admin/two-factor/challenge', ['code' => '111111']);
        }

        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['code' => $this->codeFor($a)])
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_a_stale_pending_sign_in_is_refused(): void
    {
        $this->on();
        $a = $this->admin('super', true);
        $old = [AdminSignIn::PENDING => ['id' => $a->id, 'remember' => false, 'chat' => false, 'at' => now()->subMinutes(30)->timestamp]];

        $this->withSession($old)->post('/admin/two-factor/challenge', ['code' => $this->codeFor($a)])
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_recovery_codes_work_once(): void
    {
        $this->on();
        $a     = $this->admin('super', true);
        $codes = $a->issueRecoveryCodes();
        $this->assertCount(10, $codes);

        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['recovery_code' => $codes[0]])->assertRedirect();
        $this->assertAuthenticatedAs($a->fresh(), 'admin');
        $this->assertSame(9, $a->fresh()->recoveryCodesLeft());

        auth('admin')->logout();
        $this->withSession($this->pending($a))
            ->post('/admin/two-factor/challenge', ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest('admin');
    }

    public function test_the_team_chat_sign_in_asks_for_the_code_too(): void
    {
        $this->on();
        $a = $this->admin('va', true);

        $this->withHeaders(['User-Agent' => 'ApexDesktop/1.0'])
            ->post('/admin/chat-login', ['email' => $a->email, 'password' => 'a-long-password-1'])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('admin');

        $this->withSession($this->pending($a, true))
            ->post('/admin/two-factor/challenge', ['code' => $this->codeFor($a)])
            ->assertRedirect(route('admin.team-messages.index', ['standalone' => 1]));
    }

    public function test_a_session_without_a_passed_code_is_signed_out_and_asked_for_it(): void
    {
        $this->on();
        $a = $this->admin('super', true);

        // e.g. a "remember me" cookie, or a session that began before they enrolled.
        $this->actingAs($a, 'admin')->get('/admin/profile')->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('admin');
    }

    // ---- who is forced to enrol ----

    public function test_a_super_admin_without_2fa_is_sent_to_set_it_up_and_cannot_skip_it(): void
    {
        $this->on(['super']);
        $a = $this->admin('super');

        $this->actingAs($a, 'admin')->get('/admin/profile')->assertRedirect(route('admin.two-factor.setup'));
        $this->actingAs($a, 'admin')->get('/admin/two-factor/setup')->assertOk()->assertSee('<svg', false);
        $this->actingAs($a, 'admin')->post('/admin/logout')->assertRedirect();   // signing out is always possible
    }

    public function test_other_roles_are_not_forced_until_they_are_added_to_the_list(): void
    {
        $this->on(['super']);
        $va = $this->admin('va');
        $this->actingAs($va, 'admin')->get('/admin/profile')->assertOk();

        $this->on(['super', 'va']);
        $this->actingAs($va, 'admin')->get('/admin/profile')->assertRedirect(route('admin.two-factor.setup'));
    }

    public function test_enrolling_with_a_valid_code_turns_it_on_and_shows_recovery_codes_once(): void
    {
        $this->on();
        $a = $this->admin('super');

        $this->actingAs($a, 'admin')->get('/admin/two-factor/setup')->assertOk();
        $a = $a->fresh();
        $this->assertNotNull($a->two_factor_secret);
        $this->assertFalse($a->hasTwoFactor(), 'not on until a code proves the app has the secret');

        $this->actingAs($a, 'admin')->post('/admin/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($a->fresh()->hasTwoFactor());

        $this->actingAs($a, 'admin')->post('/admin/two-factor/setup', ['code' => Totp::codeAt($a->two_factor_secret, Totp::stepFor())])
            ->assertRedirect(route('admin.two-factor.recovery'));

        $a = $a->fresh();
        $this->assertTrue($a->hasTwoFactor());
        $this->assertSame(10, $a->recoveryCodesLeft());
        $this->assertNotSame($a->getRawOriginal('two_factor_secret'), $a->two_factor_secret, 'secret is encrypted at rest');
    }

    public function test_required_roles_cannot_turn_it_off_but_others_can_with_password_and_code(): void
    {
        $this->on(['super']);
        $super = $this->admin('super', true);
        $va    = $this->admin('va', true);

        $this->withSession([AdminSignIn::PASSED => 1])->actingAs($super, 'admin')
            ->post('/admin/two-factor/disable', ['password' => 'a-long-password-1', 'code' => $this->codeFor($super)])
            ->assertForbidden();
        $this->assertTrue($super->fresh()->hasTwoFactor());

        $this->withSession([AdminSignIn::PASSED => 1])->actingAs($va, 'admin')
            ->post('/admin/two-factor/disable', ['password' => 'wrong-password', 'code' => $this->codeFor($va)])
            ->assertSessionHasErrors('password');
        $this->assertTrue($va->fresh()->hasTwoFactor());

        $this->withSession([AdminSignIn::PASSED => 1])->actingAs($va, 'admin')
            ->post('/admin/two-factor/disable', ['password' => 'a-long-password-1', 'code' => $this->codeFor($va)])
            ->assertRedirect(route('admin.two-factor.manage'));
        $this->assertFalse($va->fresh()->hasTwoFactor());
    }

    // ---- escape hatches ----

    public function test_the_emergency_switch_turns_everything_off(): void
    {
        config(['team.two_factor.enabled' => false, 'team.two_factor.required_roles' => ['super'], 'session.driver' => 'array']);
        $enrolled = $this->admin('super', true);

        $this->post('/admin/login', ['email' => $enrolled->email, 'password' => 'a-long-password-1'])
            ->assertSessionMissing(AdminSignIn::PENDING);
        $this->assertAuthenticatedAs($enrolled, 'admin');

        auth('admin')->logout();
        $plain = $this->admin('super', false, 'second@test.com');
        $this->actingAs($plain, 'admin')->get('/admin/profile')->assertOk();
    }

    public function test_the_super_admin_can_clear_a_colleagues_authenticator_with_their_own_password(): void
    {
        config(['team.two_factor.enabled' => false, 'session.driver' => 'array']);
        $super = $this->admin('super');
        $va    = $this->admin('va', true);
        $va->forceFill(['parent_admin_id' => $super->id])->save();

        $this->actingAs($super, 'admin')->post("/admin/users/{$va->id}/two-factor-reset", ['confirm_password' => 'nope'])
            ->assertSessionHasErrors('user');
        $this->assertTrue($va->fresh()->hasTwoFactor());

        $this->actingAs($super, 'admin')->post("/admin/users/{$va->id}/two-factor-reset", ['confirm_password' => 'a-long-password-1'])
            ->assertSessionHas('status');
        $this->assertFalse($va->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('activity_logs', ['admin_id' => $super->id, 'action' => 'two_factor_reset']);
    }

    public function test_secrets_never_appear_in_serialised_output(): void
    {
        $a = $this->admin('super', true);
        $a->issueRecoveryCodes();

        $json = json_encode($a->fresh()->toArray());
        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }
}
