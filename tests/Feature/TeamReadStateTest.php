<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — unread / read / seen must reflect what the VA actually looked at:
 * a fresh client never pulls the backlog in as "new", and an open chat is only
 * marked read while its window is really in front.
 */
class TeamReadStateTest extends TestCase
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
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $a->id,
            'dm_key' => 'dm:' . min($a->id, $b->id) . '-' . max($a->id, $b->id)]);
        $c->participants()->createMany([
            ['admin_id' => $a->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $b->id, 'role' => 'member', 'joined_at' => now()],
        ]);

        return $c;
    }

    private function say(Conversation $c, Admin $from, string $body): TeamMessage
    {
        $m = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $from->id, 'body' => $body]);
        $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);

        return $m;
    }

    private function lastRead(Conversation $c, Admin $who): ?int
    {
        return $c->participants()->where('admin_id', $who->id)->value('last_read_message_id');
    }

    // ---------------------------------------------------------------- priming

    public function test_a_fresh_client_gets_the_current_position_not_the_backlog(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        $last = null;
        foreach (range(1, 45) as $i) { $last = $this->say($c, $va, 'old message ' . $i); }

        $res = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')
            ->assertOk()->assertJsonCount(0, 'messages');

        $this->assertSame($last->id, $res->json('lastId'), 'priming must point at the newest message, not the 30th oldest');
        $this->assertFalse($res->json('more'));
        $this->assertSame(45, $res->json('unread'), 'the badge still shows what is unread');
    }

    public function test_priming_with_no_history_still_delivers_the_next_message(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')
            ->assertOk()->assertJsonPath('lastId', 0);

        $this->say($c, $va, 'the very first message');
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.snippet', 'the very first message');
    }

    public function test_a_normal_poll_after_priming_delivers_only_new_messages(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        foreach (range(1, 5) as $i) { $this->say($c, $va, 'old ' . $i); }

        $at = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')->json('lastId');
        $this->say($c, $va, 'brand new');

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=' . $at)
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.snippet', 'brand new');
    }

    public function test_a_long_backlog_is_flagged_so_the_client_can_summarise(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        foreach (range(1, 70) as $i) { $this->say($c, $va, 'msg ' . $i); }

        $first = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0')
            ->assertOk()->assertJsonCount(30, 'messages');
        $this->assertTrue($first->json('more'), 'more batches follow — hold the toasts');

        $second = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=' . $first->json('lastId'));
        $this->assertTrue($second->json('more'));
        $third = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=' . $second->json('lastId'));
        $this->assertFalse($third->json('more'), 'caught up — now show one summary');
    }

    // ---------------------------------------------------------------- read / seen

    public function test_a_poll_from_a_window_that_is_not_in_front_does_not_mark_read(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        $m = $this->say($c, $va, 'while you were away');

        // The app is in the tray / behind another window: read=0.
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0&read=0')
            ->assertOk()->assertJsonCount(1, 'messages');

        $this->assertNull($this->lastRead($c, $this->super), 'nothing may be marked read');

        // The sender sees no blue tick.
        $this->actingAs($va, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0&read=0')
            ->assertOk()->assertJsonPath('readUpTo', 0);

        // The chat still counts as unread for the badge.
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('perConv.' . $c->id, 1);

        // Coming back to the window (read=1) marks it read, and the sender's tick turns.
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0&read=1')->assertOk();
        $this->assertSame($m->id, $this->lastRead($c, $this->super));
        $this->actingAs($va, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0&read=0')
            ->assertOk()->assertJsonPath('readUpTo', $m->id);
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')
            ->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_a_poll_without_the_flag_still_marks_read_for_older_clients(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        $m = $this->say($c, $va, 'hello');

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/thread?c=' . $c->id . '&after=0')->assertOk();

        $this->assertSame($m->id, $this->lastRead($c, $this->super));
    }

    public function test_opening_a_chat_still_marks_it_read_but_a_prefetch_does_not(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        $m = $this->say($c, $va, 'hi');

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/open?c=' . $c->id . '&prefetch=1')->assertOk();
        $this->assertNull($this->lastRead($c, $this->super), 'hovering must not mark read');

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/open?c=' . $c->id)->assertOk();
        $this->assertSame($m->id, $this->lastRead($c, $this->super));
    }

    public function test_messages_i_have_not_looked_at_keep_their_unread_count_per_chat(): void
    {
        $abid = $this->va('Abid');
        $mujeeb = $this->va('Mujeeb');
        $a = $this->dm($this->super, $abid);
        $b = $this->dm($this->super, $mujeeb);
        $this->say($a, $abid, 'one');
        $this->say($a, $abid, 'two');
        $this->say($b, $mujeeb, 'three');

        // Chat A is open but the window is not in front.
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/thread?c=' . $a->id . '&after=0&read=0')->assertOk();

        $res = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')->assertOk();
        $this->assertSame(3, $res->json('unread'));
        $this->assertSame(2, $res->json('perConv.' . $a->id));
        $this->assertSame(1, $res->json('perConv.' . $b->id));
    }

    public function test_a_muted_chat_stays_out_of_the_badge_counts(): void
    {
        $va = $this->va();
        $c = $this->dm($this->super, $va);
        $this->say($c, $va, 'quiet please');
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/mute', ['conversation_id' => $c->id, 'muted' => 1])->assertOk();

        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/notifications?after=0&prime=1')
            ->assertOk()->assertJsonPath('unread', 0);
    }
}
