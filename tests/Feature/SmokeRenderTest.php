<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end render smoke test: seed both round-cycle types with clients in
 * every bucket + process steps, then GET every major admin and business-owner
 * page and assert none of them error (< 500).
 */
class SmokeRenderTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo20;
    private Client $bo30;

    private function seedWorld(): void
    {
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $this->super->role = 'super';
        $this->super->save();

        $this->bo20 = $this->makeBo('Twenty Owner', 'bo20@test.com', 20);
        $this->bo30 = $this->makeBo('Thirty Owner', 'bo30@test.com', 30);
    }

    private function makeBo(string $name, string $email, int $cycle): Client
    {
        $bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name, 'email' => $email,
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'round_cycle_days' => $cycle, 'intake_enabled' => true,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        // One client in each bucket.
        foreach ([
            ['done',           null,              ['1st Round', '2nd Round']],
            [null,             null,              ['1st Round']],   // in progress (approved)
            ['round_error',    null,              ['1st Round', '2nd Round']],
            ['round_error',    now(),             ['1st Round', '2nd Round']],  // resolved by BO
            ['error',          null,              ['1st Round']],
            ['error',          now(),             ['1st Round']],   // new-error resolved
            ['pending_review', null,              ['1st Round']],
        ] as $i => [$status, $resolvedAt, $rounds]) {
            $eu = EndUser::create([
                'client_id' => $bo->id, 'first_name' => "C{$i}", 'last_name' => $name,
                'suffix' => 'None', 'email' => "c{$i}_{$email}", 'current_address' => '1 St',
                'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
                'start_date' => '2026-06-01', 'intake_status' => $status, 'rounds' => $rounds,
                'error_resolved_by_client_at' => $resolvedAt,
                'error_type' => $status === 'round_error' ? 'EX Login Error' : null,
                'credit_monitoring_name' => 'MyScoreIQ', 'credit_monitoring_username' => 'user',
            ]);

            // A couple of process steps so timelines/overview render with data.
            foreach (ProcessStep::stepTypesByWeek($cycle)[1] as $type => $label) {
                ProcessStep::create([
                    'end_user_id' => $eu->id, 'round' => 1, 'week' => 1,
                    'step_type' => $type, 'step_date' => '2026-06-02',
                    'created_by_admin_id' => $this->super->id,
                ]);
            }
        }

        // One held client.
        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Held', 'last_name' => $name, 'suffix' => 'None',
            'email' => "held_{$email}", 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => 'done', 'rounds' => ['1st Round'], 'held_at' => now(),
        ]);

        return $bo;
    }

    private function assertRenders(array $paths, string $guard, ?Admin $adminUser = null, ?Client $clientUser = null, array $session = []): void
    {
        foreach ($paths as $path) {
            $req = $this->withSession($session);
            $req = $guard === 'admin'
                ? $req->actingAs($adminUser, 'admin')
                : $req->actingAs($clientUser, 'client');

            $status = $req->get($path)->status();
            $this->assertLessThan(500, $status, "PAGE ERRORED ({$status}): {$path}");
        }
    }

    public function test_all_admin_pages_render_for_both_cycles(): void
    {
        $this->seedWorld();

        foreach ([$this->bo20, $this->bo30] as $bo) {
            $showId = EndUser::where('client_id', $bo->id)->where('intake_status', 'done')->value('id');

            $adminPaths = [
                '/admin/dashboard',
                '/admin/clients',
                '/admin/select-business-owner',
                '/admin/universal-search',
                '/admin/recycle-bin',
                '/admin/new-clients',
                '/admin/end-users',                     // In Progress list
                '/admin/client-list',                   // Clients list
                '/admin/round-errors',
                '/admin/errors',
                '/admin/errors-resolved-by-client',
                '/admin/errors-resolved-new-clients',
                '/admin/hold',
                '/admin/payments',
                "/admin/end-users/{$showId}",           // client profile (all tabs)
                "/admin/end-users/{$showId}/status-report",
            ];

            $this->assertRenders($adminPaths, 'admin', adminUser: $this->super, session: ['selected_client_id' => $bo->id]);
        }
    }

    public function test_all_business_owner_pages_render_for_both_cycles(): void
    {
        $this->seedWorld();

        foreach ([$this->bo20, $this->bo30] as $bo) {
            $showId = EndUser::where('client_id', $bo->id)->where('intake_status', 'done')->value('id');

            $boPaths = [
                '/business-owner/dashboard',
                '/business-owner/end-users',            // In Progress
                '/business-owner/client-list',          // Done Clients
                '/business-owner/errors',
                '/business-owner/round-errors',
                '/business-owner/errors-resolved',
                '/business-owner/errors-resolved-new',
                '/business-owner/hold',
                '/business-owner/billing',
                "/business-owner/end-users/{$showId}",
            ];

            $this->assertRenders($boPaths, 'client', clientUser: $bo);
        }
    }

    public function test_marketing_pages_render(): void
    {
        foreach (['/', '/about', '/contact', '/results', '/trial', '/admin/login', '/business-owner/login'] as $path) {
            $this->assertLessThan(500, $this->get($path)->status(), "Marketing page errored: {$path}");
        }
    }
}
