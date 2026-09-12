<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamOrganizeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Umair']);
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

    public function test_favoriting_moves_a_chat_into_the_favorites_section(): void
    {
        $va = $this->va('Abid');
        $c  = $this->dm($va, $this->super);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/favorite', ['conversation_id' => $c->id, 'favorite' => true])
            ->assertOk()->assertJson(['favorite' => true]);

        $this->assertTrue((bool) $c->participants()->where('admin_id', $this->super->id)->first()->favorite);

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')
            ->assertOk()->assertViewHas('favorites', fn ($f) => count($f) === 1 && $f[0]['peer_id'] === $va->id);
    }

    public function test_muting_suppresses_the_nav_unread_badge(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'ping']);

        // Mute, then confirm the unread no longer counts on the page's nav badge query.
        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/mute', ['conversation_id' => $c->id, 'muted' => true])->assertOk();

        $unread = \Illuminate\Support\Facades\DB::table('team_messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.admin_id', $this->super->id)->where('p.muted', false)
            ->where('m.type', 'text')->where('m.sender_id', '!=', $this->super->id)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')->count();

        $this->assertSame(0, $unread);
    }

    public function test_search_finds_messages_by_body(): void
    {
        $va = $this->va('Abid');
        $c  = $this->dm($va, $this->super);
        TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'the invoice for Chantal is ready']);
        TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'unrelated note']);

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/search?q=invoice')
            ->assertOk()->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.conversation_id', $c->id)
            ->assertJsonPath('messages.0.title', 'Abid');
    }

    public function test_search_only_covers_my_own_conversations(): void
    {
        $va = $this->va();
        $otherSuper = new Admin(['email' => 'o@t.com', 'password' => 'x', 'full_name' => 'O']);
        $otherSuper->role = 'super';
        $otherSuper->save();
        $rivalDm = Conversation::create(['type' => 'dm', 'data_owner_id' => $otherSuper->id]);
        $rivalDm->participants()->createMany([['admin_id' => $otherSuper->id, 'role' => 'member']]);
        TeamMessage::create(['conversation_id' => $rivalDm->id, 'type' => 'text', 'sender_id' => $otherSuper->id, 'body' => 'secret invoice data']);

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/search?q=invoice')
            ->assertOk()->assertJsonCount(0, 'messages');
    }

    public function test_a_participant_can_pin_and_unpin_a_message(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'important']);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $m->id, 'pinned' => true])
            ->assertOk()->assertJson(['pinned' => true]);
        $this->assertNotNull($m->fresh()->pinned_at);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $m->id, 'pinned' => false])
            ->assertOk()->assertJson(['pinned' => false]);
        $this->assertNull($m->fresh()->pinned_at);
    }

    public function test_a_non_participant_cannot_pin(): void
    {
        $va = $this->va();
        $outsider = $this->va('Out');
        $c = $this->dm($va, $this->super);
        $m = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'x']);

        $this->actingAs($outsider, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $m->id, 'pinned' => true])
            ->assertNotFound();
    }
}
