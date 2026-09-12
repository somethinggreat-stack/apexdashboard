<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMentionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function va(string $name = 'VA Sam'): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    private function group(array $members): Conversation
    {
        $id = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Team', 'members' => collect($members)->pluck('id')->all(),
        ])->json('conversation_id');

        return Conversation::find($id);
    }

    public function test_mentioning_a_member_records_it_and_flags_the_recipient(): void
    {
        $abid = $this->va('Abid Hussain');
        $conv = $this->group([$abid]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'hey @Abid Hussain please review',
            'mentions' => [(string) $abid->id],
        ])->assertOk()->assertJsonPath('message.mentionsMe', false);   // I'm the sender

        $m = TeamMessage::where('type', 'text')->latest('id')->first();
        $this->assertDatabaseHas('message_mentions', ['team_message_id' => $m->id, 'admin_id' => $abid->id]);

        // Abid sees it flagged as mentioning him in his thread poll.
        $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/thread?c=' . $conv->id . '&after=0')
            ->assertOk()->assertJsonPath('messages.' . ($this->lastMsgIndex($conv, $abid)) . '.mentionsMe', true);
    }

    private function lastMsgIndex(Conversation $c, Admin $as): int
    {
        // Helper: the text message is the last item in the poll (after the "created" system msg).
        $json = $this->actingAs($as, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0')->json('messages');
        return count($json) - 1;
    }

    public function test_at_everyone_mentions_all_members(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => '@everyone standup now', 'mentions' => ['everyone'],
        ])->assertOk();

        $m = TeamMessage::where('type', 'text')->latest('id')->first();
        $this->assertTrue($m->fresh()->mentions_all);
    }

    public function test_unread_mentions_show_a_badge_on_the_chat(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);

        // Super mentions Abid.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'ping @Abid', 'mentions' => [(string) $abid->id],
        ])->assertOk();

        // Abid's sidebar shows a mention count for that group.
        $this->actingAs($abid, 'admin')->get('/admin/team-messages')->assertOk()
            ->assertViewHas('chats', function ($chats) use ($conv) {
                foreach ($chats as $it) if (($it['conversation_id'] ?? null) === $conv->id) return ($it['mentions'] ?? 0) === 1;
                return false;
            });
    }

    public function test_notify_level_can_be_set(): void
    {
        $abid = $this->va();
        $conv = $this->group([$abid]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/notify', [
            'conversation_id' => $conv->id, 'level' => 'mentions',
        ])->assertOk()->assertJson(['level' => 'mentions']);

        $this->assertSame('mentions', $conv->participants()->where('admin_id', $this->super->id)->first()->notify_level);
    }

    public function test_notifications_respect_the_level_and_flag_mentions(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);

        // Abid sets this group to "mentions only".
        $this->actingAs($abid, 'admin')->postJson('/admin/team-messages/notify', ['conversation_id' => $conv->id, 'level' => 'mentions'])->assertOk();

        // A plain message from super — Abid should NOT be notified.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $conv->id, 'body' => 'plain hello'])->assertOk();
        $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonCount(0, 'messages');

        // A message that @mentions Abid — he SHOULD be notified, flagged as a mention.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'urgent @Abid', 'mentions' => [(string) $abid->id],
        ])->assertOk();
        $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.mention', true);
    }
}
