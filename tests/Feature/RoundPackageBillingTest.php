<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Invoice;
use App\Models\RoundSelection;
use App\Models\TimePayout;
use App\Services\RoundPackageBilling;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly Package billing (Don Cadet): $500 covers the first 70 rounds processed
 * in a month, each round after that is $7, and a month with no rounds owes nothing.
 */
class RoundPackageBillingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $don;
    private int $made = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-period: Don's months run from the 7th, so this is Oct 7 – Nov 6.
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Karachi'));

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->don = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Don Cadet', 'email' => 'don@test.com',
            'password' => 'secret-pass', 'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
            'compensation_model' => 'package', 'package_fee' => 500, 'package_rounds' => 70,
            'package_overage_fee' => 7, 'pay_cycle' => 'monthly', 'pay_cycle_anchor' => '2026-09-07',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A client whose given rounds were each first selected at the given Pakistan time. */
    private function clientWithRounds(array $roundsAt, string $status = 'done'): EndUser
    {
        $n = ++$this->made;
        $labels = array_map(fn ($r) => EndUser::ROUND_OPTIONS[$r - 1], array_keys($roundsAt));

        $eu = EndUser::create([
            'client_id' => $this->don->id, 'first_name' => "Client{$n}", 'last_name' => 'Test',
            'email' => "c{$n}@example.com", 'status' => 'active', 'intake_status' => $status,
            'start_date' => '2026-09-01', 'rounds' => $labels,
        ]);

        foreach ($roundsAt as $round => $at) {
            RoundSelection::create([
                'end_user_id' => $eu->id, 'round' => $round,
                'created_at'  => Carbon::parse($at, 'Asia/Karachi')->utc(),
            ]);
        }

        return $eu;
    }

    private function billing(): RoundPackageBilling
    {
        return new RoundPackageBilling($this->don->fresh());
    }

    public function test_the_price_is_the_package_up_to_seventy_then_seven_dollars_a_round(): void
    {
        $b = $this->billing();

        $this->assertSame(0.0, $b->price(0)['total'], 'a month with no rounds should owe nothing');
        $this->assertSame(500.0, $b->price(1)['total']);
        $this->assertSame(500.0, $b->price(70)['total']);
        $this->assertSame(507.0, $b->price(71)['total']);
        $this->assertSame(535.0, $b->price(75)['total']);
        $this->assertSame(5, $b->price(75)['extra']);
    }

    public function test_only_rounds_first_selected_in_the_period_count(): void
    {
        // Round 1 last period, round 2 this period: only round 2 counts now.
        $this->clientWithRounds([1 => '2026-09-20 10:00', 2 => '2026-10-08 10:00']);
        // Boundaries in Pakistan time: Oct 6 11pm is last period, Oct 7 00:30 is this one.
        $this->clientWithRounds([1 => '2026-10-06 23:00']);
        $this->clientWithRounds([1 => '2026-10-07 00:30']);

        [$start, $end] = $this->billing()->periodContaining(now());
        $this->assertSame('2026-10-07', $start->toDateString());
        $this->assertSame('2026-11-06', $end->toDateString());

        $this->assertSame(2, $this->billing()->roundsIn($start, $end)->count());
    }

    public function test_a_marked_round_stays_billed_whatever_happens_to_the_client_after(): void
    {
        $removed = $this->clientWithRounds([1 => '2026-09-10 10:00', 2 => '2026-10-10 10:00']);
        $removed->update(['rounds' => ['1st Round']]);                          // round taken back off the strip

        $this->clientWithRounds([1 => '2026-10-10 10:00'], 'round_error');
        $this->clientWithRounds([1 => '2026-10-10 10:00'], 'pending_review');  // moved back to New Clients
        $this->clientWithRounds([1 => '2026-10-10 10:00'], 'error');           // moved to New Client Errors
        $held = $this->clientWithRounds([1 => '2026-10-10 10:00']);
        $held->update(['held_at' => now()]);
        $approval = $this->clientWithRounds([1 => '2026-10-10 10:00']);
        $approval->update(['round_approval_status' => 'awaiting']);

        [$start, $end] = $this->billing()->periodContaining(now());
        $this->assertSame(6, $this->billing()->roundsIn($start, $end)->count());
    }

    public function test_a_deleted_client_is_not_billed(): void
    {
        $this->clientWithRounds([1 => '2026-10-10 10:00'])->delete();

        [$start, $end] = $this->billing()->periodContaining(now());
        $this->assertSame(0, $this->billing()->roundsIn($start, $end)->count());
    }

    public function test_a_reselected_round_counts_once_in_the_month_it_was_first_selected(): void
    {
        $eu = $this->clientWithRounds([1 => '2026-09-15 10:00']);
        RoundSelection::create(['end_user_id' => $eu->id, 'round' => 1, 'created_at' => Carbon::parse('2026-10-12 10:00', 'Asia/Karachi')->utc()]);

        [$start, $end] = $this->billing()->periodContaining(now());
        $this->assertSame(0, $this->billing()->roundsIn($start, $end)->count());
    }

    public function test_seventy_five_rounds_bill_the_package_plus_five_extra(): void
    {
        for ($i = 0; $i < 75; $i++) {
            $this->clientWithRounds([1 => '2026-10-1' . ($i % 9) . ' 10:00']);
        }

        $current = $this->billing()->periods()[0];
        $this->assertSame(75, $current['price']['rounds']);
        $this->assertSame(535.0, $current['price']['total']);
    }

    public function test_the_payments_page_shows_the_package_and_this_months_rounds(): void
    {
        $this->clientWithRounds([1 => '2026-10-10 10:00', 2 => '2026-10-12 10:00']);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Monthly Package')
            ->assertSee('Rounds by Month')
            ->assertSee('Client1 Test — Round 2', false)
            ->assertSee('$500.00');
    }

    public function test_an_invoice_bills_the_package_and_lists_the_rounds(): void
    {
        for ($i = 0; $i < 72; $i++) {
            $this->clientWithRounds([1 => '2026-10-10 10:00']);
        }

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->post('/admin/payments/invoice', ['period_start' => '2026-10-07'])
            ->assertRedirect();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('514.00', (string) $invoice->total);   // 500 + 2 × 7
        $this->assertSame(72, count($invoice->items[0]['detail']));

        $this->actingAs($this->super, 'admin')->get('/admin/payments/invoice/' . $invoice->id)
            ->assertOk()->assertSee('Monthly package — up to 70 rounds')->assertSee('Additional rounds beyond the package');
    }

    public function test_recording_a_payment_clears_the_outstanding_month(): void
    {
        $this->clientWithRounds([1 => '2026-09-20 10:00']);   // last period: 1 round → $500
        $this->assertSame(500.0, $this->don->fresh()->paymentTotals()['pending']);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->post('/admin/payments/payout', [
                'period_start' => '2026-09-07', 'period_end' => '2026-10-06',
                'hours_in_period' => 0, 'rounds_in_period' => 1,
                'amount_paid' => 500, 'paid_at' => '2026-10-15',
            ])->assertRedirect();

        $this->assertSame(1, TimePayout::first()->rounds_in_period);
        $this->assertSame(0.0, $this->don->fresh()->paymentTotals()['pending']);
        $this->assertSame(500.0, $this->don->fresh()->paymentTotals()['done']);
    }

    public function test_saving_settings_keeps_the_package_and_its_numbers(): void
    {
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->put('/admin/payments/config', [
                'compensation_model' => 'package', 'package_fee' => 600,
                'package_rounds' => 80, 'package_overage_fee' => 8, 'pay_cycle_anchor' => '2026-09-07',
            ])->assertRedirect();

        $don = $this->don->fresh();
        $this->assertSame('package', $don->compensation_model);
        $this->assertSame('600.00', (string) $don->package_fee);
        $this->assertSame(80, $don->package_rounds);
        $this->assertSame('monthly', $don->pay_cycle);
    }

    public function test_other_owners_are_not_offered_the_package(): void
    {
        $other = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Someone Else', 'email' => 'se@test.com',
            'password' => 'secret-pass', 'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $other->id])
            ->get('/admin/payments')->assertOk()->assertDontSee('Monthly Package');

        // And it can't be forced on them by posting the form by hand.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $other->id])
            ->put('/admin/payments/config', ['compensation_model' => 'package', 'package_fee' => 1])
            ->assertSessionHasErrors('compensation_model');
        $this->assertSame('per_round', $other->fresh()->compensation_model);
        $this->assertSame('15.00', (string) $other->fresh()->per_round_fee);
    }

    public function test_don_sees_his_package_on_his_own_billing_page(): void
    {
        $this->clientWithRounds([1 => '2026-09-20 10:00']);

        $this->actingAs($this->don, 'client')->get('/business-owner/billing')
            ->assertOk()->assertSee('500.00 / 70 rounds')->assertSee('in monthly rounds');
    }

    public function test_the_migration_switches_on_don_cadet_and_nobody_else(): void
    {
        $make = fn (string $name, string $email) => Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name, 'email' => $email,
            'password' => 'secret-pass', 'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
            'compensation_model' => 'hourly', 'hourly_rate' => 3.85, 'pay_cycle' => 'monthly', 'pay_cycle_anchor' => '2026-09-07',
        ]);
        $this->don->forceDelete();
        $don   = $make(' don cadet ', 'd2@test.com');
        $other = $make('Don Cadet Junior', 'dj@test.com');

        $migration = require database_path('migrations/2026_10_09_000001_add_round_package_billing.php');
        $migration->down();
        $migration->up();

        $don = $don->fresh();
        $this->assertSame('package', $don->compensation_model);
        $this->assertSame('500.00', (string) $don->package_fee);
        $this->assertSame(70, $don->package_rounds);
        $this->assertSame('7.00', (string) $don->package_overage_fee);
        $this->assertSame('2026-09-07', $don->pay_cycle_anchor->toDateString(), 'his existing cycle start should be kept');
        $this->assertNull($don->hourly_rate);

        $this->assertSame('hourly', $other->fresh()->compensation_model);
    }

    public function test_the_page_says_how_many_clients_the_rounds_cover(): void
    {
        $this->clientWithRounds([1 => '2026-10-08 10:00', 2 => '2026-10-15 10:00']);   // 2 rounds, same client
        $this->clientWithRounds([1 => '2026-10-10 10:00']);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('2 clients')
            ->assertSee('1 with 2+ rounds');
    }

    public function test_dons_first_month_of_night_shift_work_lands_in_one_period(): void
    {
        // Don's real first batch: the earliest round was marked 17:10 Sep 8 Eastern
        // (02:10 Sep 9 in Pakistan) and the last ten on Oct 7 Eastern. With months
        // from the 9th, all of it is one period.
        $this->don->update(['pay_cycle_anchor' => '2026-09-09']);
        $et = fn (string $t) => Carbon::parse($t, 'America/New_York')->timezone('Asia/Karachi')->format('Y-m-d H:i:s');

        $this->clientWithRounds([1 => $et('2026-09-08 17:10:20')]);
        $this->clientWithRounds([1 => $et('2026-10-07 23:30:00')]);

        $periods = $this->billing()->periods();
        $this->assertSame('2026-10-09', $periods[0]['start']->toDateString());
        $this->assertSame(0, $periods[0]['price']['rounds']);
        $this->assertSame('2026-09-09', $periods[1]['start']->toDateString());
        $this->assertSame('2026-10-08', $periods[1]['end']->toDateString());
        $this->assertSame(2, $periods[1]['price']['rounds']);
    }

    public function test_the_first_period_runs_from_the_day_don_was_added_to_oct_9(): void
    {
        // Don added Sep 2; monthly tracking from Oct 10, so the opening period ends Oct 9.
        $this->don->forceFill(['created_at' => Carbon::parse('2026-09-02 10:00', 'Asia/Karachi')->utc()])->save();
        $this->don->update(['pay_cycle_anchor' => '2026-10-10']);
        $et = fn (string $t) => Carbon::parse($t, 'America/New_York')->timezone('Asia/Karachi')->format('Y-m-d H:i:s');

        for ($i = 0; $i < 77; $i++) {
            $this->clientWithRounds([1 => $et('2026-09-08 17:10:20')]);
        }
        $this->clientWithRounds([1 => $et('2026-09-20 10:00'), 2 => $et('2026-10-07 23:30')]);   // one client, two rounds
        $this->clientWithRounds([1 => '2026-10-12 10:00']);                                   // after the opening period

        // On Oct 9 the opening period is the current one.
        Carbon::setTestNow(Carbon::parse('2026-10-09 20:00', 'Asia/Karachi'));
        $now = $this->billing()->periods();
        $this->assertSame('2026-09-02', $now[0]['start']->toDateString());
        $this->assertSame('2026-10-09', $now[0]['end']->toDateString());
        $this->assertCount(1, $now, 'nothing should exist before the day Don was added');

        // Later, Oct 10 – Nov 9 is current and the opening period sits behind it.
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'Asia/Karachi'));
        [$current, $opening] = $this->billing()->periods();
        $this->assertSame(['2026-10-10', '2026-11-09'], [$current['start']->toDateString(), $current['end']->toDateString()]);
        $this->assertSame(1, $current['price']['rounds']);
        $this->assertSame(['2026-09-02', '2026-10-09'], [$opening['start']->toDateString(), $opening['end']->toDateString()]);
        $this->assertSame(79, $opening['price']['rounds']);
        $this->assertSame(563.0, $opening['price']['total']);   // 500 + 9 × 7

        // The opening period can be invoiced and paid like any other.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->don->id])
            ->post('/admin/payments/invoice', ['period_start' => '2026-09-02'])->assertRedirect();
        $this->assertSame('563.00', (string) Invoice::latest('id')->first()->total);
    }
}
