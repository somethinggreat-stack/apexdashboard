<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A search result opens the chat AROUND that message, so the app can jump to it even when
 * it is far back in the history (it used to open the newest page and land at the bottom).
 */
class TeamSearchJumpTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $va;
    private Conversation $conv;
    private int $needleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('User-Agent', 'ApexDesktop/1.0');

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();

        $this->va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'Abid Hussain']);
        $this->va->role = 'va';
        $this->va->parent_admin_id = $this->super->id;
        $this->va->save();

        $this->conv = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $this->va->id,
            'dm_key' => 'dm:' . min($this->va->id, $this->super->id) . '-' . max($this->va->id, $this->super->id)]);
        $this->conv->participants()->createMany([
            ['admin_id' => $this->super->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $this->va->id, 'role' => 'member', 'joined_at' => now()],
        ]);

        // One old message to find, then far more than a page of newer ones on top of it.
        $this->needleId = $this->say('the CFPB screenshot you asked for')->id;
        foreach (range(1, 80) as $i) { $this->say('later chatter ' . $i); }
    }

    private function say(string $body): TeamMessage
    {
        return TeamMessage::create(['conversation_id' => $this->conv->id, 'type' => 'text', 'sender_id' => $this->va->id, 'body' => $body]);
    }

    public function test_search_returns_the_message_id_to_jump_to(): void
    {
        $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/search?q=CFPB screenshot')
            ->assertOk()->assertJsonPath('messages.0.message_id', $this->needleId)
            ->assertJsonPath('messages.0.conversation_id', $this->conv->id);
    }

    public function test_opening_with_a_message_id_loads_the_page_around_it(): void
    {
        $res = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/open?c=' . $this->conv->id . '&m=' . $this->needleId)->assertOk();

        $ids = collect($res->json('messages'))->pluck('id');
        $this->assertTrue($ids->contains($this->needleId), 'the searched message must be in the page');
        $this->assertSame($this->needleId, $res->json('focusId'));
        $this->assertTrue($ids->last() > $this->needleId, 'with newer messages after it for context');
        $this->assertFalse($res->json('hasMore'), 'this is the oldest message, so nothing older to load');
    }

    public function test_the_full_page_load_also_centres_on_the_message(): void
    {
        $html = $this->actingAs($this->super, 'admin')
            ->get('/admin/team-messages?c=' . $this->conv->id . '&m=' . $this->needleId . '&standalone=1')
            ->assertOk()->assertViewHas('focusId', $this->needleId)->getContent();

        $this->assertStringContainsString('data-focus="' . $this->needleId . '"', $html);
        $this->assertStringContainsString('the CFPB screenshot you asked for', $html);
    }

    public function test_without_a_message_id_the_newest_page_is_returned(): void
    {
        $res = $this->actingAs($this->super, 'admin')->getJson('/admin/team-messages/open?c=' . $this->conv->id)->assertOk();

        $ids = collect($res->json('messages'))->pluck('id');
        $this->assertFalse($ids->contains($this->needleId));
        $this->assertSame(0, $res->json('focusId'));
        $this->assertTrue($res->json('hasMore'));
    }

    public function test_a_message_id_from_another_chat_is_ignored(): void
    {
        $other = Conversation::create(['type' => 'group', 'name' => 'Other', 'data_owner_id' => $this->super->id]);
        $other->participants()->create(['admin_id' => $this->super->id, 'role' => 'admin', 'joined_at' => now()]);
        $foreign = TeamMessage::create(['conversation_id' => $other->id, 'type' => 'text', 'sender_id' => $this->super->id, 'body' => 'elsewhere']);

        $res = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/open?c=' . $this->conv->id . '&m=' . $foreign->id)->assertOk();

        $this->assertSame(0, $res->json('focusId'));
        $this->assertFalse(collect($res->json('messages'))->pluck('body')->contains('elsewhere'));
    }

    public function test_a_message_i_deleted_for_myself_is_not_jumped_to(): void
    {
        TeamMessage::find($this->needleId)->hiddenFor()->syncWithoutDetaching([$this->super->id]);

        $res = $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/open?c=' . $this->conv->id . '&m=' . $this->needleId)->assertOk();

        $this->assertSame(0, $res->json('focusId'));
    }
}
