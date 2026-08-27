<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Clients list loads sorted by days-left ascending: the client whose next
 * round is soonest (and past-due files, whose days-left is negative) sits on
 * top. Same default for every business owner's list.
 */
class ClientListSortTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $this->super->role = 'super';
        $this->super->save();
        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    /** One 30-day round: days_left = start_date + 30 - today. */
    private function mk(string $name, int $daysAgo): EndUser
    {
        return EndUser::create([
            'client_id' => $this->bo->id, 'first_name' => $name, 'last_name' => 'X',
            'suffix' => 'None', 'email' => strtolower($name) . '@t.com',
            'current_address' => '1 St', 'city' => 'T', 'state' => 'ST', 'zipcode' => '12345',
            'status' => 'active', 'start_date' => now()->subDays($daysAgo)->toDateString(),
            'intake_status' => 'done', 'rounds' => ['1st Round'],
        ]);
    }

    public function test_clients_list_orders_by_days_left_ascending(): void
    {
        $comfy   = $this->mk('Comfy', 5);    // ~25 days left
        $soon    = $this->mk('Soon', 27);    // ~3 days left
        $pastDue = $this->mk('Pastdue', 40); // ~-10 days left (most urgent)

        $html = $this->actingAs($this->super, 'admin')
            ->withSession(['selected_client_id' => $this->bo->id])
            ->get('/admin/client-list')
            ->assertOk()
            ->getContent();

        $posPast  = strpos($html, 'Pastdue');
        $posSoon  = strpos($html, 'Soon');
        $posComfy = strpos($html, 'Comfy');

        $this->assertNotFalse($posPast);
        $this->assertLessThan($posSoon, $posPast, 'Past-due client must appear before the soon client');
        $this->assertLessThan($posComfy, $posSoon, 'Soon client must appear before the comfortable client');
    }
}
