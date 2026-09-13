<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function va(string $name = 'VA'): Admin
    {
        $va = new Admin(['email' => 'va' . uniqid() . '@test.com', 'password' => 'secret', 'full_name' => $name]);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    public function test_subscribe_stores_the_browser_subscription(): void
    {
        $this->actingAs($this->super, 'admin')->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'PUBKEY', 'auth' => 'AUTHTOKEN'],
            'contentEncoding' => 'aes128gcm',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'admin_id' => $this->super->id,
            'endpoint' => 'https://push.example.com/abc',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_subscribing_twice_with_same_endpoint_updates_not_duplicates(): void
    {
        $payload = ['endpoint' => 'https://push.example.com/dup', 'keys' => ['p256dh' => 'A', 'auth' => 'B']];
        $this->actingAs($this->super, 'admin')->postJson('/admin/push/subscribe', $payload)->assertOk();
        $this->actingAs($this->super, 'admin')->postJson('/admin/push/subscribe', array_merge($payload, ['keys' => ['p256dh' => 'A2', 'auth' => 'B2']]))->assertOk();

        $this->assertSame(1, PushSubscription::where('endpoint', 'https://push.example.com/dup')->count());
        $this->assertSame('A2', PushSubscription::where('endpoint', 'https://push.example.com/dup')->first()->p256dh);
    }

    public function test_unsubscribe_removes_the_subscription(): void
    {
        $this->actingAs($this->super, 'admin')->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/gone', 'keys' => ['p256dh' => 'A', 'auth' => 'B'],
        ])->assertOk();

        $this->actingAs($this->super, 'admin')->postJson('/admin/push/unsubscribe', ['endpoint' => 'https://push.example.com/gone'])->assertOk();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/gone']);
    }

    public function test_sender_is_a_noop_without_vapid_keys(): void
    {
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);
        $this->assertFalse(WebPushSender::enabled());
        // Must not throw even with a subscription present.
        PushSubscription::create([
            'admin_id' => $this->super->id, 'endpoint' => 'https://x/y', 'endpoint_hash' => hash('sha256', 'https://x/y'),
            'p256dh' => 'A', 'auth' => 'B',
        ]);
        app(WebPushSender::class)->sendToAdmins([$this->super->id], ['title' => 't', 'body' => 'b', 'url' => '/']);
        $this->assertTrue(true);
    }

    public function test_setup_page_is_super_admin_only(): void
    {
        $va = $this->va();
        $this->actingAs($va, 'admin')->get('/admin/push/setup')->assertForbidden();
        $this->actingAs($this->super, 'admin')->get('/admin/push/setup')->assertOk();
    }

    public function test_setup_page_reports_active_when_keys_present(): void
    {
        config(['webpush.public_key' => 'PUB', 'webpush.private_key' => 'PRIV']);
        $this->actingAs($this->super, 'admin')->get('/admin/push/setup')
            ->assertOk()->assertSee('active', false);
    }

    public function test_sending_a_message_does_not_error_when_push_is_unconfigured(): void
    {
        config(['webpush.public_key' => null]);
        $va = $this->va();
        $conv = Conversation::create(['type' => 'dm', 'data_owner_id' => $this->super->id]);
        $conv->participants()->createMany([
            ['admin_id' => $this->super->id, 'role' => 'member'],
            ['admin_id' => $va->id, 'role' => 'member'],
        ]);

        $this->actingAs($this->super, 'admin')->postJson('/admin/team-messages', [
            'conversation_id' => $conv->id, 'body' => 'hello push',
        ])->assertOk()->assertJsonPath('message.body', 'hello push');
    }
}
