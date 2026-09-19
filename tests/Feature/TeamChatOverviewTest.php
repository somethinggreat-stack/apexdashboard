<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The owner's chat overview. Two promises are made to the team and both are tested here:
 * the owner sees WHO talked to whom but never WHAT was said, and opening the page leaves no
 * trace anyone could notice from their own screen.
 */
class TeamChatOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');   // chat is desktop-app only
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

    private function conv(string $type, array $members, ?string $name = null, ?string $dmKey = null): Conversation
    {
        $c = Conversation::create([
            'type' => $type, 'name' => $name, 'dm_key' => $dmKey,
            'data_owner_id' => $this->super->id, 'created_by' => $members[0]->id,
        ]);
        foreach ($members as $m) {
            $c->participants()->create(['admin_id' => $m->id, 'role' => 'member', 'joined_at' => now()]);
        }

        return $c;
    }

    private function say(Conversation $c, Admin $who, string $body): TeamMessage
    {
        $m = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $who->id, 'body' => $body]);
        $c->forceFill(['last_message_id' => $m->id, 'last_message_at' => $m->created_at])->save();

        return $m;
    }

    public function test_only_the_super_admin_can_open_it(): void
    {
        $va = $this->va('Abid');

        $this->actingAs($va, 'admin')->get('/admin/team-messages/overview')->assertForbidden();
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')->assertOk();
    }

    public function test_a_vas_chat_page_does_not_even_mention_the_overview(): void
    {
        $va = $this->va('Abid');
        $this->conv('dm', [$va, $this->super], null, 'dm:1-2');

        // Not hidden with CSS — the link is never rendered into a VA's page at all.
        $this->actingAs($va, 'admin')->get('/admin/team-messages')
            ->assertOk()->assertDontSee('team-messages/overview', false)->assertDontSee('Chat overview');

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()->assertSee('Chat overview');
    }

    public function test_it_shows_a_group_the_owner_is_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $secret = $this->conv('group', [$abid, $sam], 'VAs only');
        $this->say($secret, $abid, 'something');

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')
            ->assertOk()
            ->assertSee('VAs only')
            ->assertSee("You're not in this group", false);
    }

    public function test_it_never_shows_what_anyone_said(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $dm = $this->conv('dm', [$abid, $sam], null, 'dm:' . $abid->id . '-' . $sam->id);
        $this->say($dm, $abid, 'SECRET-MESSAGE-BODY');
        $group = $this->conv('group', [$abid, $sam], 'Work');
        $this->say($group, $sam, 'ANOTHER-SECRET-BODY');

        $page = $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')->assertOk();

        $page->assertDontSee('SECRET-MESSAGE-BODY')->assertDontSee('ANOTHER-SECRET-BODY');
        // It does show that the conversation happened, and between whom.
        $page->assertSee('Abid')->assertSee('Sam');
    }

    public function test_private_notes_to_self_are_not_listed(): void
    {
        $abid = $this->va('Abid');
        $notes = $this->conv('dm', [$abid], null, 'self:' . $abid->id);
        $this->say($notes, $abid, 'my private note');

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')
            ->assertOk()->assertDontSee('my private note');

        // The notes thread contributes no "direct chat" row of its own — DM rows are the ones
        // joined by the ↔ separator, and there should be none at all here.
        $html = $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')->getContent();
        $this->assertSame(0, preg_match_all('/↔/u', $html));
        $this->assertStringContainsString('No direct chats yet.', $html);
    }

    public function test_opening_it_changes_nothing_a_va_could_notice(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $group = $this->conv('group', [$abid, $sam], 'VAs only');
        $this->say($group, $abid, 'hello');
        $dm = $this->conv('dm', [$abid, $sam], null, 'dm:' . $abid->id . '-' . $sam->id);
        $this->say($dm, $sam, 'hi');

        $before = $this->visibleState();

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')->assertOk();

        // Nothing the team can see from their own screens moved: no new member appeared in a
        // group, no read watermark advanced, no system message was written, nothing was typed.
        $this->assertSame($before, $this->visibleState());
    }

    /** Everything about the chat that a VA could actually observe. */
    private function visibleState(): array
    {
        return [
            'participants' => DB::table('conversation_participants')
                ->orderBy('id')->get(['id', 'conversation_id', 'admin_id', 'role', 'last_read_message_id', 'typing_at', 'muted', 'favorite'])
                ->map(fn ($r) => (array) $r)->all(),
            'messages' => DB::table('team_messages')->orderBy('id')
                ->get(['id', 'conversation_id', 'type', 'sender_id', 'body', 'deleted_at', 'pinned_at'])
                ->map(fn ($r) => (array) $r)->all(),
            'conversations' => DB::table('conversations')->orderBy('id')
                ->get(['id', 'name', 'last_message_id'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_it_only_covers_my_own_org(): void
    {
        $otherOwner = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other Owner']);
        $otherOwner->role = 'super';
        $otherOwner->save();

        $theirs = Conversation::create(['type' => 'group', 'name' => 'Other Org Group', 'data_owner_id' => $otherOwner->id, 'created_by' => $otherOwner->id]);
        $theirs->participants()->create(['admin_id' => $otherOwner->id, 'role' => 'member', 'joined_at' => now()]);

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/overview')
            ->assertOk()->assertDontSee('Other Org Group');
    }

    public function test_group_changes_are_written_to_the_activity_log(): void
    {
        $abid = $this->va('Abid');

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Ops', 'members' => [$abid->id],
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $this->super->id,
            'action'   => 'admin.team-messages.group.store',
        ]);
    }

    public function test_sending_a_message_is_not_written_to_the_activity_log(): void
    {
        $abid = $this->va('Abid');
        $c = $this->conv('dm', [$abid, $this->super], null, 'dm:' . min($abid->id, $this->super->id) . '-' . max($abid->id, $this->super->id));

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['conversation_id' => $c->id, 'body' => 'hi'])->assertOk();

        // Logging every message would bury the client and document actions the log exists for.
        $this->assertDatabaseMissing('activity_logs', ['action' => 'admin.team-messages.store']);
    }
}
