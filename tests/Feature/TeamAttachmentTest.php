<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MessageAttachment;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

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

    public function test_a_file_can_be_sent_with_no_text_and_is_stored_privately(): void
    {
        $va = $this->va();

        $res = $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('Chantal Brown.zip', 400, 'application/zip')],
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message.attachments.0.name', 'Chantal Brown.zip');

        $att = MessageAttachment::firstOrFail();
        $this->assertSame('Chantal Brown.zip', $att->original_name);
        Storage::disk('private')->assertExists($att->disk_path);
        $this->assertFalse($res->json('message.attachments.0.image'));
    }

    public function test_an_image_records_dimensions_and_is_flagged_as_image(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id, 'body' => 'see this',
            'attachments'  => [UploadedFile::fake()->image('shot.png', 120, 90)],
        ])->assertOk()->assertJsonPath('message.attachments.0.image', true);

        $att = MessageAttachment::firstOrFail();
        $this->assertSame(120, $att->width);
        $this->assertSame(90, $att->height);
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('evil.exe', 10)],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments');

        $this->assertSame(0, MessageAttachment::count());
        $this->assertSame(0, TeamMessage::count());
    }

    public function test_oversized_files_are_rejected(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('big.pdf', 30000)],   // ~30 MB > 25 MB cap
        ])->assertStatus(422)->assertJsonValidationErrors('attachments.0');

        $this->assertSame(0, MessageAttachment::count());
    }

    public function test_an_empty_message_with_no_file_is_rejected(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id, 'body' => '   ',
        ])->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_only_a_thread_participant_can_download_a_file(): void
    {
        $va      = $this->va('Sam');
        $outsider = $this->va('Jo');

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('secret.pdf', 50)],
        ])->assertOk();
        $att = MessageAttachment::firstOrFail();
        $url = '/admin/team-messages/attachment/' . $att->id;

        // Sender and recipient can fetch it.
        $this->actingAs($this->super, 'admin')->get($url)->assertOk();
        $this->actingAs($va, 'admin')->get($url)->assertOk();
        // A third teammate cannot.
        $this->actingAs($outsider, 'admin')->get($url)->assertForbidden();
    }

    public function test_deleting_a_message_purges_its_files(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('gone.zip', 40)],
        ])->assertOk();

        $att = MessageAttachment::firstOrFail();
        $msg = TeamMessage::firstOrFail();
        $path = $att->disk_path;

        $this->actingAs($this->super, 'admin')->deleteJson('/admin/team-messages/' . $msg->id)->assertOk();

        Storage::disk('private')->assertMissing($path);
        $this->assertSame(0, MessageAttachment::count());
        // And the file is no longer reachable.
        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/attachment/' . $att->id)->assertNotFound();
    }
}
