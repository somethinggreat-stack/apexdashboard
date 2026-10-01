<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\GhlNumber;
use App\Models\GhlOtp;
use App\Services\Ghl\GhlNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phone Numbers — the shared pool a VA claims to collect a one-time code.
 *
 * GoHighLevel is never really called: Http::fake() stands in, answering in the
 * shapes measured against the live sub-account (search is a GET; an inbound
 * message carries `to` = our number and `from` = the sender).
 *
 * The two things that must never be wrong: two VAs can never hold one number,
 * and the delete can never reach a thread that is not purely a code thread.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $dogar;
    private Admin $raja;

    private const POOL  = '+12622610439';   // Alvina's number 6
    private const BJ    = '+12058392700';   // Bj's number — live customer line

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ghl_numbers.token'             => 'test-token',
            'ghl_numbers.location_id'       => 'LOC123',
            'ghl_numbers.base_url'          => 'https://ghl.test',
            'ghl_numbers.api_version'       => '2021-07-28',
            'ghl_numbers.excluded'          => [self::BJ],
            'ghl_numbers.name_pattern'      => '/^Alvina/i',
            'ghl_numbers.claim_minutes'     => 10,
            'ghl_numbers.delete_after_copy' => true,
        ]);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();

        foreach ([['dogar', 'Ubaid Dogar'], ['raja', 'Raja Khuram']] as [$prop, $name]) {
            $va = new Admin(['email' => $prop . '@test.com', 'password' => 'secret-pass', 'full_name' => $name]);
            $va->role = 'va';
            $va->parent_admin_id = $this->super->id;
            $va->can_manage_numbers = true;
            $va->save();
            $this->{$prop} = $va;
        }
    }

    private function number(string $phone = self::POOL, string $label = "Alvina's number 6"): GhlNumber
    {
        return GhlNumber::create(['phone' => $phone, 'label' => $label, 'ghl_sid' => 'PN1', 'active' => true]);
    }

    /** GHL answering with one inbound SMS to $to. */
    private function fakeInbound(string $to, string $body = 'Your CFPB code is 448213', ?int $whenMs = null): void
    {
        $whenMs ??= now()->getTimestampMs();

        Http::fake([
            'ghl.test/phone-system/numbers*' => Http::response(['phoneNumbers' => [
                ['sid' => 'PN1', 'value' => self::POOL, 'title' => "Alvina's number 6"],
                ['sid' => 'PN0', 'value' => self::BJ,   'title' => "Bj's number"],
            ]], 200),

            'ghl.test/conversations/search*' => Http::response(['conversations' => [
                ['id' => 'CONV1', 'lastMessageDate' => $whenMs, 'contactId' => 'C1'],
            ]], 200),

            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'MSG1', 'direction' => 'inbound', 'messageType' => 'TYPE_SMS',
                 'to' => $to, 'from' => '+15550001111', 'body' => $body,
                 'dateAdded' => now()->toIso8601String()],
            ]]], 200),

            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);
    }

    // ------------------------------------------------------------------ access

    public function test_a_va_without_the_flag_cannot_reach_the_page(): void
    {
        $this->dogar->forceFill(['can_manage_numbers' => false])->save();

        Http::fake();
        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')->assertForbidden();
        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers/poll')->assertForbidden();
    }

    public function test_the_sidebar_link_is_invisible_until_access_is_granted(): void
    {
        $this->dogar->forceFill(['can_manage_numbers' => false])->save();

        $this->actingAs($this->dogar, 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertDontSee('Phone Numbers');

        $this->dogar->forceFill(['can_manage_numbers' => true])->save();
        $this->actingAs($this->dogar, 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertSee('Phone Numbers');
    }

    // -------------------------------------------------------------- THE POOL

    /**
     * Bj's number is the sub-account's Default Number and carries live customer
     * conversations. It must never enter the pool, whatever GHL returns.
     */
    public function test_the_live_customer_number_never_enters_the_pool(): void
    {
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')->assertOk();

        $this->assertSame(1, GhlNumber::count());
        $this->assertSame(self::POOL, GhlNumber::first()->phone);
        $this->assertNull(GhlNumber::where('phone', self::BJ)->first(), 'Bj\'s number was synced into the pool');
    }

    public function test_a_number_must_pass_both_gates_to_be_in_the_pool(): void
    {
        $ghl = new GhlNumbers();

        $this->assertTrue($ghl->inPool(self::POOL, "Alvina's number 6"));
        $this->assertFalse($ghl->inPool(self::BJ, "Bj's number"),            'excluded number allowed');
        $this->assertFalse($ghl->inPool(self::BJ, "Alvina's number 99"),     'exclude list beaten by a name');
        $this->assertFalse($ghl->inPool('+15550009999', 'Support line'),     'name pattern ignored');
    }

    // ----------------------------------------------------------- THE LOCK

    public function test_two_vas_can_never_hold_the_same_number(): void
    {
        $number = $this->number();
        Http::fake();

        $this->actingAs($this->dogar, 'admin')->post('/admin/numbers/' . $number->id . '/claim');
        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id);

        // Raja tries for the same one.
        $this->actingAs($this->raja, 'admin')->post('/admin/numbers/' . $number->id . '/claim')
            ->assertSessionHasErrors('number');

        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id, 'the number changed hands');
    }

    /**
     * The claim is decided by the database, not by read-then-write. Proved by
     * calling claim() twice on two model instances loaded BEFORE either wrote —
     * which is what two simultaneous requests actually look like.
     */
    public function test_the_claim_is_decided_by_the_database_not_by_a_read(): void
    {
        $number = $this->number();

        $asDogar = GhlNumber::find($number->id);
        $asRaja  = GhlNumber::find($number->id);

        $this->assertTrue($asDogar->claim($this->dogar->id));
        $this->assertFalse($asRaja->claim($this->raja->id), 'both instances saw a free number and both took it');

        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id);
    }

    public function test_an_abandoned_claim_times_out_and_returns_to_the_pool(): void
    {
        // Without this, one VA closing their laptop retires a number for good.
        $number = $this->number();
        $number->claim($this->dogar->id);
        $number->forceFill(['claimed_at' => now()->subMinutes(11)])->save();

        $this->assertTrue($number->fresh()->isAvailable());
        $this->assertTrue(GhlNumber::find($number->id)->claim($this->raja->id));
    }

    public function test_a_va_cannot_release_a_number_someone_else_is_holding(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        Http::fake();

        $this->actingAs($this->raja, 'admin')->post('/admin/numbers/' . $number->id . '/release')
            ->assertSessionHasErrors('number');

        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id);

        // The super admin can, for when a VA has gone home holding one.
        $this->actingAs($this->super, 'admin')->post('/admin/numbers/' . $number->id . '/release');
        $this->assertNull($number->fresh()->claimed_by_admin_id);
    }

    public function test_vas_see_who_is_holding_a_number(): void
    {
        $number = $this->number();
        $number->claim($this->raja->id);
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')
            ->assertOk()
            ->assertSee('Raja Khuram')
            ->assertSee('In use', false);
    }

    // ----------------------------------------------------------- THE CODE

    public function test_the_code_reaches_the_va_holding_that_number(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);

        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()
            ->assertJsonPath('codes.0.code', '448213')
            ->assertJsonPath('codes.0.phone', self::POOL);

        // Recorded, with the whole message kept beside the digits.
        $otp = GhlOtp::firstOrFail();
        $this->assertSame('448213', $otp->code);
        $this->assertSame('Your CFPB code is 448213', $otp->body);
        $this->assertSame($this->dogar->id, $otp->claimed_by_admin_id);
    }

    public function test_a_code_never_reaches_a_va_who_is_not_holding_that_number(): void
    {
        // The whole point of the lock: Raja must not see Dogar's code.
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->raja, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_a_message_older_than_the_claim_is_not_treated_as_this_vas_code(): void
    {
        // Otherwise the previous VA's code is handed to whoever claims next.
        $number = $this->number();
        $number->claim($this->dogar->id);
        $number->forceFill(['claimed_at' => now()])->save();

        Http::fake([
            'ghl.test/phone-system/numbers*' => Http::response(['phoneNumbers' => [
                ['sid' => 'PN1', 'value' => self::POOL, 'title' => "Alvina's number 6"],
            ]], 200),
            'ghl.test/conversations/search*' => Http::response(['conversations' => [
                ['id' => 'CONV1', 'lastMessageDate' => now()->getTimestampMs(), 'contactId' => 'C1'],
            ]], 200),
            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'OLD1', 'direction' => 'inbound', 'messageType' => 'TYPE_SMS',
                 'to' => self::POOL, 'from' => '+15550001111', 'body' => 'code 111111',
                 'dateAdded' => now()->subMinutes(30)->toIso8601String()],
            ]]], 200),
            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_holding_nothing_never_calls_gohighlevel(): void
    {
        Http::fake();

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');

        Http::assertNothingSent();
    }

    // --------------------------------------------------------- COPY + DELETE

    public function test_copying_records_it_frees_the_number_and_deletes_the_thread(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll');
        $otp = GhlOtp::firstOrFail();

        $this->actingAs($this->dogar, 'admin')
            ->postJson('/admin/numbers/otp/' . $otp->id . '/copied')
            ->assertOk()->assertJsonPath('ok', true);

        $otp->refresh();
        $this->assertNotNull($otp->copied_at);
        $this->assertTrue($otp->deleted_from_ghl);
        $this->assertNull($number->fresh()->claimed_by_admin_id, 'the number was not released');

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/conversations/CONV1'));
    }

    /**
     * The fence. These threads sit beside real customer conversations — payment
     * promises, dispute questions, actual people — and GHL deletes the WHOLE
     * thread. A thread with any reply in it is not a code thread.
     */
    public function test_a_thread_with_replies_is_never_deleted(): void
    {
        $messages = [
            ['id' => 'M1', 'direction' => 'inbound',  'to' => self::POOL, 'from' => '+1555', 'body' => 'code 1234'],
            ['id' => 'M2', 'direction' => 'outbound', 'to' => '+1555', 'from' => self::POOL, 'body' => 'Hi Jermecia!'],
        ];

        Http::fake([
            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => $messages]], 200),
            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);

        $note = (new GhlNumbers())->deleteConversationSafely('CONV1', [self::POOL]);

        $this->assertNotNull($note, 'a thread with a reply in it was deleted');
        $this->assertStringContainsString('replies', $note);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_a_thread_touching_a_number_outside_the_pool_is_never_deleted(): void
    {
        Http::fake([
            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'M1', 'direction' => 'inbound', 'to' => self::BJ, 'from' => '+1555', 'body' => 'Thank you'],
            ]]], 200),
            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);

        $note = (new GhlNumbers())->deleteConversationSafely('CONV1', [self::POOL]);

        $this->assertNotNull($note, 'a thread on the live customer line was deleted');
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_an_unreadable_thread_is_left_alone(): void
    {
        Http::fake([
            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => []]], 200),
            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->assertNotNull((new GhlNumbers())->deleteConversationSafely('CONV1', [self::POOL]));
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }

    /**
     * Once the thread is gone from GHL this row is the only evidence the code
     * ever existed, so it is written BEFORE the delete is attempted and kept
     * even when the delete is refused.
     */
    public function test_the_audit_row_survives_a_refused_delete(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);

        // The row is made directly rather than through the poll: Http::fake()
        // MERGES stubs rather than replacing them, so a "poll then refuse"
        // pair in one test quietly gets the first stub twice and proves nothing.
        $otp = GhlOtp::create([
            'ghl_number_id' => $number->id, 'claimed_by_admin_id' => $this->dogar->id,
            'from_number' => '+15550001111', 'code' => '448213',
            'body' => 'Your CFPB code is 448213', 'received_at' => now(),
            'conversation_id' => 'CONV1', 'message_id' => 'MSG1',
        ]);

        // A thread that is NOT a code thread.
        Http::fake([
            'ghl.test/conversations/CONV1/messages*' => Http::response(['messages' => ['messages' => [
                ['id' => 'M1', 'direction' => 'outbound', 'to' => '+1555', 'from' => self::POOL, 'body' => 'Hi'],
            ]]], 200),
            'ghl.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($this->dogar, 'admin')
            ->postJson('/admin/numbers/otp/' . $otp->id . '/copied')->assertOk();

        $otp->refresh();
        $this->assertNotNull($otp->copied_at, 'the audit row lost its copied_at');
        $this->assertFalse($otp->deleted_from_ghl);
        $this->assertStringContainsString('left alone', (string) $otp->delete_note);
        $this->assertNull($number->fresh()->claimed_by_admin_id, 'the number stayed locked after a refused delete');
    }

    public function test_a_va_cannot_consume_someone_elses_code(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll');
        $otp = GhlOtp::firstOrFail();

        $this->actingAs($this->raja, 'admin')
            ->postJson('/admin/numbers/otp/' . $otp->id . '/copied')
            ->assertForbidden();

        $this->assertNull($otp->fresh()->copied_at);
    }

    public function test_deleting_can_be_switched_off_without_a_deploy(): void
    {
        config(['ghl_numbers.delete_after_copy' => false]);

        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->fakeInbound(self::POOL);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll');
        $otp = GhlOtp::firstOrFail();

        $this->actingAs($this->dogar, 'admin')
            ->postJson('/admin/numbers/otp/' . $otp->id . '/copied')->assertOk();

        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
        $this->assertFalse($otp->fresh()->deleted_from_ghl);
    }

    // ------------------------------------------------------- code extraction

    public function test_the_code_is_read_out_of_the_message_conservatively(): void
    {
        $this->assertSame('448213', GhlOtp::extractCode('Your CFPB code is 448213'));
        $this->assertSame('90210',  GhlOtp::extractCode('OTP: 90210 expires in 10 minutes'));
        $this->assertSame('1234',   GhlOtp::extractCode('Use 1234 to verify'));

        // Nothing that looks like a code: null, and the page shows the message.
        $this->assertNull(GhlOtp::extractCode('Thanks, talk soon'));
        $this->assertNull(GhlOtp::extractCode(''));
    }

    // -------------------------------------------------------------- the toggle

    public function test_the_super_admin_grants_and_revokes_phone_number_access(): void
    {
        $this->dogar->forceFill(['can_manage_numbers' => false])->save();

        $this->actingAs($this->super, 'admin')
            ->put('/admin/users/' . $this->dogar->id . '/number-access')->assertRedirect();
        $this->assertTrue((bool) $this->dogar->fresh()->can_manage_numbers);

        $this->actingAs($this->super, 'admin')
            ->put('/admin/users/' . $this->dogar->id . '/number-access')->assertRedirect();
        $this->assertFalse((bool) $this->dogar->fresh()->can_manage_numbers);
    }

    public function test_a_va_cannot_grant_themselves_phone_number_access(): void
    {
        $this->actingAs($this->dogar, 'admin')
            ->put('/admin/users/' . $this->dogar->id . '/number-access')
            ->assertForbidden();
    }
}
