<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPresenceTest extends TestCase
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

    public function test_presence_reflects_online_and_offline(): void
    {
        $online  = $this->va('On');
        $offline = $this->va('Off');
        $online->forceFill(['last_seen_at' => now()])->saveQuietly();
        $offline->forceFill(['last_seen_at' => now()->subMinutes(30)])->saveQuietly();

        $json = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/presence')
            ->assertOk()->json('presence');

        $byId = collect($json)->keyBy('id');
        $this->assertTrue($byId[$online->id]['online']);
        $this->assertFalse($byId[$offline->id]['online']);
        $this->assertSame('Online', $byId[$online->id]['seen']);
    }

    public function test_visiting_the_app_marks_me_online(): void
    {
        $this->assertNull($this->super->last_seen_at);
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages')->assertOk();
        $this->assertTrue($this->super->fresh()->isOnline());
    }

    public function test_typing_ping_surfaces_in_the_other_persons_thread_poll(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);

        // The VA signals typing…
        $this->actingAs($va, 'admin')->postJson('/admin/team-messages/typing', ['conversation_id' => $c->id])->assertOk();

        // …and the super sees it in their poll.
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0')
            ->assertOk()->assertJsonCount(1, 'typing');
    }

    public function test_thread_poll_returns_read_watermarks_for_seen_by(): void
    {
        $va = $this->va('Abid');
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $this->super->id, 'body' => 'hi']);
        $c->update(['last_message_id' => $m->id]);

        // Before the VA reads: their watermark is 0.
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=999')
            ->assertOk()->assertJsonPath('watermarks.0.upTo', 0);

        // VA opens → reads. Super's next poll shows the VA read up to that message.
        $this->actingAs($va, 'admin')->get('/admin/team-messages?c=' . $c->id)->assertOk();
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=999')
            ->assertOk()->assertJsonPath('watermarks.0.upTo', $m->id);
    }

    public function test_a_non_participant_cannot_signal_typing(): void
    {
        $va = $this->va();
        $outsider = $this->va('Out');
        $c = $this->dm($va, $this->super);

        $this->actingAs($outsider, 'admin')
            ->postJson('/admin/team-messages/typing', ['conversation_id' => $c->id])
            ->assertNotFound();
    }
}
