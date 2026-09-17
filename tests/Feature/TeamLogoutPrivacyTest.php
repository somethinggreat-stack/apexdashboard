<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared-PC privacy: signing out asks the login page to remove only the departing user's chat data, so the
 * next VA on the same computer starts with nothing of the previous one's.
 */
class TeamLogoutPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function va(string $name = 'VA Sam'): Admin
    {
        $super = Admin::firstWhere('email', 'super@test.com');
        if (! $super) {
            $super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
            $super->role = 'super';
            $super->save();
        }
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $super->id;
        $va->save();

        return $va;
    }

    public function test_signing_out_of_the_app_clears_this_browsers_stored_data(): void
    {
        $va = $this->va();

        $res = $this->withHeader('User-Agent', 'ApexDesktop/1.0')
            ->actingAs($va, 'admin')->get('/admin/logout')
            ->assertRedirect(route('admin.chat-login'));

        $this->assertNull($res->headers->get('Clear-Site-Data'));
        $res->assertSessionHas('chat_logout.uid', (string) $va->id);
        $res->assertSessionHas('chat_logout.stamp');
        $this->assertGuest('admin');
    }

    public function test_signing_out_of_the_dashboard_clears_it_too(): void
    {
        $va = $this->va();

        $res = $this->actingAs($va, 'admin')->get('/admin/logout')->assertRedirect(route('admin.login'));

        $this->assertNull($res->headers->get('Clear-Site-Data'));
        $res->assertSessionHas('chat_logout.uid', (string) $va->id);
        $res->assertSessionHas('chat_logout.stamp');
    }

    public function test_a_renewed_session_is_handed_a_new_stamp_instead_of_signing_the_va_out(): void
    {
        $va = $this->va();
        // Same person, but the stamp is from a session that has since rolled over (for example
        // "keep me signed in" re-authenticating after an idle spell).
        $res = $this->withHeaders(['User-Agent' => 'ApexDesktop/1.0', 'X-Apex-Chat-Session' => $va->id . '.stale-token'])
            ->actingAs($va, 'admin')->getJson('/admin/team-messages/notifications?prime=1')
            ->assertStatus(409)->assertJsonPath('reason', 'session-renewed');

        $this->assertNotEmpty($res->json('stamp'));
        $this->assertStringStartsWith($va->id . '.', $res->json('stamp'));

        // Sending again with the stamp it was given works — no sign-out, no interruption.
        $this->withHeaders(['User-Agent' => 'ApexDesktop/1.0', 'X-Apex-Chat-Session' => $res->json('stamp')])
            ->actingAs($va, 'admin')->getJson('/admin/team-messages/notifications?prime=1')->assertOk();
    }

    public function test_a_tab_belonging_to_another_account_is_refused(): void
    {
        $a = $this->va('Abid');
        $b = $this->va('Mujeeb');

        $this->withHeaders(['User-Agent' => 'ApexDesktop/1.0', 'X-Apex-Chat-Session' => $a->id . '.whatever'])
            ->actingAs($b, 'admin')->getJson('/admin/team-messages/notifications?prime=1')
            ->assertStatus(401)->assertJsonPath('reason', 'account-changed');
    }

    public function test_other_admin_pages_keep_their_own_caching(): void
    {
        // The chat is private and never cached, but this must not turn every admin response
        // into no-store (that would also break back/forward and future image caching).
        $va = $this->va();
        $chat = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages?standalone=1')->assertOk();
        $this->assertStringContainsString('no-store', $chat->headers->get('Cache-Control'));

        $profile = $this->actingAs($va, 'admin')->get('/admin/profile')->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $profile->headers->get('Cache-Control'));
    }

    public function test_the_chat_page_stamps_local_state_with_the_signed_in_user(): void
    {
        $a = $this->va('Abid');
        $b = $this->va('Mujeeb');

        $pageA = $this->withHeader('User-Agent', 'ApexDesktop/1.0')
            ->actingAs($a, 'admin')->get('/admin/team-messages?standalone=1')->assertOk()->getContent();
        $pageB = $this->withHeader('User-Agent', 'ApexDesktop/1.0')
            ->actingAs($b, 'admin')->get('/admin/team-messages?standalone=1')->assertOk()->getContent();

        // Saved chats, drafts and the notification keys all carry the user id.
        foreach (["'tc-drafts:u' + \"{$a->id}\"", "'tc-draft:u' + \"{$a->id}\"", "var ME_ID = \"{$a->id}\""] as $needle) {
            $this->assertStringContainsString($needle, $pageA, $needle);
        }
        $this->assertStringContainsString("var ME_ID = \"{$b->id}\"", $pageB);
        $this->assertStringNotContainsString("var ME_ID = \"{$a->id}\"", $pageB);
        // The notification list, poll position and leader lock are keyed by the same user id.
        $this->assertStringContainsString("var U = ':u' + ME_ID;", $pageB);
        $this->assertStringContainsString("'apex-team-notifs' + U", $pageB);
        $this->assertStringContainsString("'apex-team-leader' + U", $pageB);
    }

    public function test_old_tab_cannot_read_or_send_using_a_new_accounts_cookie(): void
    {
        $va = $this->va();
        $this->actingAs($va, 'admin')->withHeaders([
            'User-Agent' => 'ApexDesktop/1.0', 'X-Apex-Chat-Session' => 'former-session',
        ]);
        $this->getJson('/admin/team-messages/notifications')->assertUnauthorized();
        $this->getJson('/admin/team-messages/open?self=1')->assertUnauthorized();
        $this->postJson('/admin/team-messages', ['body' => 'must not send', 'self' => 1])->assertUnauthorized();
        $this->assertDatabaseMissing('team_messages', ['body' => 'must not send']);
    }

    public function test_notes_snapshot_expires_when_its_oldest_message_reaches_seven_days(): void
    {
        $va = $this->va();
        $this->actingAs($va, 'admin')->withHeader('User-Agent', 'ApexDesktop/1.0');
        $conv = $this->getJson('/admin/team-messages/open?self=1')->assertOk()->json('conversation_id');
        $this->postJson('/admin/team-messages', ['body' => 'private retention note', 'conversation_id' => $conv])->assertSuccessful();
        $message = \App\Models\TeamMessage::where('body', 'private retention note')->firstOrFail();
        $created = now()->subDays(6)->subHours(23)->startOfSecond();
        $message->forceFill(['created_at' => $created])->save();
        $this->getJson('/admin/team-messages/open?self=1')->assertOk()
            ->assertJsonPath('cacheExpiresAt', $created->copy()->addDays(7)->getTimestampMs());
    }

    public function test_chat_pages_are_not_stored_in_the_http_cache(): void
    {
        $res = $this->actingAs($this->va(), 'admin')->withHeader('User-Agent', 'ApexDesktop/1.0')
            ->get('/admin/team-messages?standalone=1')->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }
}
