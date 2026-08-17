<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveReasonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Admin, 1: Client, 2: EndUser} */
    private function world(): array
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);

        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'Jane', 'last_name' => 'Roe', 'suffix' => 'None',
            'email' => 'jane@test.com', 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => 'done', 'rounds' => ['1st Round'],
        ]);

        return [$super, $bo, $eu];
    }

    public function test_hold_stores_reason_and_shows_on_hold_list(): void
    {
        [$super, $bo, $eu] = $this->world();

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post("/admin/end-users/{$eu->id}/hold", ['reason' => 'Client stopped paying'])
            ->assertRedirect();

        $eu->refresh();
        $this->assertNotNull($eu->held_at);
        $this->assertSame('Client stopped paying', $eu->move_reason);

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/hold')
            ->assertOk()->assertSee('Client stopped paying');

        // Resume clears the reason.
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post("/admin/end-users/{$eu->id}/resume")->assertRedirect();
        $eu->refresh();
        $this->assertNull($eu->held_at);
        $this->assertNull($eu->move_reason);
    }

    public function test_move_to_new_clients_stores_reason_and_shows_on_list(): void
    {
        [$super, $bo, $eu] = $this->world();

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post("/admin/end-users/{$eu->id}/to-new-clients", ['reason' => 'Docs need re-review'])
            ->assertRedirect();

        $eu->refresh();
        $this->assertSame('pending_review', $eu->intake_status);
        $this->assertSame('Docs need re-review', $eu->move_reason);

        $bo->update(['intake_enabled' => true, 'intake_token' => 'tok12345678901234567']);
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->get('/admin/new-clients')
            ->assertOk()->assertSee('Docs need re-review');

        // Approving to In Progress clears the reason.
        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->post("/admin/new-clients/{$eu->id}/approve")->assertRedirect();
        $this->assertNull($eu->refresh()->move_reason);
    }
}
