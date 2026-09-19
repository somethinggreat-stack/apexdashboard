<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Team Chat is on Pakistan time, always — the team is in Pakistan, and a timestamp that
 * depends on who is reading it is worse than no timestamp at all.
 *
 * Every instant here is chosen so Pakistan and the old America/New_York setting fall on
 * DIFFERENT DAYS, so these tests fail loudly if the timezone is ever changed back or a new
 * time is rendered without going through the chat's own timezone.
 */
class TeamChatTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-09-19 20:00 UTC = Sep 20, 1:00 AM in Pakistan, but still Sep 19, 4:00 PM in New York. */
    private const INSTANT = '2026-09-19 20:00:00';

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');   // chat is desktop-app only
        Carbon::setTestNow(self::INSTANT);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function va(string $name = 'VA Sam'): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    private function dm(Admin $a, Admin $b): Conversation
    {
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $a->id]);
        $c->participants()->createMany([
            ['admin_id' => $a->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $b->id, 'role' => 'member', 'joined_at' => now()],
        ]);

        return $c;
    }

    public function test_a_message_is_stamped_in_pakistan_time(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);

        $at = $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['conversation_id' => $c->id, 'body' => 'hello'])
            ->assertOk()->json('message.at');

        $this->assertSame('Sep 20 · 1:00 AM', $at);
        $this->assertStringNotContainsString('Sep 19', $at);   // what New York would have said
    }

    public function test_the_day_separator_uses_the_pakistan_calendar(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'hi']);

        $open = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/open?c=' . $c->id)->assertOk();

        // It is already Sep 20 in Pakistan, so the message belongs to "Today" — in New York it
        // would still be Sep 19 and this would read as yesterday's date.
        $this->assertSame('2026-09-20', $open->json('messages.0.dayKey'));
        $this->assertSame('Today', $open->json('messages.0.day'));
    }

    public function test_a_shared_file_is_stamped_in_pakistan_time(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'file']);
        $m->attachments()->create([
            'disk_path' => 'x/1.pdf', 'original_name' => 'Report.pdf',
            'mime' => 'application/pdf', 'size' => 10,
        ]);

        $file = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/gallery?c=' . $c->id)->assertOk()->json('files.0');

        $this->assertSame('Sep 20, 2026', $file['at']);
        $this->assertSame('1:00 AM', $file['time']);
    }

    public function test_the_page_tells_the_browser_to_use_pakistan_time(): void
    {
        $va = $this->va();
        $this->dm($va, $this->super);

        // The browser formats two things itself (the just-sent time and the Photos tab date
        // headings). It must be told the zone rather than falling back to the PC's clock.
        $response = $this->actingAs($this->super, 'admin')->get('/admin/team-messages')->assertOk();

        $this->assertSame('Asia/Karachi', $response->viewData('tz'));
        // @js escapes the slash, so compare against the unescaped page.
        $page = str_replace('\\/', '/', $response->getContent());
        $this->assertStringContainsString("var TZ = 'Asia/Karachi'", $page);
    }

    public function test_the_sidebar_time_uses_the_pakistan_calendar(): void
    {
        $va = $this->va('Abid');
        $c  = $this->dm($va, $this->super);
        // Send properly, so the conversation's last_message pointer is set and the row has a preview.
        $this->actingAs($va, 'admin')
            ->postJson('/admin/team-messages', ['conversation_id' => $c->id, 'body' => 'hi'])->assertOk();

        $chats = $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()->viewData('chats');

        $row = collect($chats)->firstWhere('name', 'Abid');
        // Same Pakistan day as "now", so the row shows a clock time rather than a date.
        $this->assertSame('1:00 AM', $row['preview']['at']);
    }
}
