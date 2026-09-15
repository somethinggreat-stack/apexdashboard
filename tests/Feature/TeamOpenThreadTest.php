<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamOpenThreadTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('User-Agent', 'ApexDesktop/1.0');

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
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

    private function dm(Admin $a, Admin $b): Conversation
    {
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id]);
        $c->participants()->createMany([
            ['admin_id' => $a->id, 'role' => 'member'], ['admin_id' => $b->id, 'role' => 'member'],
        ]);

        return $c;
    }

    public function test_open_returns_the_newest_page_and_header_for_a_dm(): void
    {
        $va = $this->va('Abid Hussain');
        $c  = $this->dm($va, $this->super);
        foreach (range(1, 3) as $i) {
            TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => "hello {$i}"]);
        }

        $this->actingAs($this->super, 'admin')->getJson("/admin/team-messages/open?c={$c->id}")
            ->assertOk()
            ->assertJsonPath('conversation_id', $c->id)
            ->assertJsonPath('isGroup', false)
            ->assertJsonPath('title', 'Abid Hussain')
            ->assertJsonPath('peer_id', $va->id)
            ->assertJsonPath('placeholder', 'Message Abid Hussain…')
            ->assertJsonCount(3, 'messages')
            ->assertJsonPath('hasMore', false);
    }

    public function test_open_marks_incoming_messages_read(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'ping']);

        $this->actingAs($this->super, 'admin')->getJson("/admin/team-messages/open?c={$c->id}")->assertOk();

        $p = $c->participants()->where('admin_id', $this->super->id)->first();
        $this->assertGreaterThanOrEqual($m->id, (int) $p->last_read_message_id);
    }

    public function test_open_supports_a_virtual_dm_with_no_conversation_yet(): void
    {
        $va = $this->va('Fresh Teammate');

        $this->actingAs($this->super, 'admin')->getJson("/admin/team-messages/open?with={$va->id}")
            ->assertOk()
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('peer_id', $va->id)
            ->assertJsonPath('title', 'Fresh Teammate')
            ->assertJsonCount(0, 'messages');
    }

    public function test_open_supports_the_self_notes_thread(): void
    {
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/open?self=1')
            ->assertOk()
            ->assertJsonPath('isSelf', true)
            ->assertJsonPath('title', 'Notes (You)')
            ->assertJsonPath('placeholder', 'Write a note to yourself…');
    }

    public function test_open_returns_group_header_with_member_count(): void
    {
        $a = $this->va('Aa'); $b = $this->va('Bb');
        $g = Conversation::create(['type' => 'group', 'name' => 'CFPB Team', 'data_owner_id' => $this->super->id, 'icon' => '🔥']);
        $g->participants()->createMany([
            ['admin_id' => $this->super->id, 'role' => 'admin'],
            ['admin_id' => $a->id, 'role' => 'member'],
            ['admin_id' => $b->id, 'role' => 'member'],
        ]);

        $this->actingAs($this->super, 'admin')->getJson("/admin/team-messages/open?c={$g->id}")
            ->assertOk()
            ->assertJsonPath('isGroup', true)
            ->assertJsonPath('title', 'CFPB Team')
            ->assertJsonPath('membersCount', 3);
    }

    public function test_open_rejects_a_conversation_i_am_not_in(): void
    {
        $other = $this->va('Outsider');
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id]);
        $c->participants()->create(['admin_id' => $other->id, 'role' => 'member']);

        // findConversation() treats a conversation I'm not in as not-found (404),
        // consistent with the thread/older endpoints.
        $this->actingAs($this->super, 'admin')->getJson("/admin/team-messages/open?c={$c->id}")
            ->assertStatus(404);
    }
}
