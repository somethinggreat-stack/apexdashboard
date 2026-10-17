<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\SessionError;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CSRF checking switched ON for these tests (Laravel skips it in tests by default). */
class StrictCsrfForTests extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

/**
 * "419 Page Expired" must never reach a person: a stale login form goes back to
 * that login (email kept), a stale form while still signed in goes back to the
 * same page, every 419 is recorded for the Session Errors page, and pages can
 * always fetch a fresh token.
 */
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $va;
    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(ValidateCsrfToken::class, StrictCsrfForTests::class);

        $this->super = new Admin(['email' => 'super@t.com', 'password' => 'secret123', 'full_name' => 'Super']);
        $this->super->role = 'super';
        $this->super->save();

        $this->va = new Admin(['email' => 'va@t.com', 'password' => 'secret123', 'full_name' => 'Vee']);
        $this->va->role = 'va';
        $this->va->parent_admin_id = $this->super->id;
        $this->va->save();

        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'BO', 'email' => 'owner@t.com',
            'password' => 'secret123', 'monthly_fee' => 0, 'status' => 'active',
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    public function test_token_endpoint_returns_a_fresh_uncached_token(): void
    {
        $res = $this->getJson(route('session.token'))->assertOk();
        $this->assertNotEmpty($res->json('token'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_stale_owner_login_goes_back_to_login_with_email_kept_and_is_logged(): void
    {
        $this->post('/business-owner/login', ['email' => 'owner@t.com', 'password' => 'secret123', '_token' => 'stale'])
            ->assertRedirect('/business-owner/login')
            ->assertSessionHas('status')
            ->assertSessionHasInput('email', 'owner@t.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertDatabaseHas('session_errors', ['area' => 'portal', 'path' => '/business-owner/login', 'method' => 'POST']);
    }

    public function test_stale_admin_and_chat_logins_go_back_to_their_login(): void
    {
        $this->post('/admin/login', ['email' => 'va@t.com', 'password' => 'x', '_token' => 'stale'])
            ->assertRedirect('/admin/login');
        $this->withHeader('User-Agent', 'ApexDesktop/1.0')->post('/admin/chat-login', ['email' => 'va@t.com', 'password' => 'x', '_token' => 'stale'])
            ->assertRedirect('/admin/chat-login');

        $this->assertSame(1, SessionError::where('area', 'admin')->count());
        $this->assertSame(1, SessionError::where('area', 'chat')->count());
    }

    public function test_the_retry_after_a_stale_login_succeeds(): void
    {
        $this->post('/business-owner/login', ['email' => 'owner@t.com', 'password' => 'secret123', '_token' => 'stale'])
            ->assertRedirect('/business-owner/login');

        // The login page now has a fresh token in this session; submitting with it works.
        $this->get('/business-owner/login')->assertOk();
        $this->post('/business-owner/login', ['email' => 'owner@t.com', 'password' => 'secret123', '_token' => csrf_token()])
            ->assertRedirect();
        $this->assertAuthenticatedAs($this->bo, 'client');
    }

    public function test_signed_in_user_with_a_stale_form_goes_back_to_the_same_page(): void
    {
        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->from('/admin/client-list')
            ->post(route('admin.end-users.store'), ['first_name' => 'Kept', '_token' => 'stale'])
            ->assertRedirect('/admin/client-list')
            ->assertSessionHas('confirm_error')
            ->assertSessionHasInput('first_name', 'Kept');

        $this->assertDatabaseHas('session_errors', ['area' => 'admin', 'method' => 'POST']);
    }

    public function test_signed_in_owner_with_a_stale_form_goes_back_to_the_same_page(): void
    {
        $this->actingAs($this->bo, 'client')
            ->from('/business-owner/dashboard')
            ->post('/business-owner/end-users', ['first_name' => 'Kept', '_token' => 'stale'])
            ->assertRedirect('/business-owner/dashboard')
            ->assertSessionHas('confirm_error');

        $this->assertDatabaseHas('session_errors', ['area' => 'portal']);
    }

    public function test_chat_subdomain_can_reach_the_token_endpoint(): void
    {
        config(['app.chat_host' => 'chat.example.test']);

        $this->getJson('http://chat.example.test/session/token')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_background_requests_keep_their_419_json_but_are_logged(): void
    {
        $this->actingAs($this->va, 'admin')
            ->postJson(route('admin.end-users.store'), ['_token' => 'stale'])
            ->assertStatus(419);

        $this->assertDatabaseHas('session_errors', ['was_ajax' => true]);
    }

    public function test_login_pages_are_never_cached_and_fetch_a_fresh_token(): void
    {
        foreach (['/business-owner/login', '/admin/login', '/admin/chat-login'] as $url) {
            $res = $this->withHeader('User-Agent', 'ApexDesktop/1.0')->get($url)->assertOk();
            $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'), $url);
            $res->assertSee('data-fresh-token', false)->assertSee('session-keepalive.js', false);
        }
    }

    public function test_session_errors_page_is_super_admin_only(): void
    {
        SessionError::create([
            'area' => 'portal', 'method' => 'POST', 'path' => '/business-owner/login', 'host' => 'apexgrowthsolution.com',
            'secure' => true, 'had_cookie' => true, 'reason' => 'session_expired', 'device' => 'iPhone · Safari',
            'created_at' => now(),
        ]);

        $this->actingAs($this->super, 'admin')->get(route('admin.session-errors.index'))
            ->assertOk()->assertSee('Session Errors')->assertSee('iPhone · Safari')->assertSee('/business-owner/login');

        $this->actingAs($this->va, 'admin')->get(route('admin.session-errors.index'))->assertForbidden();
    }

    public function test_device_labels(): void
    {
        $this->assertSame('iPhone · WhatsApp', SessionError::describeDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) WhatsApp/2.24'));
        $this->assertSame('Windows · Chrome', SessionError::describeDevice('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537'));
        $this->assertSame('Apex desktop app', SessionError::describeDevice('ApexDesktop/1.2 Electron'));
        $this->assertSame('Unknown', SessionError::describeDevice(''));
    }
}
