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

        $this->withHeader("User-Agent", "ApexDesktop/1.0");   // chat is desktop-app only
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

    public function test_any_file_type_is_accepted_and_still_served_safely(): void
    {
        $va = $this->va();

        // Task 4: any type may be attached (zip, exe, anything). It stays safe because a
        // non-image is force-downloaded as octet-stream from the private disk, never run.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('installer.exe', 10)],
        ])->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('message.attachments.0.name', 'installer.exe')
            ->assertJsonPath('message.attachments.0.image', false);

        $att = MessageAttachment::firstOrFail();
        Storage::disk('private')->assertExists($att->disk_path);

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/attachment/' . $att->id)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_a_multi_dot_file_name_keeps_its_full_display_name(): void
    {
        $va = $this->va();

        // The download name must survive intact; only the final extension drives the disk token.
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('archive.tar.gz', 20)],
        ])->assertOk()->assertJsonPath('message.attachments.0.name', 'archive.tar.gz');

        $att = MessageAttachment::firstOrFail();
        $this->assertSame('archive.tar.gz', $att->original_name);
        $this->assertStringEndsWith('.gz', $att->disk_path);   // "gz" is a clean token, kept as-is
        Storage::disk('private')->assertExists($att->disk_path);
    }

    public function test_oversized_files_are_rejected(): void
    {
        $va = $this->va();

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id,
            'attachments'  => [UploadedFile::fake()->create('big.pdf', 60000)],   // ~60 MB > 50 MB cap
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

    public function test_a_non_image_is_forced_to_download_and_never_rendered_inline(): void
    {
        $va = $this->va();
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id, 'attachments' => [UploadedFile::fake()->create('doc.pdf', 20)],
        ])->assertOk();
        $att = MessageAttachment::firstOrFail();

        // Even WITHOUT ?dl, a non-image is sent as a nosniff attachment with a neutral type
        // (so a file renamed to an allowed extension can never be rendered as HTML/script).
        $res = $this->actingAs($this->super, 'admin')->get('/admin/team-messages/attachment/' . $att->id)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_an_image_is_served_inline_with_an_explicit_image_type(): void
    {
        $va = $this->va();
        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id, 'attachments' => [UploadedFile::fake()->image('pic.png', 20, 20)],
        ])->assertOk();
        $att = MessageAttachment::firstOrFail();

        $this->actingAs($this->super, 'admin')->get('/admin/team-messages/attachment/' . $att->id)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_an_overlong_filename_is_truncated_not_errored(): void
    {
        $va = $this->va();
        $long = str_repeat('a', 300) . '.pdf';

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'recipient_id' => $va->id, 'attachments' => [UploadedFile::fake()->create($long, 20)],
        ])->assertOk();

        $att = MessageAttachment::firstOrFail();
        $this->assertLessThanOrEqual(200, mb_strlen($att->original_name));
        $this->assertStringEndsWith('.pdf', $att->original_name);
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
