<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared-PC privacy: signing out tells the browser to drop this site's stored data, so the
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

        $this->assertSame('"storage"', $res->headers->get('Clear-Site-Data'));
        $this->assertGuest('admin');
    }

    public function test_signing_out_of_the_dashboard_clears_it_too(): void
    {
        $va = $this->va();

        $res = $this->actingAs($va, 'admin')->get('/admin/logout')->assertRedirect(route('admin.login'));

        $this->assertSame('"storage"', $res->headers->get('Clear-Site-Data'));
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
}
