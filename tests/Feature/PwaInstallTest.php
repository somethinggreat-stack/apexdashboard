<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The installable PWA was removed at the team's request. The service worker now
 * exists ONLY for Web Push (background/closed-tab chat notifications) — there is
 * no manifest, no offline page, and no asset caching.
 */
class PwaInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_is_removed_and_sw_is_push_only(): void
    {
        // The service worker stays (for Web Push) but the installable-app files are gone.
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileDoesNotExist(public_path('manifest.webmanifest'));
        $this->assertFileDoesNotExist(public_path('offline.html'));

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('push'", $sw);        // handles push
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
        $this->assertStringNotContainsString("addEventListener('fetch'", $sw);    // no asset caching / offline app
    }

    public function test_team_pages_no_longer_advertise_an_installable_app(): void
    {
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();
        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        $html = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // No manifest link => not installable, no "Install app" prompt.
        $this->assertStringNotContainsString('rel="manifest"', $html);
        $this->assertStringNotContainsString('manifest.webmanifest', $html);
        // The push service worker is still registered, and theme-color remains.
        $this->assertStringContainsString("register('/sw.js')", $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        // Fulfillment team gets the new-client desktop notifier.
        $this->assertStringContainsString('new-clients-poll', $html);
    }

    public function test_new_clients_poll_is_scoped_to_the_data_owner(): void
    {
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();

        $other = new Admin(['email' => 'o@t.com', 'password' => 'x', 'full_name' => 'O']);
        $other->role = 'super';
        $other->save();

        $mkBo = fn (Admin $owner, string $email) => Client::create([
            'admin_id' => $owner->id, 'business_name' => 'BO ' . $email, 'email' => $email,
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
        $mine = $mkBo($super, 'mine@t.com');
        $theirs = $mkBo($other, 'theirs@t.com');

        $mkEu = fn (Client $bo, string $first, ?string $intake) => EndUser::create([
            'client_id' => $bo->id, 'first_name' => $first, 'last_name' => 'X', 'suffix' => 'None',
            'email' => strtolower($first) . '@t.com', 'current_address' => '1 St', 'city' => 'T',
            'state' => 'ST', 'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => $intake, 'rounds' => ['1st Round'],
        ]);
        $newbie  = $mkEu($mine, 'Newbie', 'pending_review');   // should notify
        $mkEu($mine, 'Working', null);                         // in progress — no
        $mkEu($theirs, 'NotMine', 'pending_review');           // other owner — no

        $json = $this->actingAs($super, 'admin')
            ->getJson('/admin/new-clients-poll')
            ->assertOk()
            ->json();

        $this->assertSame(1, $json['count']);
        $this->assertSame([$newbie->id], array_column($json['clients'], 'id'));
    }

    public function test_bo_portal_is_not_wired_for_the_team_app(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/client.blade.php'));
        $this->assertStringNotContainsString('partials.pwa', $layout);
    }
}
