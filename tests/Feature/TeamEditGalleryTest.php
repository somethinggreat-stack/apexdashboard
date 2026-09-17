<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamEditGalleryTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader("User-Agent", "ApexDesktop/1.0");   // chat is desktop-app only
        Storage::fake('private');
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
        $c->participants()->createMany([['admin_id' => $a->id, 'role' => 'member'], ['admin_id' => $b->id, 'role' => 'member']]);

        return $c;
    }

    public function test_editing_my_message_updates_the_body_and_stamps_edited(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $this->super->id, 'body' => 'typo heer']);

        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $m->id, ['body' => 'typo here'])
            ->assertOk()->assertJsonPath('message.body', 'typo here')->assertJsonPath('message.edited', true);

        $this->assertSame('typo here', $m->fresh()->body);
        $this->assertNotNull($m->fresh()->edited_at);
    }

    public function test_cannot_edit_someone_elses_message(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $m  = TeamMessage::create(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $va->id, 'body' => 'theirs']);

        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $m->id, ['body' => 'hacked'])->assertForbidden();
        $this->assertSame('theirs', $m->fresh()->body);
    }

    public function test_editing_re_resolves_mentions(): void
    {
        $abid = $this->va('Abid');
        $convId = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', ['name' => 'T', 'members' => [$abid->id]])->json('conversation_id');

        $sent = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $convId, 'body' => 'plain', 'mentions' => [],
        ])->json('message.id');

        $this->actingAs($this->super, 'admin')->putJson('/admin/team-messages/' . $sent, [
            'body' => 'now @Abid', 'mentions' => [(string) $abid->id],
        ])->assertOk();

        $this->assertDatabaseHas('message_mentions', ['team_message_id' => $sent, 'admin_id' => $abid->id]);
    }

    public function test_gallery_lists_the_conversations_files(): void
    {
        $va = $this->va();
        $c  = $this->dm($va, $this->super);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $c->id,
            'attachments' => [UploadedFile::fake()->create('Report.pdf', 30), UploadedFile::fake()->image('pic.png', 40, 40)],
        ])->assertOk();

        $this->actingAs($va, 'admin')->getJson('/admin/team-messages/gallery?c=' . $c->id)
            ->assertOk()->assertJsonCount(2, 'files');
    }

    public function test_a_non_participant_cannot_open_the_gallery(): void
    {
        $va = $this->va();
        $outsider = $this->va('Out');
        $c = $this->dm($va, $this->super);

        $this->actingAs($outsider, 'admin')->getJson('/admin/team-messages/gallery?c=' . $c->id)->assertStatus(403);   // told plainly, not a raw 404
    }
}
