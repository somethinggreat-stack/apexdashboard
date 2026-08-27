<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard is an installable desktop PWA for the internal team: the team
 * layouts ship the manifest + install button + service worker, and the BO
 * portal deliberately does NOT (BOs aren't the team).
 */
class PwaInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_static_files_exist_and_manifest_is_scoped_to_team(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('Images/pwa/icon-512.png'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertIsArray($manifest);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/admin/', $manifest['scope']);
        $this->assertStringStartsWith('/admin/', $manifest['start_url']);
    }

    public function test_team_pages_are_installable(): void
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

        $this->assertStringContainsString('manifest.webmanifest', $html);
        $this->assertStringContainsString('apexInstallBtn', $html);
        $this->assertStringContainsString("register('/sw.js'", $html);
        $this->assertStringContainsString('name="theme-color"', $html);
    }

    public function test_bo_portal_is_not_wired_for_the_team_app(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/client.blade.php'));
        $this->assertStringNotContainsString('partials.pwa', $layout);
    }
}
