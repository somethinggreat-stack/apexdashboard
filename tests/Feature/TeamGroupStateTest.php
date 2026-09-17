<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5 — groups, mentions, pins and membership: no broken membership, pin or
 * mention states left behind.
 */
class TeamGroupStateTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');
        Storage::fake('private');

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

    private function group(array $members, string $name = 'CFPB Group', ?Admin $by = null): Conversation
    {
        $id = $this->actingAs($by ?? $this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => $name, 'members' => collect($members)->pluck('id')->all(),
        ])->json('conversation_id');

        return Conversation::find($id);
    }

    private function say(Conversation $c, Admin $from, string $body, array $mentions = []): int
    {
        return $this->actingAs($from, 'admin')->postJson('/admin/team-messages', array_filter([
            'conversation_id' => $c->id, 'body' => $body, 'mentions' => $mentions ?: null,
        ]))->assertOk()->json('message.id');
    }

    // ---------------------------------------------------------------- mentions

    public function test_editing_a_message_keeps_the_mentions_still_written_in_it(): void
    {
        $abid = $this->va('Abid Hussain');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'hey @Abid Hussain please check', [(string) $abid->id]);
        $this->assertDatabaseHas('message_mentions', ['team_message_id' => $id, 'admin_id' => $abid->id]);

        // An edit that keeps the name — the mention (and Abid's @ badge) must survive, even
        // though the client sends no mention ids at all.
        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $id, ['body' => 'hey @Abid Hussain please check today'])
            ->assertOk()->assertJsonPath('message.mentionLabels.0', 'Abid Hussain');

        $this->assertDatabaseHas('message_mentions', ['team_message_id' => $id, 'admin_id' => $abid->id]);
        $this->actingAs($abid, 'admin')->get('/admin/team-messages')->assertOk()
            ->assertViewHas('chats', function ($chats) use ($c) {
                foreach ($chats as $it) if (($it['conversation_id'] ?? null) === $c->id) return ($it['mentions'] ?? 0) === 1;
                return false;
            });
    }

    public function test_editing_out_a_name_drops_that_mention(): void
    {
        $abid = $this->va('Abid Hussain');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'hey @Abid Hussain please check', [(string) $abid->id]);

        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $id, ['body' => 'never mind'])
            ->assertOk()->assertJsonPath('message.mentionLabels', []);

        $this->assertDatabaseMissing('message_mentions', ['team_message_id' => $id, 'admin_id' => $abid->id]);
    }

    public function test_an_edit_can_add_a_mention_and_at_everyone_survives(): void
    {
        $abid = $this->va('Abid Hussain');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'standup now');

        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $id, ['body' => 'standup now @Abid Hussain'])
            ->assertOk();
        $this->assertDatabaseHas('message_mentions', ['team_message_id' => $id, 'admin_id' => $abid->id]);

        $all = $this->say($c, $this->super, '@everyone standup', ['everyone']);
        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $all, ['body' => '@everyone standup in 5'])->assertOk();
        $this->assertTrue((bool) TeamMessage::find($all)->mentions_all);
    }

    // ---------------------------------------------------------------- pins

    public function test_deleting_a_pinned_message_unpins_it_everywhere(): void
    {
        $abid = $this->va('Abid');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'pin me');
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $id, 'pinned' => 1])->assertOk();

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $id, ['mode' => 'everyone'])->assertOk();

        $this->assertNull(TeamMessage::find($id)->pinned_at);
        // Gone from the pinned bar for everyone — no ghost "📎 Attachment" pin.
        $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/open?c=' . $c->id)->assertOk()->assertJsonCount(0, 'pinned');
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages?c=' . $c->id)->assertOk()
            ->assertViewHas('pinned', fn ($p) => $p->count() === 0);
    }

    public function test_an_old_pin_on_a_deleted_message_can_still_be_unpinned(): void
    {
        $abid = $this->va('Abid');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'pin me');
        // A message pinned and deleted by the older code: pinned, and tombstoned.
        TeamMessage::whereKey($id)->update(['pinned_at' => now(), 'pinned_by' => $this->super->id, 'deleted_at' => now(), 'deleted_by' => $this->super->id, 'body' => '']);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $id, 'pinned' => 0])
            ->assertOk()->assertJsonPath('pinned', false);
        $this->assertNull(TeamMessage::find($id)->pinned_at);
    }

    public function test_a_deleted_message_cannot_be_pinned(): void
    {
        $abid = $this->va('Abid');
        $c = $this->group([$abid]);
        $id = $this->say($c, $this->super, 'bye');
        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $id, ['mode' => 'everyone'])->assertOk();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/pin', ['message_id' => $id, 'pinned' => 1])->assertStatus(404);
    }

    // ---------------------------------------------------------------- membership

    public function test_a_removed_member_is_told_plainly_instead_of_a_raw_error(): void
    {
        $abid = $this->va('Abid');
        $c = $this->group([$abid]);
        $this->say($c, $this->super, 'members only');

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/group/' . $c->id . '/members/' . $abid->id)->assertOk();

        foreach ([
            ['GET', '/admin/team-messages/thread?c=' . $c->id . '&after=0'],
            ['GET', '/admin/team-messages/open?c=' . $c->id],
            ['GET', '/admin/team-messages/gallery?c=' . $c->id],
        ] as [$method, $url]) {
            $res = $this->actingAs($abid, 'admin')->json($method, $url)->assertStatus(403);
            $this->assertStringContainsString('no longer a member', $res->json('message'), $url);
            $this->assertStringNotContainsString('No query results', (string) $res->json('message'));
        }

        $send = $this->actingAs($abid, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $c->id, 'body' => 'still here?'])
            ->assertStatus(403);
        $this->assertStringContainsString('no longer a member', $send->json('message'));
    }

    public function test_a_chat_that_no_longer_exists_says_so(): void
    {
        $abid = $this->va('Abid');
        $c = $this->group([$abid]);
        $id = $c->id;
        $c->delete();

        $res = $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/thread?c=' . $id . '&after=0')->assertStatus(404);
        $this->assertStringContainsString('no longer available', $res->json('message'));
    }

    public function test_deleting_the_only_group_admin_promotes_a_remaining_member(): void
    {
        $lead = $this->va('Group Lead');
        $abid = $this->va('Abid');
        $mujeeb = $this->va('Mujeeb');
        // The VA creates the group, so they are its only admin.
        $c = $this->group([$abid, $mujeeb], 'VA Group', $lead);
        $this->assertSame('admin', $c->participants()->where('admin_id', $lead->id)->value('role'));

        $this->actingAs($this->super, 'admin')->delete('/admin/users/' . $lead->id)->assertRedirect();

        $c->load('participants');
        $this->assertCount(2, $c->participants);
        $this->assertTrue($c->participants->contains('role', 'admin'), 'the group must still have an admin');
        $this->assertSame($abid->id, $c->participants->sortBy('id')->firstWhere('role', 'admin')->admin_id);

        // …and that person can actually manage the group now.
        $this->actingAs($abid, 'admin')->postJson('/admin/team-messages/group/' . $c->id . '/rename', ['name' => 'Renamed by new admin'])->assertOk();
    }

    public function test_deleting_a_member_who_was_not_an_admin_changes_no_roles(): void
    {
        $abid = $this->va('Abid');
        $mujeeb = $this->va('Mujeeb');
        $c = $this->group([$abid, $mujeeb]);

        $this->actingAs($this->super, 'admin')->delete('/admin/users/' . $abid->id)->assertRedirect();

        $c->load('participants');
        $this->assertSame([$this->super->id], $c->participants->where('role', 'admin')->pluck('admin_id')->all());
    }

    public function test_a_group_creator_can_still_add_and_remove_after_the_fixes(): void
    {
        $abid = $this->va('Abid');
        $mujeeb = $this->va('Mujeeb');
        $c = $this->group([$abid]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group/' . $c->id . '/members', ['members' => [$mujeeb->id]])->assertOk();
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $c->id, 'admin_id' => $mujeeb->id]);

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/group/' . $c->id . '/members/' . $mujeeb->id)->assertOk();
        $this->assertDatabaseMissing('conversation_participants', ['conversation_id' => $c->id, 'admin_id' => $mujeeb->id]);
    }
}
