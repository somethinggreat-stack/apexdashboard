<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * JARVIS write endpoints, slice 1 (buckets and holds).
 *
 * The happy paths matter least. What these tests are actually for:
 * writes are off until switched on, a preview changes nothing, a retry doesn't
 * apply twice, the assistant is never mistaken for a human, and the assistant's
 * account can't be logged into or seen as a teammate.
 */
class JarvisWriteTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-jarvis-token-do-not-use-in-production';

    private Admin $super;

    private Client $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jarvis.token' => self::TOKEN, 'jarvis.writes_enabled' => true]);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();

        $this->owner = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Clinecea Phillips',
            'email' => 'owner@test.com', 'password' => 'secret-pass', 'status' => 'active',
            'monthly_fee' => 0, 'round_cycle_days' => 30, 'results_tracking' => true,
        ]);

        // The assistant account belongs to this org.
        Admin::where('email', config('jarvis.actor_email'))->update(['parent_admin_id' => $this->super->id]);
    }

    private function client(array $attr = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id' => $this->owner->id, 'first_name' => 'Dominique', 'last_name' => 'Johnson',
            'email' => 'd@example.com', 'status' => 'active',
            'start_date' => now()->subDays(40)->toDateString(),
            'rounds' => ['1st Round'], 'round_dates' => ['1st Round' => now()->subDays(40)->toDateString()],
        ], $attr));
    }

    /** Named `fire` — TestCase::post() already exists and is public. */
    private function fire(string $path, array $body = [], ?string $key = 'key-1')
    {
        $req = $this->withHeader('Authorization', 'Bearer ' . self::TOKEN);
        if ($key !== null) {
            $req = $req->withHeader('Idempotency-Key', $key);
        }

        return $req->postJson('/api/jarvis/clients/' . $path, $body);
    }

    // ---- the kill switch ------------------------------------------------------------------

    public function test_writes_are_refused_when_disabled(): void
    {
        config(['jarvis.writes_enabled' => false]);
        $e = $this->client();

        $this->fire($e->id . '/to-done')->assertStatus(503);

        $this->assertNull($e->fresh()->intake_status, 'a disabled write still changed the record');
    }

    public function test_writes_are_off_by_default(): void
    {
        // The shipped default must be off — switching them on is a deliberate act.
        $this->assertFalse((bool) config()->get('jarvis.writes_enabled', false) && false);
        $this->assertFalse((new \Illuminate\Config\Repository(require config_path('jarvis.php')))->get('writes_enabled'));
    }

    public function test_a_write_still_needs_the_token(): void
    {
        $e = $this->client();

        $this->withHeader('Idempotency-Key', 'k')->postJson('/api/jarvis/clients/' . $e->id . '/to-done')
            ->assertStatus(401);
    }

    // ---- preview --------------------------------------------------------------------------

    public function test_preview_reports_the_change_without_making_it(): void
    {
        $e = $this->client();

        $res = $this->fire($e->id . '/to-done?preview=true', [], null)->assertOk();

        $res->assertJsonPath('preview', true)
            ->assertJsonPath('would_change.intake_status.from', null)
            ->assertJsonPath('would_change.intake_status.to', 'done');

        // Nothing committed.
        $this->assertNull($e->fresh()->intake_status, 'preview actually changed the record');
        $this->assertSame(0, DB::table('jarvis_requests')->count(), 'preview recorded an idempotency row');
    }

    public function test_preview_fails_the_same_way_the_real_call_would(): void
    {
        // A preview that succeeds where the real call fails is worse than no preview:
        // it would be approved on the strength of something that cannot happen.
        $e = $this->client();
        Client::where('id', $this->owner->id)->update(['results_tracking' => false]);

        $this->fire($e->id . '/approve-round?preview=true', [], null)->assertStatus(409);
        $this->fire($e->id . '/approve-round')->assertStatus(409);
    }

    public function test_preview_needs_no_idempotency_key(): void
    {
        $e = $this->client();

        $this->fire($e->id . '/hold?preview=true', [], null)->assertOk();
    }

    // ---- idempotency ------------------------------------------------------------------------

    public function test_a_mutation_requires_an_idempotency_key(): void
    {
        $e = $this->client();

        $this->fire($e->id . '/to-done', [], null)->assertStatus(422);
    }

    public function test_a_retry_with_the_same_key_does_not_apply_twice(): void
    {
        $e = $this->client();

        $first = $this->fire($e->id . '/hold', [], 'retry-me')->assertOk();
        $heldAt = $e->fresh()->held_at;
        $this->assertNotNull($heldAt);

        $second = $this->fire($e->id . '/hold', [], 'retry-me')->assertOk();

        $second->assertJsonPath('replayed', true);
        $this->assertEquals($heldAt, $e->fresh()->held_at, 'the retry re-applied the change');
        $this->assertSame($first->json('request_id'), $second->json('request_id'));
        $this->assertSame(1, DB::table('jarvis_requests')->count());
    }

    // ---- the assistant's identity -------------------------------------------------------------

    public function test_the_assistant_account_cannot_be_logged_into(): void
    {
        $jarvis = Admin::where('email', config('jarvis.actor_email'))->firstOrFail();

        // No password can match: the stored value isn't a bcrypt hash at all, so
        // Hash::check either returns false or rejects it outright. Both are fine —
        // what must never happen is a guess succeeding.
        foreach (['', 'password', 'jarvis', 'no-login', $jarvis->password] as $guess) {
            $matched = false;
            try {
                $matched = \Illuminate\Support\Facades\Hash::check((string) $guess, (string) $jarvis->password);
            } catch (\Throwable $e) {
                $matched = false;
            }
            $this->assertFalse($matched, 'the assistant account accepted a password');
        }
        $this->assertStringStartsWith('no-login-', (string) $jarvis->password);
    }

    public function test_the_assistant_is_not_role_super(): void
    {
        // role DEFAULTS to 'super' on this table, and this row is created by code —
        // exactly the case that inherits the default silently.
        $jarvis = Admin::where('email', config('jarvis.actor_email'))->firstOrFail();

        $this->assertSame('system', $jarvis->role);
        $this->assertNotSame('super', $jarvis->role);
    }

    public function test_the_assistant_never_appears_as_a_teammate_in_chat(): void
    {
        $this->withHeader('User-Agent', 'ApexDesktop/1.0');
        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'Abid Hussain']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        $page = $this->actingAs($va, 'admin')->get('/admin/team-messages')->assertOk();

        $page->assertDontSee('JARVIS (assistant)');
    }

    public function test_the_assistant_is_never_the_owner_the_read_api_reports_on(): void
    {
        config(['jarvis.owner_admin_id' => null]);

        $snapshot = \App\Services\OwnerSnapshot::forSuperAdmin();
        $jarvis = Admin::where('email', config('jarvis.actor_email'))->firstOrFail();

        $ref = new \ReflectionProperty($snapshot, 'ownerAdminId');
        $ref->setAccessible(true);

        $this->assertNotSame($jarvis->id, $ref->getValue($snapshot));
    }

    public function test_a_write_is_attributed_to_the_assistant_not_a_human(): void
    {
        $e = $this->client();
        $this->fire($e->id . '/to-done')->assertOk();

        // The move is recorded, and the actor is the assistant account — Umair's own
        // activity log stays evidence of what HE did.
        $jarvis = Admin::where('email', config('jarvis.actor_email'))->firstOrFail();
        $this->assertDatabaseHas('client_events', ['end_user_id' => $e->id, 'admin_id' => $jarvis->id]);
    }

    // ---- the actions themselves ----------------------------------------------------------------

    public function test_bucket_moves(): void
    {
        $e = $this->client();

        $this->fire($e->id . '/to-done', [], 'k1')->assertOk();
        $this->assertSame('done', $e->fresh()->intake_status);

        $this->fire($e->id . '/to-round-error', ['error_type' => 'import'], 'k2')->assertOk();
        $this->assertSame('round_error', $e->fresh()->intake_status);

        $this->fire($e->id . '/resolve-round-error', [], 'k3')->assertOk();
        $this->assertNotSame('round_error', $e->fresh()->intake_status);
    }

    public function test_hold_and_resume_are_an_undo_pair(): void
    {
        $e = $this->client();

        $this->fire($e->id . '/hold', [], 'h1')->assertOk();
        $this->assertNotNull($e->fresh()->held_at);

        $this->fire($e->id . '/resume', [], 'h2')->assertOk();
        $this->assertNull($e->fresh()->held_at, 'resume did not undo the hold');
    }

    public function test_the_approval_flow_is_refused_for_an_owner_without_results_tracking(): void
    {
        Client::where('id', $this->owner->id)->update(['results_tracking' => false]);
        $e = $this->client();

        // A plain refusal, not a bare 404 — the feature simply isn't on for them.
        $this->fire($e->id . '/request-approval', [], 'a1')->assertStatus(409);
    }

    public function test_clear_approval_undoes_either_state(): void
    {
        $e = $this->client();

        $this->fire($e->id . '/request-approval', [], 'b1')->assertOk();
        $this->assertSame('awaiting', $e->fresh()->round_approval_status);

        $this->fire($e->id . '/approve-round', [], 'b2')->assertOk();
        $this->assertSame('approved', $e->fresh()->round_approval_status);

        $this->fire($e->id . '/clear-approval', [], 'b3')->assertOk();
        $this->assertNull($e->fresh()->round_approval_status);
    }

    public function test_approving_a_round_does_not_advance_it(): void
    {
        // The whole reason approve-round is low risk: it flips a flag and credits
        // nobody. If it ever starts growing the rounds array it becomes an advance
        // wearing a different name, and this test is the alarm.
        $e = $this->client();
        $before = $e->rounds;

        $this->fire($e->id . '/request-approval', [], 'c1')->assertOk();
        $this->fire($e->id . '/approve-round', [], 'c2')->assertOk();

        $this->assertSame($before, $e->fresh()->rounds, 'approve-round advanced the client');
        $this->assertSame(0, DB::table('round_selections')->count(), 'approve-round credited a round selection');
    }

    public function test_a_write_cannot_reach_another_organisations_client(): void
    {
        // These actions scope through session('selected_client_id'), and that helper
        // checks only the session value — not whose owner it is. On the dashboard the
        // selector never offers someone else's owner; here the org check is ours, so
        // this is the test that it exists.
        $otherAdmin = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other Owner']);
        $otherAdmin->role = 'super';
        $otherAdmin->save();

        $theirOwner = Client::create([
            'admin_id' => $otherAdmin->id, 'business_name' => 'Someone Else Ltd',
            'email' => 'them@test.com', 'password' => 'secret-pass', 'status' => 'active',
            'monthly_fee' => 0, 'round_cycle_days' => 30,
        ]);
        $theirClient = EndUser::create([
            'client_id' => $theirOwner->id, 'first_name' => 'Not', 'last_name' => 'Ours',
            'email' => 'n@example.com', 'status' => 'active', 'start_date' => now()->toDateString(),
        ]);

        $this->fire($theirClient->id . '/to-done', [], 'x1')->assertStatus(404);

        $this->assertNull($theirClient->fresh()->intake_status, 'reached into another org');
    }

    // ---- the shape of the surface ----------------------------------------------------------------

    public function test_no_write_endpoint_accepts_a_list_of_ids(): void
    {
        // Per-record only, so the consumer's volume counter sees every single change
        // and a loop can never hide as one call.
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/jarvis/clients')) {
                $this->assertStringNotContainsString('bulk', $route->uri());
                $this->assertStringContainsString('{id}', $route->uri());
            }
        }
    }

    public function test_the_read_api_is_unaffected_by_the_write_kill_switch(): void
    {
        config(['jarvis.writes_enabled' => false]);

        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->getJson('/api/jarvis/health')->assertOk();
    }
}
