<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 2 of the owner's controls: the super admin runs every group in their own org and can
 * remove any message, without having to ask the person who posted it.
 *
 * The line held here: ACTING is allowed and visible; READING is not. The owner still cannot
 * open a thread they are not part of — they chose not to read what VAs say to each other, and
 * gaining the power to delete must not quietly hand them the power to read.
 */
class TeamChatOwnerControlsTest extends TestCase
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

    private function va(string $name): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    /** A group the owner was never added to. */
    private function group(array $members, string $name = 'VAs only'): Conversation
    {
        $c = Conversation::create(['type' => 'group', 'name' => $name, 'data_owner_id' => $this->super->id, 'created_by' => $members[0]->id]);
        foreach ($members as $i => $m) {
            $c->participants()->create(['admin_id' => $m->id, 'role' => $i === 0 ? 'admin' : 'member', 'joined_at' => now()]);
        }

        return $c;
    }

    private function say(Conversation $c, Admin $who, string $body): TeamMessage
    {
        return TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $who->id, 'body' => $body]);
    }

    // ---- deleting ---------------------------------------------------------------------

    public function test_the_owner_can_remove_a_message_from_a_group_they_are_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $g = $this->group([$abid, $sam]);
        $m = $this->say($g, $abid, 'client SSN 123-45-6789');

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])
            ->assertOk()->assertJsonPath('mode', 'everyone');

        $m->refresh();
        $this->assertNotNull($m->deleted_at);
        $this->assertSame('', $m->body);
        // Attributed, not vanished: the group will read "deleted by Umair".
        $this->assertSame($this->super->id, $m->deleted_by);
    }

    public function test_a_va_still_cannot_remove_someone_elses_message(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $g = $this->group([$abid, $sam]);
        $m = $this->say($g, $abid, 'mine');

        $this->actingAs($sam, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])
            ->assertForbidden();

        $this->assertNull($m->fresh()->deleted_at);
    }

    public function test_the_owner_cannot_touch_another_orgs_message(): void
    {
        $otherOwner = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other']);
        $otherOwner->role = 'super';
        $otherOwner->save();

        $c = Conversation::create(['type' => 'group', 'name' => 'Theirs', 'data_owner_id' => $otherOwner->id, 'created_by' => $otherOwner->id]);
        $c->participants()->create(['admin_id' => $otherOwner->id, 'role' => 'admin', 'joined_at' => now()]);
        $m = $this->say($c, $otherOwner, 'not yours');

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])
            ->assertForbidden();
    }

    public function test_removing_someone_elses_message_is_written_to_the_activity_log(): void
    {
        $abid = $this->va('Abid');
        $g = $this->group([$abid, $this->va('Sam')]);
        $m = $this->say($g, $abid, 'oops');

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $this->super->id,
            'action'   => 'admin.team-messages.destroy',
        ]);
    }

    public function test_deleting_your_own_message_is_not_logged(): void
    {
        $abid = $this->va('Abid');
        $g = $this->group([$abid, $this->super], 'Shared');
        $m = $this->say($g, $abid, 'mine');

        $this->actingAs($abid, 'admin')
            ->deleteJson('/admin/team-messages/' . $m->id, ['mode' => 'everyone'])->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['action' => 'admin.team-messages.destroy']);
    }

    // ---- running every group ------------------------------------------------------------

    public function test_the_owner_can_manage_a_group_they_are_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $late = $this->va('Late');
        $g = $this->group([$abid, $sam]);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/group/' . $g->id . '/rename', ['name' => 'Renamed by owner'])->assertOk();
        $this->assertSame('Renamed by owner', $g->fresh()->name);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/group/' . $g->id . '/members', ['members' => [$late->id]])->assertOk();
        $this->assertTrue($g->fresh()->participants->contains('admin_id', $late->id));

        $this->actingAs($this->super, 'admin')
            ->deleteJson('/admin/team-messages/group/' . $g->id . '/members/' . $sam->id)->assertOk();
        $this->assertFalse($g->fresh()->participants->contains('admin_id', $sam->id));
    }

    public function test_a_plain_member_still_cannot_manage_the_group(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');           // added as a member, not an admin
        $g = $this->group([$abid, $sam]);

        $this->actingAs($sam, 'admin')
            ->postJson('/admin/team-messages/group/' . $g->id . '/rename', ['name' => 'Nope'])->assertForbidden();
    }

    // ---- joining ------------------------------------------------------------------------

    public function test_the_owner_can_join_any_group_and_the_group_is_told(): void
    {
        $abid = $this->va('Abid');
        $g = $this->group([$abid, $this->va('Sam')]);

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/group/' . $g->id . '/join')->assertOk();

        $g->refresh()->load('participants');
        $this->assertTrue($g->participants->contains('admin_id', $this->super->id));
        // Joining is deliberately visible — no silent presence in a conversation.
        $this->assertDatabaseHas('team_messages', [
            'conversation_id' => $g->id, 'type' => 'system',
            'body' => 'Umair Arshad joined the group',
        ]);
    }

    public function test_a_va_cannot_join_a_group_they_were_not_added_to(): void
    {
        $abid = $this->va('Abid');
        $outsider = $this->va('Outsider');
        $g = $this->group([$abid, $this->va('Sam')]);

        $this->actingAs($outsider, 'admin')
            ->postJson('/admin/team-messages/group/' . $g->id . '/join')->assertForbidden();
    }

    // ---- offboarding --------------------------------------------------------------------

    public function test_removing_someone_from_every_group_at_once(): void
    {
        $leaver = $this->va('Leaver');
        $abid   = $this->va('Abid');
        $a = $this->group([$abid, $leaver], 'One');
        $b = $this->group([$abid, $leaver], 'Two');
        $untouched = $this->group([$abid], 'Three');

        $this->actingAs($this->super, 'admin')
            ->postJson('/admin/team-messages/people/' . $leaver->id . '/remove-everywhere')
            ->assertOk()->assertJsonPath('groups', 2);

        $this->assertFalse($a->fresh()->participants->contains('admin_id', $leaver->id));
        $this->assertFalse($b->fresh()->participants->contains('admin_id', $leaver->id));
        $this->assertTrue($untouched->fresh()->participants->contains('admin_id', $abid->id));
    }

    public function test_a_va_cannot_remove_anyone_everywhere(): void
    {
        $abid   = $this->va('Abid');
        $leaver = $this->va('Leaver');
        $this->group([$abid, $leaver]);

        $this->actingAs($abid, 'admin')
            ->postJson('/admin/team-messages/people/' . $leaver->id . '/remove-everywhere')
            ->assertForbidden();
    }

    // ---- the line that must not move ----------------------------------------------------

    public function test_the_owner_still_cannot_read_a_thread_they_are_not_in(): void
    {
        $abid = $this->va('Abid');
        $sam  = $this->va('Sam');
        $g = $this->group([$abid, $sam]);
        $this->say($g, $abid, 'PRIVATE-GROUP-TALK');

        // Being able to DELETE a message does not mean being able to READ the conversation.
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/thread?c=' . $g->id . '&after=0')->assertForbidden();
        $this->actingAs($this->super, 'admin')
            ->getJson('/admin/team-messages/open?c=' . $g->id)->assertForbidden();
    }
}
