<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', 'ApexDesktop/1.0');   // chat is desktop-app only
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

    public function test_a_user_can_upload_their_own_photo(): void
    {
        $this->actingAs($this->super, 'admin')->post('/admin/team-messages/avatar', [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk()->assertJsonPath('ok', true);

        $this->super->refresh();
        $this->assertSame('team-avatars/' . $this->super->id . '.jpg', $this->super->avatar);
        Storage::disk('private')->assertExists('team-avatars/' . $this->super->id . '.jpg');
    }

    public function test_removing_a_photo_sets_the_no_photo_sentinel(): void
    {
        $this->super->forceFill(['avatar' => 'team-avatars/' . $this->super->id . '.jpg'])->save();
        Storage::disk('private')->put('team-avatars/' . $this->super->id . '.jpg', 'x');

        $this->actingAs($this->super, 'admin')->delete('/admin/team-messages/avatar')
            ->assertOk()->assertJsonPath('ok', true);

        $this->super->refresh();
        $this->assertSame('-', $this->super->avatar);
        $this->assertNull($this->super->avatarUrl());
        Storage::disk('private')->assertMissing('team-avatars/' . $this->super->id . '.jpg');
    }

    public function test_a_teammate_can_view_my_photo_but_an_outsider_cannot(): void
    {
        $va = $this->va();
        $this->super->forceFill(['avatar' => 'team-avatars/' . $this->super->id . '.jpg'])->save();
        Storage::disk('private')->put('team-avatars/' . $this->super->id . '.jpg', 'imgbytes');

        // Same org — allowed.
        $this->actingAs($va, 'admin')->get('/admin/team-messages/avatar/' . $this->super->id)->assertOk();

        // A different org's admin cannot.
        $rival = new Admin(['email' => 'r@t.com', 'password' => 'x', 'full_name' => 'Rival']);
        $rival->role = 'super';
        $rival->save();
        $this->actingAs($rival, 'admin')->get('/admin/team-messages/avatar/' . $this->super->id)->assertForbidden();
    }

    public function test_a_non_image_is_rejected(): void
    {
        $this->actingAs($this->super, 'admin')->post('/admin/team-messages/avatar', [
            'photo' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ])->assertStatus(302);   // validation redirect (non-JSON post)

        $this->super->refresh();
        $this->assertNull($this->super->avatar);
    }
}
