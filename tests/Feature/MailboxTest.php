<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Mailbox;
use App\Services\Cpanel\CpanelMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Mailboxes — the throwaway cPanel addresses VAs create for CFPB one-time codes.
 *
 * cPanel is never really called: Http::fake() stands in for it, and the fakes
 * answer in cPanel's own shape (HTTP 200 with status:0 for a refusal), because
 * mistaking that for success is the specific way this integration would break.
 */
class MailboxTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $va;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cpanel.host'        => 'cpanel.test',
            'cpanel.port'        => 2083,
            'cpanel.user'        => 'apexgrow',
            'cpanel.token'       => 'test-token',
            'cpanel.mail_domain' => 'apexgrowthsolution.com',
            'cpanel.quota_mb'    => 100,
        ]);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();

        $this->va = new Admin(['email' => 'dogar@test.com', 'password' => 'secret-pass', 'full_name' => 'Ubaid Dogar']);
        $this->va->role = 'va';
        $this->va->parent_admin_id = $this->super->id;
        $this->va->save();
    }

    private function grantMailboxes(Admin $admin): Admin
    {
        $admin->can_manage_mailboxes = true;
        $admin->save();

        return $admin;
    }

    /** cPanel answering "yes" to everything. */
    private function fakeOk(): void
    {
        Http::fake(['cpanel.test:2083/*' => Http::response(['status' => 1, 'errors' => null, 'data' => []], 200)]);
    }

    private function endUser(?Admin $owner = null): EndUser
    {
        $client = Client::create([
            'admin_id' => ($owner ?? $this->super)->id,
            'business_name' => 'Genius Credit Boutique ' . uniqid(),
            'email' => uniqid() . '@test.com', 'password' => 'secret-pass',
            'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
        ]);

        return EndUser::create([
            'client_id' => $client->id, 'first_name' => 'Nazeer', 'last_name' => 'Mohamed',
            'email' => uniqid() . '@example.com', 'status' => 'active', 'intake_status' => 'done',
            'start_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    // ------------------------------------------------------------------ access

    public function test_a_va_without_the_flag_cannot_reach_the_page_at_all(): void
    {
        $this->actingAs($this->va, 'admin')->get('/admin/mailboxes')->assertForbidden();
        $this->actingAs($this->va, 'admin')->post('/admin/mailboxes')->assertForbidden();
    }

    public function test_a_granted_va_can_reach_it_and_the_super_admin_never_needs_the_flag(): void
    {
        $this->actingAs($this->grantMailboxes($this->va), 'admin')->get('/admin/mailboxes')->assertOk();

        $this->assertFalse((bool) $this->super->can_manage_mailboxes);
        $this->actingAs($this->super, 'admin')->get('/admin/mailboxes')->assertOk();
    }

    public function test_the_sidebar_link_is_invisible_until_access_is_granted(): void
    {
        // Another VA page, so the sidebar renders either way.
        $this->actingAs($this->va, 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertDontSee('Mailboxes');

        $this->actingAs($this->grantMailboxes($this->va), 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertSee('Mailboxes');
    }

    // ------------------------------------------------------------------ create

    public function test_creating_a_mailbox_calls_cpanel_and_records_it(): void
    {
        $this->fakeOk();
        $endUser = $this->endUser();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', ['end_user_id' => $endUser->id])
            ->assertRedirect();

        $mailbox = Mailbox::firstOrFail();

        $this->assertSame('apexgrowthsolution.com', $mailbox->domain);
        $this->assertSame($mailbox->local_part . '@apexgrowthsolution.com', $mailbox->address);
        $this->assertSame(100, $mailbox->quota_mb);
        $this->assertSame($this->va->id, $mailbox->created_by_admin_id);
        $this->assertSame($this->super->id, $mailbox->admin_id, 'the VA\'s org owns it, not the VA');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/execute/Email/add_pop')
                && $request['quota'] === 100
                && $request['domain'] === 'apexgrowthsolution.com';
        });
    }

    /**
     * THE guarantee of this page: creating a mailbox must not change one byte of
     * the client's own record. Asserted over the whole row rather than over
     * cfpb_email alone, so a future convenience ("while we're here, also set…")
     * cannot slip in unnoticed.
     */
    public function test_creating_a_mailbox_changes_nothing_on_the_client(): void
    {
        $this->fakeOk();
        $endUser = $this->endUser();
        $endUser->forceFill(['cfpb_email' => 'untouched@example.com'])->save();

        $before = DB::table('end_users')->where('id', $endUser->id)->first();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', ['end_user_id' => $endUser->id])
            ->assertRedirect();

        $after = DB::table('end_users')->where('id', $endUser->id)->first();

        $this->assertEquals((array) $before, (array) $after, 'the client record was modified');
        $this->assertSame('untouched@example.com', $endUser->fresh()->cfpb_email);
        $this->assertSame(1, Mailbox::count());
    }

    public function test_deleting_a_mailbox_changes_nothing_on_the_client(): void
    {
        $this->fakeOk();
        $endUser = $this->endUser();
        $va      = $this->grantMailboxes($this->va);

        $this->actingAs($va, 'admin')->post('/admin/mailboxes', ['end_user_id' => $endUser->id]);
        $before = DB::table('end_users')->where('id', $endUser->id)->first();

        $this->actingAs($va, 'admin')->delete('/admin/mailboxes/' . Mailbox::firstOrFail()->id);

        $this->assertEquals(
            (array) $before,
            (array) DB::table('end_users')->where('id', $endUser->id)->first(),
            'the client record was modified'
        );
    }

    public function test_the_mailbox_name_comes_from_the_clients_first_and_last_name(): void
    {
        $this->fakeOk();
        $endUser = $this->endUser();
        // A middle name and suffix exist on the model but must not reach the address.
        $endUser->forceFill(['middle_name' => 'Abdul', 'suffix' => 'Jr'])->save();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', ['end_user_id' => $endUser->id]);

        $local = Mailbox::firstOrFail()->local_part;

        $this->assertMatchesRegularExpression('/^nazeer\.mohamed\d{4}$/', $local, "got '{$local}'");
    }

    public function test_the_password_is_not_readable_in_the_database(): void
    {
        $this->fakeOk();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')->post('/admin/mailboxes', []);

        $mailbox = Mailbox::firstOrFail();
        $raw     = DB::table('mailboxes')->where('id', $mailbox->id)->value('password');

        $this->assertNotSame($mailbox->password, $raw, 'the password was stored in plain text');
        $this->assertNotEmpty($mailbox->password);
    }

    public function test_a_client_from_another_organisation_is_refused(): void
    {
        $this->fakeOk();

        $otherSuper = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Someone Else']);
        $otherSuper->role = 'super';
        $otherSuper->save();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', ['end_user_id' => $this->endUser($otherSuper)->id])
            ->assertSessionHasErrors('mailbox');

        $this->assertSame(0, Mailbox::count());
        Http::assertNothingSent();
    }

    /**
     * THE failure mode of this integration: UAPI refuses with HTTP 200 and
     * status:0. Reading that as success records a mailbox that does not exist,
     * and the VA gets a login that silently never works.
     */
    public function test_a_cpanel_refusal_is_not_recorded_as_a_mailbox(): void
    {
        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 0,
            'errors' => ['The email account already exists.'],
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', [])
            ->assertSessionHasErrors('mailbox');

        $this->assertSame(0, Mailbox::count(), 'a refused mailbox was recorded anyway');
    }

    public function test_cpanel_being_unreachable_does_not_record_a_mailbox(): void
    {
        Http::fake(['cpanel.test:2083/*' => Http::response('', 500)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', [])
            ->assertSessionHasErrors('mailbox');

        $this->assertSame(0, Mailbox::count());
    }

    public function test_there_is_no_limit_on_how_many_mailboxes_can_be_created(): void
    {
        // Asked for explicitly: VAs create as many as the work needs. The only
        // ceiling left is the hosting account's disk.
        $this->fakeOk();
        $va = $this->grantMailboxes($this->va);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($va, 'admin')->post('/admin/mailboxes', [])->assertSessionHasNoErrors();
        }

        $this->assertSame(30, Mailbox::count());
    }

    public function test_two_clients_with_the_same_name_get_different_addresses(): void
    {
        $this->fakeOk();
        $va = $this->grantMailboxes($this->va);

        $this->actingAs($va, 'admin')->post('/admin/mailboxes', ['local_part' => 'nazeer.mohamed']);
        $this->actingAs($va, 'admin')->post('/admin/mailboxes', ['local_part' => 'nazeer.mohamed']);

        $this->assertSame(2, Mailbox::distinct('address')->count('address'));
    }

    // ------------------------------------------------------------------ delete

    public function test_deleting_removes_it_from_cpanel_and_keeps_the_audit_row(): void
    {
        $this->fakeOk();
        $va = $this->grantMailboxes($this->va);

        $this->actingAs($va, 'admin')->post('/admin/mailboxes', []);
        $mailbox = Mailbox::firstOrFail();

        $this->actingAs($va, 'admin')->delete('/admin/mailboxes/' . $mailbox->id)->assertRedirect();

        $mailbox->refresh();
        $this->assertNotNull($mailbox->deleted_at, 'the row was not marked deleted');
        $this->assertSame($va->id, $mailbox->deleted_by_admin_id);
        $this->assertSame(1, Mailbox::count(), 'the audit row was thrown away');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/execute/Email/delete_pop'));
    }

    public function test_a_failed_delete_leaves_the_mailbox_alive_in_our_records(): void
    {
        // Marking it deleted when cPanel still has it would hide a live mailbox
        // nobody can find again — and it keeps consuming disk.
        //
        // The row is made directly rather than through the create route: Http::fake()
        // MERGES stubs rather than replacing them, so a "create ok, delete refused"
        // pair in one test silently gets the ok response twice and proves nothing.
        $va = $this->grantMailboxes($this->va);

        $mailbox = Mailbox::create([
            'admin_id' => $this->super->id, 'created_by_admin_id' => $va->id,
            'local_part' => 'stubborn1234', 'domain' => 'apexgrowthsolution.com',
            'address' => 'stubborn1234@apexgrowthsolution.com', 'password' => 'x', 'quota_mb' => 100,
        ]);

        Http::fake(['cpanel.test:2083/*' => Http::response(['status' => 0, 'errors' => ['Nope.']], 200)]);

        $this->actingAs($va, 'admin')->delete('/admin/mailboxes/' . $mailbox->id)
            ->assertSessionHasErrors('mailbox');

        $this->assertNull($mailbox->fresh()->deleted_at);
    }

    public function test_one_organisation_cannot_delete_anothers_mailbox(): void
    {
        $otherSuper = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Someone Else']);
        $otherSuper->role = 'super';
        $otherSuper->save();

        $theirs = Mailbox::create([
            'admin_id' => $otherSuper->id, 'created_by_admin_id' => $otherSuper->id,
            'local_part' => 'theirs1234', 'domain' => 'apexgrowthsolution.com',
            'address' => 'theirs1234@apexgrowthsolution.com', 'password' => 'x', 'quota_mb' => 100,
        ]);

        $this->fakeOk();
        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->delete('/admin/mailboxes/' . $theirs->id)
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->deleted_at);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------- the service itself

    public function test_the_service_refuses_the_real_business_addresses(): void
    {
        $this->fakeOk();
        $cpanel = new CpanelMail();

        foreach (['hello', 'billing', 'support', 'HELLO'] as $protected) {
            try {
                $cpanel->deleteMailbox($protected);
                $this->fail("deleteMailbox('{$protected}') was allowed through");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('protected', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_the_service_refuses_an_unlimited_quota(): void
    {
        // cPanel reads 0 as unlimited — the one value that defeats the point.
        $this->fakeOk();

        $this->expectException(RuntimeException::class);
        (new CpanelMail())->createMailbox('somebody1234', 'pw', null, 0);
    }

    public function test_the_service_refuses_a_malformed_mailbox_name(): void
    {
        $this->fakeOk();
        $cpanel = new CpanelMail();

        foreach (['has space', 'semi;colon', 'quote"mark', '../escape', ''] as $bad) {
            try {
                $cpanel->createMailbox($bad, 'pw');
                $this->fail("createMailbox('{$bad}') was allowed through");
            } catch (RuntimeException $e) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_usage_survives_cpanel_being_down(): void
    {
        // Sizes are a nicety; the page must still render for a VA mid-shift.
        Http::fake(['cpanel.test:2083/*' => Http::response('', 503)]);

        $this->assertSame([], (new CpanelMail())->usage());

        $this->actingAs($this->grantMailboxes($this->va), 'admin')->get('/admin/mailboxes')->assertOk();
    }

    public function test_nothing_is_attempted_when_cpanel_is_not_configured(): void
    {
        config(['cpanel.token' => null]);
        Http::fake();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes', [])
            ->assertSessionHasErrors('mailbox');

        $this->assertSame(0, Mailbox::count());
        Http::assertNothingSent();

        // The page still loads and says why.
        $this->actingAs($this->va, 'admin')->get('/admin/mailboxes')
            ->assertOk()->assertSee('not switched on', false);
    }

    public function test_the_api_token_never_reaches_the_page(): void
    {
        $this->fakeOk();
        $this->actingAs($this->grantMailboxes($this->va), 'admin')->post('/admin/mailboxes', []);

        $this->actingAs($this->va, 'admin')->get('/admin/mailboxes')
            ->assertOk()
            ->assertDontSee('test-token', false)
            ->assertDontSee('apexgrow:', false);
    }

    // ------------------------------------------------------ one-click webmail

    /** A mailbox row, made directly so Http::fake() stubs stay unambiguous. */
    private function row(?Admin $org = null, ?Admin $by = null): Mailbox
    {
        $org ??= $this->super;

        return Mailbox::create([
            'admin_id' => $org->id, 'created_by_admin_id' => ($by ?? $this->va)->id,
            'local_part' => 'affe.hunte1317', 'domain' => 'apexgrowthsolution.com',
            'address' => 'affe.hunte1317@apexgrowthsolution.com',
            'password' => 'Str0ngPassw0rd!x', 'quota_mb' => 100,
        ]);
    }

    /**
     * Every argument and every piece of the URL below was measured against the
     * live cPanel, because each wrong shape fails in a way that still looks
     * plausible: the full address sends cPanel looking for "name@domain@domain",
     * a missing domain is refused outright, and a URL without the /cpsess…/
     * token answers 401 with a password form -- signed in to nothing.
     */
    public function test_the_webmail_button_signs_the_va_in_without_a_password(): void
    {
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1,
            'data'   => ['session' => 'affe.hunte1317@apexgrowthsolution.com:e3IdLh0:CREATE_WEBMAIL_SESSION',
                         'token'   => '/cpsess1234567890'],
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
            ->assertRedirect(
                'https://cpanel.test:2096/cpsess1234567890/login/?session='
                . rawurlencode('affe.hunte1317@apexgrowthsolution.com:e3IdLh0:CREATE_WEBMAIL_SESSION')
                . '&goto_uri=' . rawurlencode('/3rdparty/roundcube/index.php')
            );

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/execute/Session/create_webmail_session_for_mail_user')
                && $request['login']   === 'affe.hunte1317'          // local part, NOT the address
                && $request['domain']  === 'apexgrowthsolution.com'  // separately, or cPanel refuses
                && $request['service'] === 'webmaild';
        });
    }

    public function test_the_security_token_is_required_not_assumed(): void
    {
        // Without /cpsess…/ in the path, webmail answers 401 and shows a password
        // form. Redirecting there anyway would look like the feature was broken.
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1, 'data' => ['session' => 'a:b:c'],   // no token
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
            ->assertSessionHasErrors('mailbox');
    }

    /**
     * The token lands in the URL PATH, so it is checked rather than trusted.
     * A reply that smuggled something else in there would be writing part of
     * the address a VA is about to be sent to.
     */
    public function test_a_token_that_is_not_a_cpsess_segment_is_refused(): void
    {
        $mailbox = $this->row();

        foreach (['//evil.example.com', '/cpsess1/../..', 'cpsess123', '/nope123'] as $bad) {
            Http::fake(['cpanel.test:2083/*' => Http::response([
                'status' => 1, 'data' => ['session' => 'a:b:c', 'token' => $bad],
            ], 200)]);

            $this->actingAs($this->grantMailboxes($this->va), 'admin')
                ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
                ->assertSessionHasErrors('mailbox');
        }
    }

    public function test_the_host_is_always_ours_whatever_cpanel_replies(): void
    {
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1,
            'data'   => ['session' => 'a:b:c', 'token' => '/cpsess9', 'hostname' => 'evil.example.com',
                         'url' => 'https://evil.example.com/login/?session=a'],
        ], 200)]);

        $res = $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail');

        $this->assertStringStartsWith('https://cpanel.test:2096/cpsess9/', $res->headers->get('Location'));
        $this->assertStringNotContainsString('evil.example.com', (string) $res->headers->get('Location'));
    }

    public function test_the_webmail_redirect_is_never_cached(): void
    {
        // It carries a live session; a cached copy in a shared browser is a
        // signed-in mailbox for whoever presses Back.
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1, 'data' => ['session' => 'a:b:c', 'token' => '/cpsess9'],
        ], 200)]);

        $res = $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail');

        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $res->headers->get('Referrer-Policy'));
    }

    public function test_one_organisation_cannot_open_anothers_webmail(): void
    {
        $otherSuper = new Admin(['email' => 'other2@test.com', 'password' => 'secret-pass', 'full_name' => 'Someone Else']);
        $otherSuper->role = 'super';
        $otherSuper->save();

        $theirs = $this->row($otherSuper, $otherSuper);
        Http::fake();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $theirs->id . '/webmail')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_deleted_mailbox_cannot_be_signed_into(): void
    {
        $mailbox = $this->row();
        $mailbox->forceFill(['deleted_at' => now()])->save();
        Http::fake();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_va_without_mailbox_access_cannot_sign_into_webmail(): void
    {
        $mailbox = $this->row();

        $this->actingAs($this->va, 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
            ->assertForbidden();
    }

    public function test_a_refused_session_explains_itself_instead_of_breaking(): void
    {
        // Some hosts disable this API. The VA still has the address and password
        // on the row, so the page must say so rather than dead-end.
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 0,
            'errors' => ['Session creation is not permitted.'],
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail')
            ->assertSessionHasErrors('mailbox');
    }

    public function test_signing_into_webmail_changes_nothing(): void
    {
        $mailbox = $this->row();
        $before  = DB::table('mailboxes')->where('id', $mailbox->id)->first();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1, 'data' => ['session' => 'a@b.com:tok'],
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail');

        $this->assertEquals(
            (array) $before,
            (array) DB::table('mailboxes')->where('id', $mailbox->id)->first(),
            'opening webmail modified the mailbox row'
        );
    }

    public function test_the_webmail_session_never_reaches_the_mailbox_list(): void
    {
        $mailbox = $this->row();

        Http::fake(['cpanel.test:2083/*' => Http::response([
            'status' => 1, 'data' => ['session' => 'a@b.com:supersecrettoken'],
        ], 200)]);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->post('/admin/mailboxes/' . $mailbox->id . '/webmail');

        $this->actingAs($this->va, 'admin')->get('/admin/mailboxes')
            ->assertOk()->assertDontSee('supersecrettoken', false);
    }

    // ---------------------------------------------------------- the client search

    public function test_the_search_finds_a_client_by_first_name_last_name_or_email(): void
    {
        $endUser = $this->endUser();                       // Nazeer Mohamed
        $va      = $this->grantMailboxes($this->va);

        foreach (['Nazeer', 'Mohamed', 'nazeer mohamed', 'mohamed nazeer'] as $term) {
            $this->actingAs($va, 'admin')->getJson('/admin/mailboxes/clients?q=' . urlencode($term))
                ->assertOk()
                ->assertJsonPath('results.0.id', $endUser->id);
        }

        // and by the address on the record
        $this->actingAs($va, 'admin')
            ->getJson('/admin/mailboxes/clients?q=' . urlencode($endUser->email))
            ->assertOk()->assertJsonPath('results.0.id', $endUser->id);
    }

    public function test_the_search_reaches_across_every_business_owner(): void
    {
        // Two owners under the same super admin; neither is "selected" in session.
        $a = $this->endUser();
        $b = $this->endUser();
        $b->forceFill(['first_name' => 'Jeremie', 'last_name' => 'Mufundu'])->save();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->getJson('/admin/mailboxes/clients?q=Jeremie')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $b->id);

        $this->assertNotSame($a->client_id, $b->client_id, 'the two clients should sit under different owners');
    }

    public function test_the_search_never_reaches_another_organisations_clients(): void
    {
        $otherSuper = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Someone Else']);
        $otherSuper->role = 'super';
        $otherSuper->save();

        $theirs = $this->endUser($otherSuper);

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->getJson('/admin/mailboxes/clients?q=Nazeer')
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->assertNotNull($theirs->id);
    }

    public function test_the_search_is_closed_to_a_va_without_mailbox_access(): void
    {
        $this->actingAs($this->va, 'admin')->get('/admin/mailboxes/clients?q=Nazeer')->assertForbidden();
    }

    public function test_an_empty_search_returns_nothing_rather_than_the_whole_client_list(): void
    {
        $this->endUser();

        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->getJson('/admin/mailboxes/clients?q=')
            ->assertOk()->assertJsonCount(0, 'results');
    }

    // ------------------------------------------------------------------ the page

    /**
     * The create dialog must start CLOSED.
     *
     * It shipped with `hidden` plus an inline `display:flex`. An inline style
     * outranks the [hidden] rule, so the dialog sat on top of the page from the
     * moment it loaded and Cancel appeared to do nothing — there was nothing to
     * hide, it had never been hidden. Asserted as the general rule, because the
     * next modal added to this page would hit it too.
     */
    public function test_no_element_on_the_page_is_hidden_while_also_being_displayed(): void
    {
        $html = $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->get('/admin/mailboxes')->assertOk()->getContent();

        // Every tag carrying the `hidden` attribute, in either attribute order.
        preg_match_all('/<[a-z][^>]*\shidden[\s>][^>]*>|<[a-z][^>]*\shidden$/im', $html, $m);

        foreach ($m[0] as $tag) {
            if (preg_match('/style="[^"]*display\s*:\s*(?!none)([a-z-]+)/i', $tag, $hit)) {
                $this->fail("A hidden element is forced visible by display:{$hit[1]} — " . trim($tag));
            }
        }

        // The dialog starts closed through its class, and nothing inline may
        // override that back to visible.
        $this->assertMatchesRegularExpression(
            '/id="mbModal"[^>]*class="[^"]*mb-scrim/s',
            $html,
            'the create dialog is not using the closed-by-default scrim'
        );
        $this->assertMatchesRegularExpression(
            '/\.mb-scrim\s*\{[^}]*display\s*:\s*none/s',
            $html,
            'the scrim does not start at display:none'
        );

        preg_match('/<div id="mbModal"[^>]*>/', $html, $tag);
        $this->assertNotEmpty($tag, 'the dialog element was not found');
        $this->assertDoesNotMatchRegularExpression(
            '/style="[^"]*display\s*:\s*(?!none)/i',
            $tag[0],
            'an inline display on the dialog overrides its closed state'
        );
    }

    // -------------------------------------------------------------- the toggle

    public function test_the_super_admin_grants_and_revokes_mailbox_access(): void
    {
        $this->actingAs($this->super, 'admin')
            ->put('/admin/users/' . $this->va->id . '/mailbox-access')->assertRedirect();
        $this->assertTrue((bool) $this->va->fresh()->can_manage_mailboxes);

        $this->actingAs($this->super, 'admin')
            ->put('/admin/users/' . $this->va->id . '/mailbox-access')->assertRedirect();
        $this->assertFalse((bool) $this->va->fresh()->can_manage_mailboxes);
    }

    public function test_a_va_cannot_grant_themselves_mailbox_access(): void
    {
        $this->actingAs($this->grantMailboxes($this->va), 'admin')
            ->put('/admin/users/' . $this->va->id . '/mailbox-access')
            ->assertForbidden();
    }
}
