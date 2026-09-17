<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Sending one message creates exactly one complete message in exactly the right chat."
 * Covers the server side of that promise: client-id de-duplication, all-or-nothing saves
 * (message + files + mentions), and the group flag the sidebar needs to route new messages.
 */
class TeamSendIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');   // chat is desktop-app only
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

    private function group(array $members, string $name = 'CFPB Group'): Conversation
    {
        $id = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => $name, 'members' => collect($members)->pluck('id')->all(),
        ])->json('conversation_id');

        return Conversation::find($id);
    }

    private function files(): array
    {
        return Storage::disk('private')->allFiles('team-chat');
    }

    public function test_the_same_client_id_sent_twice_creates_one_message(): void
    {
        $va = $this->va();
        $conv = $this->group([$va]);
        $payload = ['conversation_id' => $conv->id, 'body' => 'hello', 'client_uuid' => 'a1b2c3d4-0000-4000-8000-000000000001'];

        $first = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', $payload)->assertOk()->json();
        $second = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', $payload)->assertOk()->json();

        $this->assertSame(1, TeamMessage::where('type', 'text')->count());
        $this->assertSame($first['message']['id'], $second['message']['id']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($conv->id, $second['conversation_id']);
    }

    public function test_a_retried_upload_keeps_one_message_and_one_copy_of_the_file(): void
    {
        $va = $this->va();
        $conv = $this->group([$va]);
        $send = fn () => $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'client_uuid' => 'retry-upload-0001',
            'attachments' => [UploadedFile::fake()->create('JEANCLAUDE CHIN.zip', 300, 'application/zip')],
        ])->assertOk();

        $send();
        $send();   // e.g. the VA pressed Enter again after "Send timed out"

        $this->assertSame(1, TeamMessage::where('type', 'text')->count());
        $this->assertSame(1, MessageAttachment::count());
        $this->assertCount(1, $this->files(), 'the duplicate upload must not leave a second file behind');
    }

    public function test_different_client_ids_are_different_messages(): void
    {
        $va = $this->va();
        $conv = $this->group([$va]);

        foreach (['msg-one-00000001', 'msg-two-00000002'] as $id) {
            $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
                'conversation_id' => $conv->id, 'body' => 'same words', 'client_uuid' => $id,
            ])->assertOk();
        }

        $this->assertSame(2, TeamMessage::where('type', 'text')->count());
    }

    public function test_client_ids_are_per_sender(): void
    {
        $a = $this->va('Abid');
        $b = $this->va('Mujeeb');
        $conv = $this->group([$a, $b]);

        foreach ([$a, $b] as $who) {
            $this->actingAs($who, 'admin')->postJson('/admin/team-messages', [
                'conversation_id' => $conv->id, 'body' => 'hi', 'client_uuid' => 'shared-id-00000001',
            ])->assertOk()->assertJsonMissingPath('duplicate');
        }

        $this->assertSame(2, TeamMessage::where('type', 'text')->count());
    }

    public function test_a_first_dm_sent_twice_creates_one_conversation_and_one_message(): void
    {
        $va = $this->va();
        $payload = ['recipient_id' => $this->super->id, 'body' => 'Hi boss', 'client_uuid' => 'first-dm-00000001'];

        $a = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', $payload)->assertOk()->json('conversation_id');
        $b = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', $payload)->assertOk()->json('conversation_id');

        $this->assertSame($a, $b);
        $this->assertSame(1, Conversation::where('type', 'dm')->count());
        $this->assertSame(1, TeamMessage::count());
    }

    public function test_a_malformed_client_id_is_rejected(): void
    {
        $va = $this->va();
        $conv = $this->group([$va]);

        $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'x', 'client_uuid' => "bad id <script>",
        ])->assertStatus(422);
    }

    public function test_a_failed_save_leaves_no_message_no_attachment_and_no_file(): void
    {
        $va = $this->va();
        $conv = $this->group([$va]);
        $before = TeamMessage::count();

        // Make the message insert fail after the files were already written to disk.
        TeamMessage::creating(function (TeamMessage $m) {
            if ($m->type === 'text') throw new \RuntimeException('db down');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
                'conversation_id' => $conv->id, 'body' => 'with file',
                'attachments' => [UploadedFile::fake()->create('SOPHIA SUAREZ.zip', 200, 'application/zip')],
            ]);
            $this->fail('the send should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('db down', $e->getMessage());
        }

        $this->assertSame($before, TeamMessage::count());
        $this->assertSame(0, MessageAttachment::count());
        $this->assertSame([], $this->files(), 'files written before the failed save must be removed');
    }

    public function test_a_failed_mention_save_rolls_back_the_whole_message(): void
    {
        $abid = $this->va('Abid Hussain');
        $conv = $this->group([$abid]);
        $before = TeamMessage::count();

        // The message row saves, then the mention step fails: nothing may be left half-saved.
        TeamMessage::saved(function (TeamMessage $m) {
            if ($m->type === 'text' && $m->mentions_all) throw new \RuntimeException('mention failed');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
                'conversation_id' => $conv->id, 'body' => '@everyone look',
                'mentions' => ['everyone'],
                'attachments' => [UploadedFile::fake()->create('proof.zip', 50, 'application/zip')],
            ]);
            $this->fail('the send should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('mention failed', $e->getMessage());
        }

        $this->assertSame($before, TeamMessage::count());
        $this->assertSame(0, MessageAttachment::count());
        $this->assertSame([], $this->files());
    }

    public function test_a_message_is_returned_with_its_files_and_mentions_in_one_piece(): void
    {
        $abid = $this->va('Abid Hussain');
        $conv = $this->group([$abid]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'for you @Abid Hussain', 'client_uuid' => 'complete-00000001',
            'mentions' => [(string) $abid->id],
            'attachments' => [UploadedFile::fake()->create('a.zip', 10), UploadedFile::fake()->create('b.zip', 10)],
        ])->assertOk();

        // The very first poll that sees the message sees all of it.
        $msgs = $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/thread?c=' . $conv->id . '&after=0')->json('messages');
        $text = collect($msgs)->firstWhere('body', 'for you @Abid Hussain');
        $this->assertCount(2, $text['attachments']);
        $this->assertTrue($text['mentionsMe']);

        $note = collect($this->actingAs($abid, 'admin')->getJson('/admin/team-messages/notifications?after=0')->json('messages'))
            ->firstWhere('id', $text['id']);
        $this->assertTrue($note['mention']);
    }

    public function test_notifications_say_whether_a_message_is_from_a_group(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid], 'Brand New Group');

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $conv->id, 'body' => 'welcome'])->assertOk();
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['recipient_id' => $abid->id, 'body' => 'dm'])->assertOk();

        $msgs = collect($this->actingAs($abid, 'admin')->getJson('/admin/team-messages/notifications?after=0')->json('messages'));
        $g = $msgs->firstWhere('snippet', 'welcome');
        $d = $msgs->firstWhere('snippet', 'dm');

        $this->assertTrue($g['is_group']);
        $this->assertSame('Brand New Group', $g['title']);
        $this->assertNotEmpty($g['icon']);
        $this->assertFalse($d['is_group']);
        $this->assertNull($d['icon']);
    }

    public function test_message_ids_follow_send_order_within_a_conversation(): void
    {
        $a = $this->va('Abid');
        $b = $this->va('Mujeeb');
        $conv = $this->group([$a, $b]);

        $ids = [];
        foreach ([$a, $b, $a, $this->super] as $i => $who) {
            $ids[] = $this->actingAs($who, 'admin')->postJson('/admin/team-messages', [
                'conversation_id' => $conv->id, 'body' => 'm' . $i,
            ])->json('message.id');
        }

        $sorted = $ids; sort($sorted);
        $this->assertSame($sorted, $ids);
        // A poll from just before my own send still returns the teammate's earlier message.
        $after = $ids[0];
        $got = collect($this->actingAs($a, 'admin')->getJson('/admin/team-messages/thread?c=' . $conv->id . '&after=' . $after)->json('messages'))->pluck('id')->all();
        $this->assertSame(array_slice($ids, 1), $got);
    }

    public function test_forwarding_still_works_through_the_ordered_save(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', ['conversation_id' => $conv->id, 'body' => 'fwd me'])->json('message.id');

        $to = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', [
            'message_id' => $src, 'recipient_id' => $abid->id,
        ])->assertOk()->json('conversation_id');

        $this->assertDatabaseHas('team_messages', ['conversation_id' => $to, 'body' => 'fwd me', 'forwarded' => true]);
        $this->assertSame(1, Conversation::where('type', 'dm')->count());
    }
}
