<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TeamMessageController;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2 — uploads: one set of size limits the client and server agree on (Cloudflare's
 * 100 MiB request cap, PHP's limits, our own caps), clear rejections, and forwarding that
 * carries the files with it.
 */
class TeamUploadLimitsForwardTest extends TestCase
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

    private function group(array $members, string $name = 'CFPB Group'): Conversation
    {
        $id = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/group', [
            'name' => $name, 'members' => collect($members)->pluck('id')->all(),
        ])->json('conversation_id');

        return Conversation::find($id);
    }

    // ---------------------------------------------------------------- limits

    public function test_the_per_message_limit_stays_under_cloudflares_100_mib(): void
    {
        $lim = TeamMessageController::uploadLimits();

        $this->assertLessThan(100 * 1024 * 1024, $lim['total']);
        $this->assertLessThanOrEqual($lim['total'], $lim['file']);
        $this->assertLessThanOrEqual(50 * 1024 * 1024, $lim['file']);
        $this->assertGreaterThan(0, $lim['count']);
    }

    public function test_files_that_add_up_past_the_message_limit_are_rejected_clearly(): void
    {
        config(['team.chat.max_request_mb' => 1]);   // 1 MiB message cap for the test
        $va = $this->va();
        $conv = $this->group([$va]);

        $res = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id,
            'attachments' => [UploadedFile::fake()->create('a.zip', 450), UploadedFile::fake()->create('b.zip', 450)],
        ])->assertStatus(422);

        $this->assertStringContainsString('separate messages', $res->json('message'));
        $this->assertSame(0, TeamMessage::where('type', 'text')->count());
        $this->assertSame([], Storage::disk('private')->allFiles('team-chat'), 'nothing may be stored for a rejected message');
    }

    public function test_a_single_file_over_the_cap_is_named_in_the_error(): void
    {
        config(['team.chat.max_request_mb' => 1]);
        $va = $this->va();
        $conv = $this->group([$va]);

        $res = $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id,
            'attachments' => [UploadedFile::fake()->create('CHANTAL BROWN.zip', 1200)],
        ])->assertStatus(422);

        $this->assertStringContainsString('CHANTAL BROWN.zip', $res->json('message'));
    }

    public function test_files_within_the_limits_still_send(): void
    {
        config(['team.chat.max_request_mb' => 2]);
        $va = $this->va();
        $conv = $this->group([$va]);

        $this->actingAs($va, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id,
            'attachments' => [UploadedFile::fake()->create('a.zip', 700), UploadedFile::fake()->create('b.zip', 700)],
        ])->assertOk()->assertJsonCount(2, 'message.attachments');
    }

    public function test_the_chat_page_carries_the_limits_for_the_client(): void
    {
        config(['team.chat.max_request_mb' => 40]);
        $va = $this->va();

        $this->actingAs($va, 'admin')->get('/admin/team-messages?standalone=1')->assertOk()
            ->assertViewHas('uploadLimits', fn ($l) => $l['total'] < 40 * 1024 * 1024 && $l['file'] <= $l['total']);
    }

    public function test_only_the_super_admin_can_read_the_server_limits_report(): void
    {
        $this->actingAs($this->va(), 'admin')->getJson('/admin/system/upload-limits')->assertStatus(403);

        $this->actingAs($this->super, 'admin')->getJson('/admin/system/upload-limits')->assertOk()
            ->assertJsonStructure(['chat_max_file', 'chat_max_per_message', 'chat_max_files', 'edge_cap_mb',
                'php' => ['upload_max_filesize', 'post_max_size', 'max_input_time', 'max_execution_time']]);
    }

    public function test_a_fresh_csrf_token_can_be_fetched_by_a_signed_in_user(): void
    {
        $this->actingAs($this->va(), 'admin')->getJson('/admin/team-messages/csrf')
            ->assertOk()->assertJsonStructure(['token']);
        $this->app['auth']->guard('admin')->logout();
        $this->getJson('/admin/team-messages/csrf')->assertStatus(401);
    }

    public function test_the_page_allows_blob_previews_for_staged_files_and_the_photo_cropper(): void
    {
        // Without blob: in img-src the browser blocks the previews the page makes itself:
        // attachment thumbnails show broken, and "Change photo" fails to open the image.
        $csp = $this->actingAs($this->va(), 'admin')->get('/admin/team-messages?standalone=1')
            ->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    // ---------------------------------------------------------------- forward

    public function test_forwarding_a_file_message_copies_the_files(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id,
            'attachments' => [UploadedFile::fake()->create('SOPHIA SUAREZ.zip', 120), UploadedFile::fake()->image('shot.png', 40, 30)],
        ])->json('message.id');

        $res = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', [
            'message_id' => $src, 'recipient_id' => $abid->id,
        ])->assertOk()->assertJson(['ok' => true, 'files' => 2, 'missing_files' => 0]);

        $fwd = TeamMessage::where('conversation_id', $res->json('conversation_id'))->where('forwarded', true)->firstOrFail();
        $this->assertCount(2, $fwd->attachments);
        $orig = TeamMessage::find($src)->attachments;
        foreach ($fwd->attachments as $i => $a) {
            $this->assertNotSame($orig[$i]->disk_path, $a->disk_path, 'the forward must have its own copy');
            Storage::disk('private')->assertExists($a->disk_path);
            $this->assertSame($orig[$i]->original_name, $a->original_name);
        }
        $this->assertSame(40, $fwd->attachments->firstWhere('original_name', 'shot.png')->width);

        // The recipient sees the files in the forwarded message and can download them.
        $msgs = $this->actingAs($abid, 'admin')->getJson('/admin/team-messages/thread?c=' . $res->json('conversation_id') . '&after=0')->json('messages');
        $this->assertCount(2, collect($msgs)->firstWhere('forwarded', true)['attachments']);
        $this->actingAs($abid, 'admin')->get('/admin/team-messages/attachment/' . $fwd->attachments[0]->id . '?dl=1')->assertOk();
    }

    public function test_the_forwarded_copy_survives_the_original_being_deleted(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'attachments' => [UploadedFile::fake()->create('proof.zip', 30)],
        ])->json('message.id');
        $to = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', ['message_id' => $src, 'recipient_id' => $abid->id])->json('conversation_id');

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $src, ['mode' => 'everyone'])->assertOk();

        $copy = TeamMessage::where('conversation_id', $to)->firstOrFail()->attachments->first();
        Storage::disk('private')->assertExists($copy->disk_path);
    }

    public function test_forwarding_text_with_a_missing_file_sends_the_text_and_says_so(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'see attached',
            'attachments' => [UploadedFile::fake()->create('gone.zip', 10)],
        ])->json('message.id');
        Storage::disk('private')->delete(MessageAttachment::firstOrFail()->disk_path);   // e.g. uploaded on the other host

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', ['message_id' => $src, 'recipient_id' => $abid->id])
            ->assertOk()->assertJson(['files' => 0, 'missing_files' => 1]);
        $this->assertDatabaseHas('team_messages', ['body' => 'see attached', 'forwarded' => true]);
    }

    public function test_forwarding_a_file_only_message_whose_file_is_gone_is_refused(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'attachments' => [UploadedFile::fake()->create('gone.zip', 10)],
        ])->json('message.id');
        Storage::disk('private')->delete(MessageAttachment::firstOrFail()->disk_path);

        $res = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', ['message_id' => $src, 'recipient_id' => $abid->id])
            ->assertStatus(422);
        $this->assertStringContainsString('no longer available', $res->json('message'));
        $this->assertSame(0, TeamMessage::where('forwarded', true)->count());
    }

    public function test_a_failed_forward_leaves_no_copied_files(): void
    {
        $abid = $this->va('Abid');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'attachments' => [UploadedFile::fake()->create('x.zip', 10)],
        ])->json('message.id');
        $before = Storage::disk('private')->allFiles('team-chat');

        TeamMessage::creating(function (TeamMessage $m) { if ($m->forwarded) throw new \RuntimeException('db down'); });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages/forward', ['message_id' => $src, 'recipient_id' => $abid->id]);
            $this->fail('should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('db down', $e->getMessage());
        }

        $this->assertEqualsCanonicalizing($before, Storage::disk('private')->allFiles('team-chat'));
    }

    public function test_a_non_participant_cannot_forward_someone_elses_file(): void
    {
        $abid = $this->va('Abid');
        $outsider = $this->va('Outsider');
        $conv = $this->group([$abid]);
        $src = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'attachments' => [UploadedFile::fake()->create('private.zip', 10)],
        ])->json('message.id');

        $this->actingAs($outsider, 'admin')->postJson('/admin/team-messages/forward', ['message_id' => $src, 'recipient_id' => $this->super->id])
            ->assertStatus(404);
        $this->assertSame(1, MessageAttachment::count());
    }
}
