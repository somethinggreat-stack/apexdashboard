<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMessageTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader("User-Agent", "ApexDesktop/1.0");   // chat is desktop-app only

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function va(string $name = 'VA Sam', ?int $parent = null): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $parent ?? $this->super->id;
        $va->save();

        return $va;
    }

    /** A dm conversation between two admins in my org, with a message. */
    private function dm(Admin $a, Admin $b): Conversation
    {
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $a->id]);
        $c->participants()->createMany([
            ['admin_id' => $a->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $b->id, 'role' => 'member', 'joined_at' => now()],
        ]);

        return $c;
    }

    private function msg(Conversation $c, Admin $sender, array $attr = []): TeamMessage
    {
        $m = TeamMessage::create(array_merge(
            ['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $sender->id, 'body' => 'hi'], $attr
        ));
        $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);

        return $m;
    }

    // ---------------------------------------------------------------- DMs

    public function test_a_va_can_message_the_super_admin_and_it_creates_a_dm(): void
    {
        $va = $this->va();

        $res = $this->actingAs($va, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $this->super->id, 'body' => 'Hi boss'])
            ->assertOk()->assertJson(['ok' => true]);

        $convId = $res->json('conversation_id');
        $this->assertDatabaseHas('team_messages', ['conversation_id' => $convId, 'sender_id' => $va->id, 'body' => 'Hi boss']);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $convId, 'admin_id' => $this->super->id]);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $convId, 'admin_id' => $va->id]);
    }

    public function test_messaging_the_same_person_twice_reuses_one_conversation(): void
    {
        $va = $this->va();

        $a = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['recipient_id' => $va->id, 'body' => 'one'])->json('conversation_id');
        $b = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['recipient_id' => $va->id, 'body' => 'two'])->json('conversation_id');

        $this->assertSame($a, $b);
        $this->assertSame(1, Conversation::where('type', 'dm')->count());
    }

    public function test_cannot_message_an_admin_outside_my_org(): void
    {
        $otherSuper = new Admin(['email' => 'other@test.com', 'password' => 'secret', 'full_name' => 'Other Boss']);
        $otherSuper->role = 'super';
        $otherSuper->save();
        $outsider = $this->va('Rival VA', $otherSuper->id);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $outsider->id, 'body' => 'spy'])
            ->assertNotFound();

        $this->assertSame(0, TeamMessage::count());
    }

    public function test_leads_are_not_messageable(): void
    {
        $lead = new Admin(['email' => 'lead@test.com', 'password' => 'secret', 'full_name' => 'Lead Gen']);
        $lead->role = 'leads';
        $lead->parent_admin_id = $this->super->id;
        $lead->save();

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $lead->id, 'body' => 'hi'])
            ->assertNotFound();

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()
            ->assertViewHas('teammates', fn ($t) => $t->doesntContain('id', $lead->id));
    }

    public function test_opening_a_thread_marks_incoming_read_and_shows_unread_before(): void
    {
        $va = $this->va('VA Sam');
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $va, ['body' => 'ping']);

        // Unread badge shows in the sidebar item before opening.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()
            ->assertViewHas('chats', function ($chats) use ($va) {
                foreach ($chats as $it) if (($it['peer_id'] ?? null) === $va->id) return $it['unread'] === 1;
                return false;
            });

        // Opening it marks it read.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?c=' . $c->id)
            ->assertOk()->assertSee('ping');

        $this->assertSame($m->id, $c->participants()->where('admin_id', $this->super->id)->first()->last_read_message_id);
    }

    public function test_thread_poll_returns_messages_after_an_id_with_read_watermark(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m1 = $this->msg($c, $va, ['body' => 'first']);
        $m2 = $this->msg($c, $this->super, ['body' => 'second']);

        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=' . $m1->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'second')
            ->assertJsonPath('messages.0.mine', true);
    }

    public function test_a_delete_for_me_message_no_longer_counts_as_unread(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $this->msg($c, $va, ['body' => 'one']);
        $mid = $this->msg($c, $va, ['body' => 'two'])->id;
        $this->msg($c, $va, ['body' => 'three']);

        // Baseline: 3 unread for super.
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonPath('perConv.' . $c->id, 3);

        // Super hides one for themselves → it must drop out of the unread count.
        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $mid, ['mode' => 'me'])->assertOk();

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonPath('perConv.' . $c->id, 2);
    }

    public function test_a_state_change_on_an_old_message_is_returned_via_statesSince(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $old = $this->msg($c, $this->super, ['body' => 'an old message']);

        // Someone reacts to the OLD message now. A "changed since" poll must surface it even though
        // it is not among the newest messages.
        $this->actingAs($va, 'admin')->postJson('/admin/team-messages/react', ['message_id' => $old->id, 'emoji' => '👍'])->assertOk();

        $states = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=99999&statesSince=' . urlencode('2000-01-01 00:00:00'))
            ->assertOk()->json('states');

        $ids = array_column($states, 'id');
        $this->assertContains($old->id, $ids);
        $row = collect($states)->firstWhere('id', $old->id);
        $this->assertSame('👍', $row['reactions'][0]['emoji']);
    }

    public function test_i_can_message_myself_in_a_private_notes_thread(): void
    {
        // Opening notes creates a single-participant self conversation.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?self=1')->assertOk();

        $conv = Conversation::where('dm_key', 'self:' . $this->super->id)->first();
        $this->assertNotNull($conv);
        $this->assertSame(1, $conv->participants()->count());

        // I can post a note to it, and it belongs only to me.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'remember this',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('team_messages', [
            'conversation_id' => $conv->id, 'body' => 'remember this', 'sender_id' => $this->super->id,
        ]);
    }

    public function test_the_thread_loads_only_the_newest_page_and_flags_more_older(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        for ($i = 1; $i <= 60; $i++) {
            $this->msg($c, $i % 2 ? $va : $this->super, ['body' => 'm' . $i]);
        }

        $res  = $this->actingAs($this->super, 'admin')->get('/admin/team-messages?c=' . $c->id)->assertOk();
        $view = $res->viewData('messages');

        // Only the newest 50 render initially, and the "more older" flag is set.
        $this->assertCount(50, $view);
        $this->assertSame('m11', $view->first()->body);
        $this->assertSame('m60', $view->last()->body);
        $this->assertTrue($res->viewData('hasMoreOlder'));
    }

    public function test_load_older_returns_the_previous_page_in_chronological_order(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $ids = [];
        for ($i = 1; $i <= 60; $i++) {
            $ids[$i] = $this->msg($c, $i % 2 ? $va : $this->super, ['body' => 'm' . $i])->id;
        }

        // The newest page starts at m11 (id $ids[11]); ask for everything older than it.
        $res = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/older?c=' . $c->id . '&before=' . $ids[11])
            ->assertOk();

        $bodies = array_column($res->json('messages'), 'body');
        $this->assertSame('m1', $bodies[0]);        // oldest first (chronological)
        $this->assertSame('m10', end($bodies));     // up to just before the current page
        $this->assertCount(10, $bodies);
        $this->assertFalse($res->json('hasMore'));  // nothing older than m1
        // Each row carries the day-separator metadata the client needs to prepend correctly.
        $this->assertArrayHasKey('dayKey', $res->json('messages.0'));
        $this->assertArrayHasKey('day', $res->json('messages.0'));
    }

    public function test_load_older_rejects_a_conversation_i_am_not_in(): void
    {
        $va      = $this->va();
        $outsider = new Admin(['email' => 'out@test.com', 'password' => 'secret', 'full_name' => 'Out Sider']);
        $outsider->role = 'super';
        $outsider->save();

        $c   = $this->dm($va, $this->super);
        $mid = $this->msg($c, $va, ['body' => 'private'])->id;

        $this->actingAs($outsider, 'admin')
            ->getJson('/admin/team-messages/older?c=' . $c->id . '&before=' . ($mid + 1))
            ->assertNotFound();
    }

    public function test_blue_tick_watermark_reflects_the_other_person_reading(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $this->super, ['body' => 'seen soon']);

        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=999')
            ->assertOk()->assertJsonPath('readUpTo', 0);

        // The VA opens the thread → marks it read.
        $this->actingAs($va, 'admin')->get('/admin/team-messages?c=' . $c->id)->assertOk();

        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=999')
            ->assertOk()->assertJsonPath('readUpTo', $m->id);
    }

    public function test_react_and_delete_work_within_a_conversation(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $mine   = $this->msg($c, $this->super, ['body' => 'mine']);
        $theirs = $this->msg($c, $va, ['body' => 'yours']);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $theirs->id, 'emoji' => '👍'])
            ->assertOk()->assertJsonPath('reactions.0.emoji', '👍');

        // Only the sender may delete their message.
        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $theirs->id)->assertForbidden();
        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $mine->id)->assertOk();
        $this->assertNotNull($mine->fresh()->deleted_at);
    }

    public function test_a_reaction_with_markup_is_rejected(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $va, ['body' => 'react to me']);

        // XSS attempt via the reaction value must be refused, and nothing stored.
        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '<img src=x onerror=alert(1)>'])
            ->assertStatus(422)->assertJsonValidationErrors('emoji');
        $this->assertNull($m->fresh()->reactions);

        // A real emoji still works.
        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '😍'])
            ->assertOk()->assertJsonPath('reactions.0.emoji', '😍');
    }

    public function test_cannot_react_to_a_deleted_message(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $va, ['body' => 'gone']);
        $m->forceFill(['deleted_at' => now(), 'deleted_by' => $va->id])->save();   // tombstone (deleted_at isn't fillable)

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '👍'])
            ->assertNotFound();
    }

    public function test_a_removed_group_member_cannot_edit_their_old_message(): void
    {
        $abid = $this->va('Abid');
        $convId = $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/group', ['name' => 'T', 'members' => [$abid->id]])->json('conversation_id');

        $sent = $this->actingAs($abid, 'admin')
            ->postJson('/admin/team-messages', ['conversation_id' => $convId, 'body' => 'abid here'])->json('message.id');

        // Super removes Abid from the group.
        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/group/' . $convId . '/members/' . $abid->id)->assertOk();

        // Abid can no longer edit the message he sent there.
        $this->actingAs($abid, 'admin')
            ->putJson('/admin/team-messages/' . $sent, ['body' => 'sneaky edit'])->assertForbidden();
        $this->assertSame('abid here', TeamMessage::find($sent)->body);
    }

    public function test_delete_for_me_hides_a_message_for_just_me(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $va, ['body' => 'private note']);

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'me'])
            ->assertOk()->assertJson(['mode' => 'me']);

        $this->assertDatabaseHas('message_hides', ['team_message_id' => $m->id, 'admin_id' => $this->super->id]);
        $this->assertNull($m->fresh()->deleted_at);   // not a tombstone

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?c=' . $c->id)
            ->assertViewHas('messages', fn ($msgs) => $msgs->doesntContain('id', $m->id));
        $this->actingAs($va, 'admin')->get('/admin/team-messages?c=' . $c->id)
            ->assertViewHas('messages', fn ($msgs) => $msgs->contains('id', $m->id));
    }

    public function test_delete_for_everyone_tombstones_and_records_who(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $this->super, ['body' => 'oops']);

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])
            ->assertOk()->assertJson(['mode' => 'everyone']);

        $this->assertNotNull($m->fresh()->deleted_at);
        $this->assertSame($this->super->id, $m->fresh()->deleted_by);
    }

    public function test_cannot_delete_someone_elses_message_for_everyone(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = $this->msg($c, $va, ['body' => 'theirs']);

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])->assertForbidden();
    }

    // ---------------------------------------------------------------- groups

    public function test_super_can_create_a_group_with_teammates(): void
    {
        $a = $this->va('A');
        $b = $this->va('B');

        $res = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Credit Repair CFPB', 'icon' => '🔥', 'members' => [$a->id, $b->id],
        ])->assertOk()->assertJson(['ok' => true]);

        $conv = Conversation::where('type', 'group')->firstOrFail();
        $this->assertSame('Credit Repair CFPB', $conv->name);
        $this->assertSame(3, $conv->participants()->count());   // creator + 2
        $this->assertSame('admin', $conv->participants()->where('admin_id', $this->super->id)->first()->role);
        // A "created the group" system message exists.
        $this->assertDatabaseHas('team_messages', ['conversation_id' => $conv->id, 'type' => 'system']);
    }

    public function test_a_group_cannot_include_admins_from_another_org(): void
    {
        $otherSuper = new Admin(['email' => 'o2@test.com', 'password' => 'x', 'full_name' => 'O2']);
        $otherSuper->role = 'super';
        $otherSuper->save();
        $rival = $this->va('Rival', $otherSuper->id);
        $mine  = $this->va('Mine');

        $res = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Mixed', 'members' => [$mine->id, $rival->id],
        ])->assertOk();

        $conv = Conversation::where('type', 'group')->firstOrFail();
        $ids = $conv->participants()->pluck('admin_id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($rival->id, $ids);
    }

    public function test_group_admin_can_add_and_remove_members_but_a_member_cannot(): void
    {
        $a = $this->va('A');
        $b = $this->va('B');
        $convId = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Team', 'members' => [$a->id],
        ])->json('conversation_id');
        $conv = Conversation::find($convId);

        // A plain member (a) cannot add.
        $this->actingAs($a, 'admin')->postJson("/admin/team-messages/group/{$convId}/members", ['members' => [$b->id]])
            ->assertForbidden();

        // The admin (super) can.
        $this->actingAs($this->super, 'admin')->postJson("/admin/team-messages/group/{$convId}/members", ['members' => [$b->id]])
            ->assertOk();
        $this->assertTrue($conv->participants()->where('admin_id', $b->id)->exists());

        // And can remove.
        $this->actingAs($this->super, 'admin')->deleteJson("/admin/team-messages/group/{$convId}/members/{$b->id}")
            ->assertOk();
        $this->assertFalse($conv->fresh()->participants()->where('admin_id', $b->id)->exists());
    }

    public function test_leaving_a_group_removes_you_and_promotes_an_admin_if_needed(): void
    {
        $a = $this->va('A');
        $convId = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Team', 'members' => [$a->id],
        ])->json('conversation_id');

        // The only admin (super) leaves → the remaining member is promoted.
        $this->actingAs($this->super, 'admin')->postJson("/admin/team-messages/group/{$convId}/leave")->assertOk();

        $conv = Conversation::find($convId);
        $this->assertFalse($conv->participants()->where('admin_id', $this->super->id)->exists());
        $this->assertSame('admin', $conv->participants()->where('admin_id', $a->id)->first()->role);
    }

    public function test_a_non_member_cannot_see_or_post_to_a_group(): void
    {
        $a = $this->va('A');
        $outsider = $this->va('Out');
        $convId = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Private', 'members' => [$a->id],
        ])->json('conversation_id');

        $this->actingAs($outsider, 'admin')->get('/admin/team-messages?c=' . $convId)->assertOk()
            ->assertViewHas('active', null);   // not resolved for a non-member
        $res = $this->actingAs($outsider, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $convId, 'body' => 'hi'])
            ->assertStatus(403);
        $this->assertStringContainsString('no longer a member', $res->json('message'));
    }

    public function test_group_message_shows_the_sender_and_body(): void
    {
        $a = $this->va('Abid Hussain');
        $convId = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => 'Team', 'members' => [$a->id],
        ])->json('conversation_id');

        $this->actingAs($a, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $convId, 'body' => 'hello team'])->assertOk();

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?c=' . $convId)
            ->assertOk()->assertSee('hello team')->assertSee('Abid Hussain');
    }
}
