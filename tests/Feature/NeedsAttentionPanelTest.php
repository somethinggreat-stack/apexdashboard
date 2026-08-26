<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The redesigned VA-side "Needs Attention" panel renders: summary tiles, tabs,
 * per-owner rows with a priority, and an action button.
 */
class NeedsAttentionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_va_sees_needs_attention_panel(): void
    {
        $super = new Admin(['email' => 'super@t.com', 'password' => 'x', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $va = new Admin(['email' => 'va@t.com', 'password' => 'x', 'full_name' => 'Val']);
        $va->role = 'va';
        $va->parent_admin_id = $super->id;
        $va->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Acme Credit', 'email' => 'acme@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        // An active client whose round is well past due → shows as overdue + incomplete.
        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Pat', 'last_name' => 'Doe', 'suffix' => 'None',
            'email' => 'pat@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(45)->toDateString(),
            'intake_status' => null, 'rounds' => ['1st Round'],
        ]);

        $resp = $this->actingAs($va, 'admin')->get(route('admin.client-selector.index'));

        $resp->assertStatus(200);
        $resp->assertSee('Needs Attention');
        $resp->assertSee('Acme Credit');
        $resp->assertSee('Overdue');            // summary tile / column
        $resp->assertSee('View clients →');      // action button for a client with no new intake
        $resp->assertSee('nx-tabs', false);      // the new tabbed UI is present
    }
}
