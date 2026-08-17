<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRoundDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_invoice_includes_each_round_start_date(): void
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Gawd', 'email' => 'gawd@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 20,
        ]);

        // A client on rounds 1 and 2 with distinct start dates, nothing paid yet.
        EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Islam', 'last_name' => 'Abdel Qader', 'suffix' => 'None',
            'email' => 'islam@test.com', 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-07-17',
            'intake_status' => 'done', 'rounds' => ['1st Round', '2nd Round'],
            'round_dates' => ['2nd Round' => '2026-08-06'],
        ]);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post('/admin/payments/invoice')
            ->assertRedirect();

        $items = collect(Invoice::latest('id')->first()->items);

        $r1 = $items->firstWhere('round', 1);
        $r2 = $items->firstWhere('round', 2);

        $this->assertSame('2026-07-17', $r1['round_started'], '1st round uses the client start date');
        $this->assertSame('2026-08-06', $r2['round_started'], '2nd round uses its stamped date');
    }
}
