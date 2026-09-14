<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeOldTeamChatTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader("User-Agent", "ApexDesktop/1.0");   // chat is desktop-app only

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function va(): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => 'VA Sam']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    private function dm(Admin $a, Admin $b): Conversation
    {
        $c = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id, 'created_by' => $a->id]);
        $c->participants()->createMany([
            ['admin_id' => $a->id, 'role' => 'member', 'joined_at' => now()],
            ['admin_id' => $b->id, 'role' => 'member', 'joined_at' => now()],
        ]);

        return $c;
    }

    private function msg(Conversation $c, Admin $sender, \DateTimeInterface $at, string $body = 'hi'): TeamMessage
    {
        $m = new TeamMessage(['conversation_id' => $c->id, 'type' => 'text', 'sender_id' => $sender->id, 'body' => $body]);
        $m->created_at = $at;
        $m->updated_at = $at;
        $m->save();

        return $m;
    }

    public function test_it_deletes_messages_and_files_older_than_the_window_and_keeps_recent_ones(): void
    {
        Storage::fake('private');
        $va = $this->va();
        $c  = $this->dm($va, $this->super);

        // An old message (9 days) with an attached file, and a recent one (1 day).
        $old = $this->msg($c, $va, now()->subDays(9), 'old');
        Storage::disk('private')->put('team-chat/1/old.zip', 'x');
        $old->attachments()->create(['disk_path' => 'team-chat/1/old.zip', 'original_name' => 'old.zip', 'mime' => 'application/zip', 'size' => 1]);

        $recent = $this->msg($c, $va, now()->subDay(), 'recent');
        Storage::disk('private')->put('team-chat/1/recent.zip', 'y');
        $recent->attachments()->create(['disk_path' => 'team-chat/1/recent.zip', 'original_name' => 'recent.zip', 'mime' => 'application/zip', 'size' => 1]);

        // An orphan file no message references.
        Storage::disk('private')->put('team-chat/1/orphan.zip', 'z');

        $c->update(['last_message_id' => $recent->id, 'last_message_at' => $recent->created_at]);

        $this->artisan('team-chat:purge')->assertSuccessful();

        // Old message + its file gone; recent kept.
        $this->assertNull(TeamMessage::find($old->id));
        $this->assertNotNull(TeamMessage::find($recent->id));
        Storage::disk('private')->assertMissing('team-chat/1/old.zip');
        Storage::disk('private')->assertExists('team-chat/1/recent.zip');
        // Orphan swept.
        Storage::disk('private')->assertMissing('team-chat/1/orphan.zip');

        // Conversation kept, still pointing at the surviving message.
        $this->assertDatabaseHas('conversations', ['id' => $c->id, 'last_message_id' => $recent->id]);
    }

    public function test_a_conversation_with_only_old_messages_is_cleared_but_kept(): void
    {
        Storage::fake('private');
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $this->msg($c, $va, now()->subDays(30), 'ancient');
        $c->update(['last_message_id' => 1, 'last_message_at' => now()->subDays(30)]);

        $this->artisan('team-chat:purge')->assertSuccessful();

        $this->assertSame(0, TeamMessage::where('conversation_id', $c->id)->count());
        // The chat itself stays in the sidebar, with its pointer cleared.
        $this->assertDatabaseHas('conversations', ['id' => $c->id, 'last_message_id' => null, 'last_message_at' => null]);
    }

    public function test_the_days_window_is_configurable(): void
    {
        Storage::fake('private');
        $va = $this->va();
        $c  = $this->dm($va, $this->super);
        $this->msg($c, $va, now()->subDays(3), 'three-days-old');

        // A 2-day window drops the 3-day-old message; a 7-day window keeps it.
        $this->artisan('team-chat:purge --days=2')->assertSuccessful();
        $this->assertSame(0, TeamMessage::where('conversation_id', $c->id)->count());
    }
}
