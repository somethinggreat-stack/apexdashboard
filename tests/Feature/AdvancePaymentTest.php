<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
use App\Models\Invoice;
use App\Models\OwnerAdvance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Advance payments: money a business owner paid up front is noted once, comes
 * off every invoice while unused, and pays for rounds when applied — so it is
 * never charged twice.
 */
class AdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->owner = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'JP', 'email' => 'jp@test.com',
            'password' => 'secret-pass', 'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
    }

    /** A client on the Clients list who has done round 1 (unpaid). */
    private function client(string $first): EndUser
    {
        return EndUser::create([
            'client_id' => $this->owner->id, 'first_name' => $first, 'last_name' => 'Paul',
            'email' => strtolower($first) . '@example.com', 'status' => 'active', 'intake_status' => 'done',
            'start_date' => now()->subDays(10)->toDateString(), 'rounds' => ['1st Round'],
        ]);
    }

    private function as()
    {
        return $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->owner->id]);
    }

    private function advance(float $amount)
    {
        return $this->as()->post('/admin/payments/advances', [
            'amount' => $amount, 'received_at' => now()->toDateString(), 'method' => 'Zelle', 'notes' => 'paid ahead',
        ]);
    }

    public function test_an_advance_is_recorded_and_shows_as_credit(): void
    {
        $this->advance(150)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(150.0, $this->owner->advanceBalance());
        $this->as()->get('/admin/payments')->assertOk()
            ->assertSee('Advance Payments')->assertSee('$150.00')->assertSee('paid ahead');
    }

    public function test_unused_credit_comes_off_what_is_owed_and_the_invoice(): void
    {
        $this->client('Jennings');
        $this->client('Myrka');        // 2 unpaid rounds = $30
        $this->advance(20);

        $this->assertSame(10.0, $this->owner->fresh()->paymentTotals()['pending']);
        $this->as()->get('/admin/payments')->assertOk()->assertSee('Due after $20.00 advance: $10.00');

        $this->as()->post('/admin/payments/invoice')->assertRedirect();
        $invoice = Invoice::latest('id')->first();
        $this->assertSame('10.00', (string) $invoice->total);

        $this->as()->get('/admin/payments/invoice/' . $invoice->id)->assertOk()
            ->assertSee('Less: advance payment already received')->assertSee('$30.00');
    }

    public function test_credit_bigger_than_the_bill_never_makes_it_negative(): void
    {
        $this->client('Jennings');     // $15 owed
        $this->advance(100);

        $this->assertSame(0.0, $this->owner->fresh()->paymentTotals()['pending']);
        $this->as()->post('/admin/payments/invoice')->assertRedirect();
        $this->assertSame('0.00', (string) Invoice::latest('id')->first()->total);
    }

    public function test_applying_credit_pays_whole_rounds_and_keeps_the_rest(): void
    {
        $a = $this->client('Jennings');
        $b = $this->client('Myrka');
        $c = $this->client('Zed');     // 3 × $15 = $45 owed
        $this->advance(40);

        $this->as()->post('/admin/payments/advances/apply')->assertRedirect()->assertSessionHas('status');

        $paid = ClientPayment::where('from_advance', true)->get();
        $this->assertCount(2, $paid, 'two whole rounds fit in $40');
        $this->assertSame(['Advance credit'], $paid->pluck('method')->unique()->values()->all());
        $this->assertSame(10.0, $this->owner->advanceBalance(), '$10 stays as credit');
        $this->assertSame(5.0, $this->owner->fresh()->paymentTotals()['pending'], '$15 left, less the $10 credit');
    }

    public function test_credit_smaller_than_a_round_pays_nothing(): void
    {
        $this->client('Jennings');
        $this->advance(10);

        $this->as()->post('/admin/payments/advances/apply')->assertRedirect();

        $this->assertSame(0, ClientPayment::count());
        $this->assertSame(10.0, $this->owner->advanceBalance());
    }

    public function test_undoing_a_round_paid_from_credit_gives_the_credit_back(): void
    {
        $this->client('Jennings');
        $this->advance(15);
        $this->as()->post('/admin/payments/advances/apply');
        $this->assertSame(0.0, $this->owner->advanceBalance());

        $this->as()->delete('/admin/payments/' . ClientPayment::first()->id)->assertRedirect();

        $this->assertSame(15.0, $this->owner->advanceBalance());
    }

    public function test_an_advance_already_spent_cannot_be_removed(): void
    {
        $this->client('Jennings');
        $this->advance(15);
        $this->as()->post('/admin/payments/advances/apply');

        $this->as()->delete('/admin/payments/advances/' . OwnerAdvance::first()->id)
            ->assertSessionHasErrors('advance');
        $this->assertSame(1, OwnerAdvance::count());
    }

    public function test_an_unspent_advance_can_be_removed(): void
    {
        $this->advance(50);

        $this->as()->delete('/admin/payments/advances/' . OwnerAdvance::first()->id)->assertRedirect();
        $this->assertSame(0, OwnerAdvance::count());
    }

    public function test_another_organisation_cannot_touch_an_advance(): void
    {
        $this->advance(50);

        $other = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other']);
        $other->role = 'super';
        $other->save();

        $this->actingAs($other, 'admin')->withSession(['selected_client_id' => $this->owner->id])
            ->delete('/admin/payments/advances/' . OwnerAdvance::first()->id);   // refused (redirect or 404)
        $this->assertSame(1, OwnerAdvance::count());
    }

    public function test_the_owner_sees_the_advance_on_their_billing_page(): void
    {
        $this->client('Jennings');
        $this->client('Myrka');
        $this->advance(20);

        $this->actingAs($this->owner, 'client')->get('/business-owner/billing')->assertOk()
            ->assertSee('Advance Credit')
            ->assertSee('Less: your advance payment')
            ->assertSee('$10.00 unpaid');
    }
}
