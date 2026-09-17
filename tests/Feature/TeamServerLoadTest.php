<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Stop spending server, PHP and database work on requests that achieve nothing:
 * polls that are redirected or refused, a favicon that renders the whole chat page,
 * and images/avatars that could never be cached.
 */
class TeamServerLoadTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.chat_host' => 'chat.apexgrowthsolution.com']);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    /** Page HTML with JSON-escaped slashes normalised, so a path can be looked for. */
    private function html($response): string
    {
        return str_replace('\/', '/', $response->getContent());
    }

    private function va(string $name = 'VA Sam'): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    public function test_the_chat_window_does_not_run_the_new_clients_poll(): void
    {
        $va = $this->va();
        $poll = '/admin/new-clients-poll';   // path only: absolute URLs differ per request host

        // In the desktop app (standalone chat) that poll is redirected by the chat-host guard,
        // and the server ends up rendering the whole chat page for nothing.
        $chat = $this->html($this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages?standalone=1')->assertOk());
        $this->assertStringNotContainsString($poll, $chat);

        // The dashboard still gets it — that is where new-client alerts are useful.
        $dash = $this->html($this->actingAs($va, 'admin')->get('/admin/select-business-owner')->assertOk());
        $this->assertStringContainsString($poll, $dash);
    }

    public function test_a_plain_browser_dashboard_does_not_poll_the_chat(): void
    {
        $va = $this->va();
        $notifications = '/admin/team-messages/notifications';

        // In a browser every chat poll is refused (the chat is desktop-app only), so the page
        // must not start the poller at all.
        $browser = $this->html($this->actingAs($va, 'admin')->get('/admin/select-business-owner')->assertOk());
        $this->assertStringNotContainsString($notifications, $browser);

        // In the desktop app it still runs.
        $app = $this->html($this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages?standalone=1')->assertOk());
        $this->assertStringContainsString($notifications, $app);
    }

    public function test_the_chat_window_asks_for_an_icon_that_exists(): void
    {
        $html = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($this->va(), 'admin')
            ->get('/admin/team-messages?standalone=1')->assertOk()->getContent();

        $this->assertStringNotContainsString('/favicon.ico', $html);
        $this->assertStringContainsString('/favicon.svg', $html);
        $this->assertFileExists(public_path('favicon.svg'));
    }

    public function test_presence_writes_do_not_change_every_avatar_url(): void
    {
        Storage::fake('private');
        $va = $this->va();
        Storage::disk('private')->put('team-avatars/' . $va->id . '.jpg', 'x');
        $va->forceFill(['avatar' => 'team-avatars/' . $va->id . '.jpg'])->save();

        $before = $va->fresh()->avatarUrl();

        // A request 30s later refreshes presence (the middleware's throttle window).
        $va->forceFill(['last_seen_at' => now()->subMinute()])->saveQuietly();
        $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->getJson('/admin/team-messages/presence')->assertOk();

        $after = $va->fresh()->avatarUrl();
        $this->assertSame($before, $after, 'the avatar URL must not change just because someone is online');
        $this->assertNotNull($va->fresh()->last_seen_at);
    }

    public function test_a_shared_image_can_be_cached_by_the_browser(): void
    {
        Storage::fake('private');
        $va = $this->va();
        $conv = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $va->id]);
        $conv->participants()->createMany([
            ['admin_id' => $va->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $this->super->id, 'role' => 'member', 'joined_at' => now()],
        ]);
        $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id,
            'attachments' => [UploadedFile::fake()->image('screenshot.png', 20, 20), UploadedFile::fake()->create('notes.zip', 5)],
        ])->assertOk();

        $msg = TeamMessage::where('type', 'text')->latest('id')->firstOrFail();
        $image = $msg->attachments->firstWhere('original_name', 'screenshot.png');
        $zip = $msg->attachments->firstWhere('original_name', 'notes.zip');

        $img = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages/attachment/' . $image->id)->assertOk();
        $this->assertStringContainsString('max-age=31536000', $img->headers->get('Cache-Control'));
        // Private files must never be cached by a shared cache (Cloudflare sits in front).
        $this->assertStringContainsString('private', $img->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $img->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', $img->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $img->headers->get('X-Content-Type-Options'));

        // A download is still never cached, and still forced to download.
        $dl = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages/attachment/' . $zip->id . '?dl=1')->assertOk();
        $this->assertStringContainsString('no-store', $dl->headers->get('Cache-Control'));
    }

    public function test_an_avatar_can_be_cached_and_is_versioned_by_the_photo(): void
    {
        Storage::fake('private');
        $va = $this->va();
        Storage::disk('private')->put('team-avatars/' . $va->id . '.jpg', 'photo');
        $va->forceFill(['avatar' => 'team-avatars/' . $va->id . '.jpg'])->save();

        $res = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->actingAs($va, 'admin')
            ->get('/admin/team-messages/avatar/' . $va->id)->assertOk();

        $this->assertStringContainsString('max-age=86400', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('?v=' . Storage::disk('private')->lastModified('team-avatars/' . $va->id . '.jpg'), $va->fresh()->avatarUrl());
    }
}
