<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An inactive business owner drops off the super-admin dashboard and the
 * business-owner picker entirely (Needs Attention, balances, payment totals,
 * stats) but stays on the Add/Remove Business Owners page so it can be
 * reactivated.
 */
class InactiveOwnerHiddenTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function makeBo(string $name, string $email, string $status): Client
    {
        $bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name, 'email' => $email,
            'password' => 'secret', 'monthly_fee' => 0, 'status' => $status,
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'round_cycle_days' => 30, 'intake_enabled' => true,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        // A pending-review client so the owner would otherwise surface in Needs Attention.
        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'C', 'last_name' => $name,
            'suffix' => 'None', 'email' => "c_{$email}", 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => '2026-06-01', 'intake_status' => 'pending_review', 'rounds' => ['1st Round'],
        ]);

        return $bo;
    }

    public function test_inactive_owner_is_hidden_from_dashboard_and_picker_but_shown_on_manage(): void
    {
        $this->makeBo('Active Owner', 'active@test.com', 'active');
        $this->makeBo('Churned Owner', 'churned@test.com', 'inactive');

        $this->actingAs($this->super, 'admin');

        // Dashboard: active present, inactive absent.
        $dash = $this->get(route('admin.dashboard'));
        $dash->assertStatus(200);
        $dash->assertSee('Active Owner');
        $dash->assertDontSee('Churned Owner');

        // Business-owner picker: same.
        $picker = $this->get(route('admin.client-selector.index'));
        $picker->assertStatus(200);
        $picker->assertSee('Active Owner');
        $picker->assertDontSee('Churned Owner');

        // Add/Remove Business Owners: inactive owner still listed for reactivation.
        $manage = $this->get(route('admin.clients.index'));
        $manage->assertStatus(200);
        $manage->assertSee('Churned Owner');
    }

    public function test_total_collected_includes_inactive_owner_but_outstanding_excludes_it(): void
    {
        // Active owner: no money at all.
        $this->makeBo('Active Owner', 'active@test.com', 'active');

        // Inactive owner: $777 already collected on a past client.
        $churned = $this->makeBo('Churned Owner', 'churned@test.com', 'inactive');
        $eu = EndUser::create([
            'client_id' => $churned->id, 'first_name' => 'Paid', 'last_name' => 'Client',
            'suffix' => 'None', 'email' => 'paid@churned.test', 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'status' => 'active',
            'start_date' => '2026-06-01', 'intake_status' => 'done', 'rounds' => ['1st Round'],
        ]);
        ClientPayment::create([
            'end_user_id' => $eu->id, 'round' => 1, 'amount' => 777,
            'is_free' => false, 'paid_at' => '2026-06-15',
            'created_by_admin_id' => $this->super->id,
        ]);

        $this->actingAs($this->super, 'admin');
        $picker = $this->get(route('admin.client-selector.index'));

        $picker->assertStatus(200);
        // Collected still counts the inactive owner's $777 …
        $picker->assertSee('$777.00');
        // … but the inactive owner never appears as a card, and nothing is owed
        // (the only pending would have come from the excluded inactive owner).
        $picker->assertDontSee('Churned Owner');
    }
}
