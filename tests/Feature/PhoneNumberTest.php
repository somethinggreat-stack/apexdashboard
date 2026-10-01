<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SmsCode;
use App\Models\SmsNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Plivo\Util\v3SignatureValidation;
use Tests\TestCase;

/**
 * Phone Numbers — the shared pool a VA claims to collect a one-time code.
 *
 * Plivo pushes inbound SMS to the webhook; nothing here polls. The two things
 * that must never be wrong: two VAs can never hold one number, and a forged
 * webhook can never put a code on anyone's screen.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    private const POOL  = '+12622610439';
    private const OTHER = '+15559990000';   // a number we do not run
    private const TOKEN = 'test-auth-token';
    private const HOOK  = 'https://apexgrowthsolution.com/sms/plivo/inbound';

    private Admin $super;
    private Admin $dogar;
    private Admin $raja;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sms.plivo.auth_id'    => 'MA123',
            'sms.plivo.auth_token' => self::TOKEN,
            'sms.plivo.base_url'   => 'https://plivo.test/v1',
            'sms.webhook_url'      => self::HOOK,
            'sms.verify_signature' => true,
            'sms.claim_minutes'    => 10,
            'sms.excluded'         => [],
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

    private function number(string $phone = self::POOL, string $label = 'Pool 1'): SmsNumber
    {
        return SmsNumber::create(['phone' => $phone, 'label' => $label, 'active' => true]);
    }

    /** A webhook signed the way Plivo signs one. */
    private function deliver(array $params, ?string $signature = null, ?string $nonce = null)
    {
        $nonce ??= 'nonce-' . uniqid();

        if ($signature === null) {
            // Produced by Plivo's own signer, so the test cannot agree with a
            // mistake in our verification by making the same mistake twice.
            $signature = $this->signLikePlivo($params, $nonce);
        }

        return $this->post('/sms/plivo/inbound', $params, [
            'X-Plivo-Signature-V3'       => $signature,
            'X-Plivo-Signature-V3-Nonce' => $nonce,
        ]);
    }

    private function signLikePlivo(array $params, string $nonce): string
    {
        // Mirrors Plivo's documented V3 scheme: URL, then params sorted by name
        // and concatenated, then a period and the nonce.
        $keys = array_keys($params);
        sort($keys, SORT_NATURAL);

        $joined = '';
        foreach ($keys as $key) {
            $joined .= $key . $params[$key];
        }

        // Plivo's own constructPostUrl appends '?' to the URL when there are POST
        // params (even with no query string), then the sorted pairs, then '.' and
        // the nonce. Confirmed against their library in the test below.
        $base = self::HOOK . (count($params) ? '?' : '') . $joined . '.' . $nonce;

        return base64_encode(hash_hmac('SHA256', $base, self::TOKEN, true));
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'From'        => '+15550001111',
            'To'          => self::POOL,
            'Text'        => 'Your CFPB code is 448213',
            'Type'        => 'sms',
            'MessageUUID' => 'uuid-' . uniqid(),
        ], $over);
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

    // --------------------------------------------------------------- THE LOCK

    public function test_two_vas_can_never_hold_the_same_number(): void
    {
        $number = $this->number();
        Http::fake();

        $this->actingAs($this->dogar, 'admin')->post('/admin/numbers/' . $number->id . '/claim');
        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id);

        $this->actingAs($this->raja, 'admin')->post('/admin/numbers/' . $number->id . '/claim')
            ->assertSessionHasErrors('number');

        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id, 'the number changed hands');
    }

    /**
     * The claim is decided by the database, not by read-then-write. Two model
     * instances loaded BEFORE either wrote is what two simultaneous requests
     * actually look like.
     */
    public function test_the_claim_is_decided_by_the_database_not_by_a_read(): void
    {
        $number  = $this->number();
        $asDogar = SmsNumber::find($number->id);
        $asRaja  = SmsNumber::find($number->id);

        $this->assertTrue($asDogar->claim($this->dogar->id));
        $this->assertFalse($asRaja->claim($this->raja->id), 'both instances saw a free number and both took it');
    }

    public function test_an_abandoned_claim_times_out_and_returns_to_the_pool(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $number->forceFill(['claimed_at' => now()->subMinutes(11)])->save();

        $this->assertTrue($number->fresh()->isAvailable());
        $this->assertTrue(SmsNumber::find($number->id)->claim($this->raja->id));
    }

    public function test_a_va_cannot_release_a_number_someone_else_is_holding(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        Http::fake();

        $this->actingAs($this->raja, 'admin')->post('/admin/numbers/' . $number->id . '/release')
            ->assertSessionHasErrors('number');
        $this->assertSame($this->dogar->id, $number->fresh()->claimed_by_admin_id);

        $this->actingAs($this->super, 'admin')->post('/admin/numbers/' . $number->id . '/release');
        $this->assertNull($number->fresh()->claimed_by_admin_id);
    }

    public function test_vas_see_who_is_holding_a_number(): void
    {
        $number = $this->number();
        $number->claim($this->raja->id);
        Http::fake(['plivo.test/*' => Http::response(['objects' => []], 200)]);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')
            ->assertOk()->assertSee('Raja Khuram')->assertSee('In use', false);
    }

    // ------------------------------------------------------------ THE WEBHOOK

    public function test_an_inbound_sms_is_recorded_against_the_right_number(): void
    {
        $number = $this->number();

        $this->deliver($this->payload())->assertOk();

        $code = SmsCode::firstOrFail();
        $this->assertSame($number->id, $code->sms_number_id);
        $this->assertSame('448213', $code->code);
        $this->assertSame('Your CFPB code is 448213', $code->body, 'the whole message must be kept');
    }

    /**
     * The endpoint is public, so this is the one that matters most: without it,
     * anyone who learns the URL can put any code on a VA's screen.
     */
    public function test_a_forged_webhook_is_dropped(): void
    {
        $this->number();

        $this->deliver($this->payload(), 'not-a-real-signature')->assertOk();
        $this->assertSame(0, SmsCode::count(), 'a forged webhook was recorded');

        // ...and a real signature for DIFFERENT content is no good either.
        $params = $this->payload();
        $nonce  = 'n1';
        $signed = $this->signLikePlivo($params, $nonce);

        $tampered = array_merge($params, ['Text' => 'Your CFPB code is 000000']);
        $this->deliver($tampered, $signed, $nonce)->assertOk();

        $this->assertSame(0, SmsCode::count(), 'a tampered webhook was recorded');
    }

    public function test_our_verification_agrees_with_plivos_own_signer(): void
    {
        // Guards against the test and the code sharing one misunderstanding of
        // the V3 scheme: the signature is checked against Plivo's own library.
        $params = $this->payload();
        $nonce  = 'nonce-xyz';

        $this->assertTrue(v3SignatureValidation::validateV3Signature(
            'POST', self::HOOK, $nonce, self::TOKEN, $this->signLikePlivo($params, $nonce), $params
        ), 'the test signs differently from Plivo');
    }

    public function test_a_message_to_a_number_we_do_not_run_is_ignored(): void
    {
        $this->number();

        $this->deliver($this->payload(['To' => self::OTHER]))->assertOk();

        $this->assertSame(0, SmsCode::count());
    }

    public function test_the_same_message_delivered_twice_records_one_code(): void
    {
        // Plivo retries a webhook it did not get a 200 for.
        $this->number();
        $payload = $this->payload();

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $this->assertSame(1, SmsCode::count());
    }

    public function test_the_webhook_always_answers_200(): void
    {
        // An error would only make Plivo send it again; a message we chose to
        // ignore is not something to retry.
        $this->number();

        $this->deliver($this->payload(), 'bad-signature')->assertOk();
        $this->deliver($this->payload(['To' => self::OTHER]))->assertOk();
        $this->deliver($this->payload(['Type' => 'mms']))->assertOk();
    }

    // --------------------------------------------------------------- THE CODE

    public function test_the_code_reaches_the_va_holding_that_number(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);

        $this->deliver($this->payload());

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()
            ->assertJsonPath('codes.0.code', '448213')
            ->assertJsonPath('codes.0.phone', self::POOL);
    }

    public function test_a_code_never_reaches_a_va_who_is_not_holding_that_number(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->deliver($this->payload());

        $this->actingAs($this->raja, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_a_code_that_arrived_before_the_claim_is_not_handed_over(): void
    {
        // Otherwise the previous VA's code goes to whoever claims next.
        $number = $this->number();
        $this->deliver($this->payload());
        SmsCode::query()->update(['received_at' => now()->subMinutes(30)]);

        $number->claim($this->dogar->id);

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_holding_nothing_returns_nothing(): void
    {
        $this->number();
        $this->deliver($this->payload());

        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_copying_records_it_and_frees_the_number(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->deliver($this->payload());

        $code = SmsCode::firstOrFail();

        $this->actingAs($this->dogar, 'admin')
            ->postJson('/admin/numbers/otp/' . $code->id . '/copied')
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertNotNull($code->fresh()->copied_at);
        $this->assertNull($number->fresh()->claimed_by_admin_id, 'the number was not released');

        // ...and it is not offered a second time.
        $number->claim($this->dogar->id);
        $this->actingAs($this->dogar, 'admin')->getJson('/admin/numbers/poll')
            ->assertOk()->assertJsonCount(0, 'codes');
    }

    public function test_a_va_cannot_consume_someone_elses_code(): void
    {
        $number = $this->number();
        $number->claim($this->dogar->id);
        $this->deliver($this->payload());

        $code = SmsCode::firstOrFail();

        $this->actingAs($this->raja, 'admin')
            ->postJson('/admin/numbers/otp/' . $code->id . '/copied')
            ->assertForbidden();

        $this->assertNull($code->fresh()->copied_at);
    }

    // ------------------------------------------------------------- the pool

    public function test_the_pool_is_read_from_plivo_and_excluded_numbers_stay_out(): void
    {
        config(['sms.excluded' => ['+15551234567']]);

        Http::fake(['plivo.test/*' => Http::response(['objects' => [
            ['number' => '12622610439', 'alias' => 'Pool 1'],      // no leading +
            ['number' => '+15551234567', 'alias' => 'Keep out'],
        ]], 200)]);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')->assertOk();

        $this->assertSame(1, SmsNumber::count());
        $this->assertSame(self::POOL, SmsNumber::first()->phone, 'the number was not normalised to E.164');
    }

    public function test_the_page_still_renders_when_plivo_is_unreachable(): void
    {
        $this->number();
        Http::fake(['plivo.test/*' => Http::response('', 500)]);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')
            ->assertOk()->assertSee('Pool 1');
    }

    public function test_the_credentials_never_reach_the_page(): void
    {
        $this->number();
        Http::fake(['plivo.test/*' => Http::response(['objects' => []], 200)]);

        $this->actingAs($this->dogar, 'admin')->get('/admin/numbers')
            ->assertOk()
            ->assertDontSee(self::TOKEN, false)
            ->assertDontSee('MA123', false);
    }

    // ---------------------------------------------------- code extraction

    public function test_the_code_is_read_out_of_the_message_conservatively(): void
    {
        $this->assertSame('448213', SmsCode::extractCode('Your CFPB code is 448213'));
        $this->assertSame('90210',  SmsCode::extractCode('OTP: 90210 expires in 10 minutes'));
        $this->assertSame('1234',   SmsCode::extractCode('Use 1234 to verify'));

        // Nothing code-shaped: null, and the page shows the message instead.
        $this->assertNull(SmsCode::extractCode('Thanks, talk soon'));
        $this->assertNull(SmsCode::extractCode(''));
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
