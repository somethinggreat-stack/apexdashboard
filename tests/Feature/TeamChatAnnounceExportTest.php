<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Steps 3 and 4 of the owner's controls: saying one thing to everyone and knowing who read it,
 * and keeping records before the 7-day purge takes them.
 *
 * The same line as step 2 holds here: the owner may save a copy of a conversation they are IN,
 * and may see that a file exists anywhere — but exporting a chat they are not part of would be
 * reading it through the back door, and is refused.
 */
class TeamChatAnnounceExportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function va(string $name): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    private function group(array $members, string $name = 'VAs only'): Conversation
    {
        $c = Conversation::create(['type' => 'group', 'name' => $name, 'data_owner_id' => $this->super->id, 'created_by' => $members[0]->id]);
        foreach ($members as $i => $m) {
            $c->participants()->create(['admin_id' => $m->id, 'role' => $i === 0 ? 'admin' : 'member', 'joined_at' => now()]);
        }

        return $c;
    }

    // ---- step 3: announcements ----------------------------------------------------------

    public function test_one_announcement_reaches_every_teammate(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/announce', ['body' => 'Shift starts at 9 tonight'])
            ->assertOk()->assertJsonPath('sent', 2);

        // A copy in each person's own chat with the owner, so it arrives the normal way —
        // unread badge, notification and all.
        foreach ([$abid, $sam] as $mate) {
            $conv = Conversation::where('type', 'dm')
                ->whereHas('participants', fn ($q) => $q->where('admin_id', $mate->id))
                ->whereHas('participants', fn ($q) => $q->where('admin_id', $this->super->id))
                ->first();
            $this->assertNotNull($conv, 'no direct chat for ' . $mate->full_name);
            $this->assertDatabaseHas('team_messages', [
                'conversation_id' => $conv->id,
                'sender_id'       => $this->super->id,
                'body'            => 'Shift starts at 9 tonight',
            ]);
        }

        // All copies share one announcement id, which is how "who has read it" is worked out.
        $ids = TeamMessage::whereNotNull('announcement_id')->pluck('announcement_id')->unique();
        $this->assertCount(1, $ids);
    }

    public function test_a_va_cannot_announce(): void
    {
        $abid = $this->va('Abid');

        $this->actingAs($abid, 'admin')
            ->postJson('/admin/team-messages/announce', ['body' => 'everyone listen to me'])
            ->assertForbidden();
    }

    public function test_the_overview_says_who_has_read_an_announcement(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/announce', ['body' => 'Please read this'])->assertOk();

        // Abid opens his chat with the owner; Sam does not.
        $abidConv = Conversation::where('type', 'dm')
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $abid->id))->first();
        $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/thread?c=' . $abidConv->id . '&after=0')->assertOk();

        $announcements = $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages/overview')->assertOk()->viewData('announcements');

        $this->assertCount(1, $announcements);
        $this->assertSame(['Abid'], $announcements[0]['read']);
        $this->assertSame(['Sam'], $announcements[0]['unread']);
    }

    // ---- step 4: files + export ----------------------------------------------------------

    public function test_the_files_page_lists_files_from_chats_the_owner_is_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $g = $this->group([$abid, $sam]);
        $m = TeamMessage::create(['conversation_id' => $g->id, 'type' => 'text', 'sender_id' => $abid->id, 'body' => 'SECRET-TALK']);
        $m->attachments()->create(['disk_path' => 'x/1.zip', 'original_name' => 'client-ssn.zip', 'mime' => 'application/zip', 'size' => 2048]);

        $page = $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview/files')->assertOk();

        $page->assertSee('client-ssn.zip')->assertSee('VAs only');
        // Files, not conversations: what was actually said stays private.
        $page->assertDontSee('SECRET-TALK');
    }

    public function test_a_va_cannot_open_the_files_page(): void
    {
        $this->actingAs($this->va('Abid'), 'admin')
            ->get('/admin/team-messages/overview/files')->assertForbidden();
    }

    public function test_the_owner_can_save_a_copy_of_a_chat_they_are_in(): void
    {
        $abid = $this->va('Abid');
        $g = $this->group([$this->super, $abid], 'Ops');
        TeamMessage::create(['conversation_id' => $g->id, 'type' => 'text', 'sender_id' => $abid->id, 'body' => 'kept for the record']);

        $res = $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages/overview/export/' . $g->id)->assertOk();

        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('kept for the record', $body);
        $this->assertStringContainsString('Abid', $body);
        $this->assertStringContainsString('Time (Pakistan)', $body);
    }

    public function test_the_owner_cannot_export_a_chat_they_are_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $g = $this->group([$abid, $sam]);
        TeamMessage::create(['conversation_id' => $g->id, 'type' => 'text', 'sender_id' => $abid->id, 'body' => 'private']);

        // Exporting would be reading it through the back door. Join first — visibly.
        $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages/overview/export/' . $g->id)->assertForbidden();
    }

    public function test_after_joining_the_owner_can_export_that_group(): void
    {
        $abid = $this->va('Abid');
        $g = $this->group([$abid, $this->va('Sam')]);
        TeamMessage::create(['conversation_id' => $g->id, 'type' => 'text', 'sender_id' => $abid->id, 'body' => 'now on the record']);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group/' . $g->id . '/join')->assertOk();

        $res = $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages/overview/export/' . $g->id)->assertOk();
        $this->assertStringContainsString('now on the record', $res->streamedContent());
    }

    public function test_export_is_scoped_to_my_own_org(): void
    {
        $otherOwner = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other']);
        $otherOwner->role = 'super';
        $otherOwner->save();

        $c = Conversation::create(['type' => 'group', 'name' => 'Theirs', 'data_owner_id' => $otherOwner->id, 'created_by' => $otherOwner->id]);
        $c->participants()->create(['admin_id' => $otherOwner->id, 'role' => 'admin', 'joined_at' => now()]);

        $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages/overview/export/' . $c->id)->assertForbidden();
    }
}
