<?php

namespace Tests\Feature;

use App\Models\Admin;
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

    public function test_va_can_message_the_super_admin(): void
    {
        $va = $this->va();

        $this->actingAs($va, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $this->super->id, 'body' => 'Hi boss'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('team_messages', [
            'sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'Hi boss',
        ]);
    }

    public function test_super_can_message_a_va(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')
            ->post('/admin/team-messages', ['recipient_id' => $va->id, 'body' => 'Good work'])
            ->assertRedirect();

        $this->assertDatabaseHas('team_messages', [
            'sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'Good work',
        ]);
    }

    public function test_a_va_can_message_another_va_in_the_same_org(): void
    {
        $a = $this->va('VA A');
        $b = $this->va('VA B');

        $this->actingAs($a, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $b->id, 'body' => 'hey'])
            ->assertOk();

        $this->assertDatabaseHas('team_messages', ['sender_id' => $a->id, 'recipient_id' => $b->id]);
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

    public function test_leads_are_not_messageable_teammates(): void
    {
        $lead = new Admin(['email' => 'lead@test.com', 'password' => 'secret', 'full_name' => 'Lead Gen']);
        $lead->role = 'leads';
        $lead->parent_admin_id = $this->super->id;
        $lead->save();

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $lead->id, 'body' => 'hi'])
            ->assertNotFound();

        // And they don't appear in the contacts list.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()
            ->assertViewHas('members', fn ($m) => $m->doesntContain('id', $lead->id));
    }

    public function test_index_lists_teammates_and_opening_a_thread_marks_incoming_read(): void
    {
        $va = $this->va('VA Sam');
        TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'ping']);

        // Unread shows before opening.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()
            ->assertSee('VA Sam')
            ->assertViewHas('unread', fn ($u) => ($u[$va->id] ?? 0) === 1);

        // Opening the thread marks it read.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?with=' . $va->id)
            ->assertOk()
            ->assertSee('ping');

        $this->assertNotNull(TeamMessage::first()->read_at);
    }

    public function test_contact_list_carries_a_last_message_preview_with_read_state(): void
    {
        $va = $this->va('VA Sam');
        // I sent the last message and they have NOT read it yet.
        TeamMessage::create(['sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'ping you']);

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()
            ->assertViewHas('previews', function ($p) use ($va) {
                return isset($p[$va->id])
                    && $p[$va->id]['body'] === 'ping you'
                    && $p[$va->id]['mine'] === true
                    && $p[$va->id]['read'] === false;
            })
            ->assertSee('ping you');
    }

    public function test_thread_poll_returns_only_messages_after_the_given_id(): void
    {
        $va = $this->va();
        $m1 = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'first']);
        $m2 = TeamMessage::create(['sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'second']);

        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?with=' . $va->id . '&after=' . $m1->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'second')
            ->assertJsonPath('messages.0.mine', true);
    }

    public function test_thread_poll_reports_read_state_of_my_sent_messages(): void
    {
        $va = $this->va();
        $m  = TeamMessage::create(['sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'seen soon']);

        // Before they open it, nothing is read.
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?with=' . $va->id . '&after=999')
            ->assertOk()->assertJsonPath('readUpTo', 0);

        // The VA opens the thread, which marks my message read.
        $this->actingAs($va, 'admin')->get('/admin/team-messages?with=' . $this->super->id)->assertOk();

        // Now my poll reports the read watermark.
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?with=' . $va->id . '&after=999')
            ->assertOk()->assertJsonPath('readUpTo', $m->id);
    }

    public function test_a_participant_can_react_and_toggle_off(): void
    {
        $va = $this->va();
        $m  = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'yo']);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reactions.0.emoji', '👍')
            ->assertJsonPath('reactions.0.count', 1)
            ->assertJsonPath('reactions.0.mine', true);

        $this->assertSame(['👍'], array_values($m->fresh()->reactions));

        // Tapping the same emoji again clears my reaction.
        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '👍'])
            ->assertOk()->assertJsonCount(0, 'reactions');

        $this->assertNull($m->fresh()->reactions);
    }

    public function test_a_non_participant_cannot_react(): void
    {
        $va    = $this->va('Sam');
        $other = $this->va('Jo');
        $m = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'hi']);

        $this->actingAs($other, 'admin')
            ->postJson('/admin/team-messages/react', ['message_id' => $m->id, 'emoji' => '👍'])
            ->assertNotFound();
    }

    public function test_forward_sends_the_text_to_another_teammate(): void
    {
        $a = $this->va('A');
        $b = $this->va('B');
        $m = TeamMessage::create(['sender_id' => $a->id, 'recipient_id' => $this->super->id, 'body' => 'ship it']);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/forward', ['message_id' => $m->id, 'recipient_id' => $b->id])
            ->assertOk()->assertJsonPath('with', $b->id);

        $this->assertDatabaseHas('team_messages', [
            'sender_id' => $this->super->id, 'recipient_id' => $b->id, 'body' => 'ship it', 'forwarded' => true,
        ]);
    }

    public function test_only_the_sender_can_delete_and_it_becomes_a_tombstone(): void
    {
        $va     = $this->va();
        $mine   = TeamMessage::create(['sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'secret']);
        $theirs = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'yours']);

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $theirs->id)->assertForbidden();

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $mine->id)->assertOk();
        $fresh = $mine->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('', $fresh->body);
    }

    public function test_reply_links_to_a_message_in_the_same_thread(): void
    {
        $va   = $this->va();
        $orig = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'question?']);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $va->id, 'body' => 'answer', 'reply_to_id' => $orig->id])
            ->assertOk()->assertJsonPath('message.reply.text', 'question?');

        $this->assertDatabaseHas('team_messages', [
            'sender_id' => $this->super->id, 'recipient_id' => $va->id, 'reply_to_id' => $orig->id,
        ]);
    }

    public function test_reply_to_a_message_outside_the_thread_is_ignored(): void
    {
        $va      = $this->va('Sam');
        $other   = $this->va('Jo');
        $foreign = TeamMessage::create(['sender_id' => $other->id, 'recipient_id' => $this->super->id, 'body' => 'elsewhere']);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages', ['recipient_id' => $va->id, 'body' => 'hi', 'reply_to_id' => $foreign->id])
            ->assertOk();

        $this->assertDatabaseHas('team_messages', [
            'sender_id' => $this->super->id, 'recipient_id' => $va->id, 'body' => 'hi', 'reply_to_id' => null,
        ]);
    }

    public function test_thread_poll_reports_reaction_and_deletion_state(): void
    {
        $va = $this->va();
        $m  = TeamMessage::create(['sender_id' => $va->id, 'recipient_id' => $this->super->id, 'body' => 'hi']);
        $m->reactions = [$this->super->id => '❤️'];
        $m->save();

        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?with=' . $va->id . '&after=999')
            ->assertOk()
            ->assertJsonPath('states.0.id', $m->id)
            ->assertJsonPath('states.0.reactions.0.emoji', '❤️');
    }

    public function test_body_is_required(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')
            ->post('/admin/team-messages', ['recipient_id' => $va->id, 'body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, TeamMessage::count());
    }
}
