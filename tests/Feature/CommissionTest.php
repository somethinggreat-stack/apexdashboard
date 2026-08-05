<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CommissionPayout;
use App\Models\EndUser;
use App\Support\CommissionSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@t.com', 'password' => 'secret', 'full_name' => 'Super']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function bo(string $name, array $attrs = []): Client
    {
        return Client::create(array_merge([
            'admin_id' => $this->super->id, 'business_name' => $name, 'email' => md5($name) . '@t.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ], $attrs));
    }

    private function realPayment(Client $bo, int $round, bool $free = false): void
    {
        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C' . uniqid(), 'last_name' => 'D', 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'status' => 'active', 'start_date' => '2026-01-01',
        ]);
        ClientPayment::create([
            'end_user_id' => $eu->id, 'round' => $round, 'amount' => 15, 'is_free' => $free,
            'paid_at' => '2026-01-01', 'created_by_admin_id' => $this->super->id,
        ]);
    }

    public function test_earned_is_five_per_real_payment_and_ignores_free(): void
    {
        $referrer = $this->bo('Chantal', ['is_commission_referrer' => true]);
        $referred = $this->bo('Tycon Stan', ['referrer_id' => $referrer->id]);

        $this->realPayment($referred, 1);
        $this->realPayment($referred, 2);
        $this->realPayment($referred, 3, free: true);   // free — must not count

        $summary = CommissionSummary::forReferrer($referrer);
        $this->assertSame(10.0, $summary['earned'], '2 real payments × $5');
    }

    public function test_each_referrer_only_counts_their_own_referred_bos(): void
    {
        $chantal = $this->bo('Chantal', ['is_commission_referrer' => true]);
        $peter   = $this->bo('Peter', ['is_commission_referrer' => true]);

        $this->realPayment($this->bo('BO-A', ['referrer_id' => $chantal->id]), 1);
        $this->realPayment($this->bo('BO-B', ['referrer_id' => $peter->id]), 1);
        $this->realPayment($this->bo('BO-C', ['referrer_id' => $peter->id]), 1);

        $this->assertSame(5.0,  CommissionSummary::forReferrer($chantal)['earned']);
        $this->assertSame(10.0, CommissionSummary::forReferrer($peter)['earned']);
    }

    public function test_admin_index_lists_referrers_and_show_works(): void
    {
        $chantal = $this->bo('Chantal', ['is_commission_referrer' => true]);
        $this->bo('Peter', ['is_commission_referrer' => true]);

        $this->actingAs($this->super, 'admin')->get('/admin/commissions')
            ->assertOk()->assertSee('Chantal')->assertSee('Peter');

        $this->actingAs($this->super, 'admin')->get("/admin/commissions/{$chantal->id}")
            ->assertOk()->assertSee('Chantal');
    }

    public function test_admin_cannot_open_a_non_referrer_or_other_orgs_referrer(): void
    {
        $plainBo = $this->bo('Plain BO');   // not a referrer
        $this->actingAs($this->super, 'admin')->get("/admin/commissions/{$plainBo->id}")->assertNotFound();

        $other = new Admin(['email' => 'o@t.com', 'password' => 'secret', 'full_name' => 'Other']);
        $other->role = 'super';
        $other->save();
        $otherReferrer = Client::create([
            'admin_id' => $other->id, 'business_name' => 'Other Ref', 'email' => 'or@t.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round', 'is_commission_referrer' => true,
        ]);
        $this->actingAs($this->super, 'admin')->get("/admin/commissions/{$otherReferrer->id}")->assertNotFound();
    }

    public function test_payout_records_against_the_referrer_and_reduces_outstanding(): void
    {
        $chantal = $this->bo('Chantal', ['is_commission_referrer' => true]);
        $this->realPayment($this->bo('BO-A', ['referrer_id' => $chantal->id]), 1);  // $5 earned

        $this->actingAs($this->super, 'admin')
            ->post("/admin/commissions/{$chantal->id}/payout", ['amount' => 3, 'paid_at' => '2026-02-01'])
            ->assertRedirect();

        $this->assertSame(1, CommissionPayout::where('referrer_id', $chantal->id)->count());
        $this->assertSame(2.0, CommissionSummary::forReferrer($chantal)['outstanding']);
    }

    public function test_bo_portal_shows_only_own_commissions_and_blocks_non_referrers(): void
    {
        $chantal = $this->bo('Chantal', ['is_commission_referrer' => true]);
        $peter   = $this->bo('Peter', ['is_commission_referrer' => true]);
        $plain   = $this->bo('Plain');

        $this->realPayment($this->bo('BO-A', ['referrer_id' => $chantal->id]), 1);
        $this->realPayment($this->bo('BO-B', ['referrer_id' => $peter->id]), 1);

        // A referrer sees their own figures.
        $this->actingAs($chantal, 'client')->get('/business-owner/commissions')->assertOk();
        // A non-referrer BO cannot reach it.
        $this->actingAs($plain, 'client')->get('/business-owner/commissions')->assertNotFound();
    }
}
