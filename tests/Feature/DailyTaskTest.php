<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\RoundSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $bo;

    private function seedWorld(): void
    {
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Alin', 'email' => 'alin@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
    }

    private function eu(string $name, ?string $status, string $email): EndUser
    {
        return EndUser::create([
            'client_id' => $this->bo->id, 'first_name' => $name, 'last_name' => 'X', 'suffix' => 'None',
            'email' => $email, 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-06-01',
            'intake_status' => $status, 'rounds' => ['1st Round'],
        ]);
    }

    public function test_daily_task_shows_recent_work_and_hides_old(): void
    {
        $this->seedWorld();

        // A VA who selects a round in the window → the client + this VA appear.
        $va = new Admin(['email' => 'bea@test.com', 'password' => 'secret', 'full_name' => 'Bea']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        // Round selected this shift, by a VA → client + VA + round appear.
        $worked = $this->eu('Recent Worked', 'approved', 'w@test.com');
        RoundSelection::create([
            'end_user_id' => $worked->id, 'round' => 2, 'admin_id' => $va->id, 'created_at' => now(),
        ]);

        // A round selected two days ago → outside this shift, must NOT appear.
        $old = $this->eu('Old Worked', 'approved', 'o@test.com');
        RoundSelection::create([
            'end_user_id' => $old->id, 'round' => 1, 'admin_id' => $this->super->id,
            'created_at' => now()->subDays(2),
        ]);

        $resp = $this->actingAs($this->super, 'admin')->get('/admin/daily-task')->assertOk();

        $resp->assertSee('Alin');            // business owner block
        $resp->assertSee('Recent Worked');   // via in-window round selection
        $resp->assertSee('Bea');             // the VA who selected it
        $resp->assertSee('2nd Round');       // the round that was selected

        $resp->assertDontSee('Old Worked');  // selection outside this shift → excluded
    }

    public function test_super_and_va_can_open_daily_task_but_leads_cannot(): void
    {
        $this->seedWorld();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'VA']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        $leads = new Admin(['email' => 'leads@test.com', 'password' => 'secret', 'full_name' => 'Leads']);
        $leads->role = 'leads';
        $leads->parent_admin_id = $this->super->id;
        $leads->save();

        $this->actingAs($this->super, 'admin')->get('/admin/daily-task')->assertOk();
        $this->actingAs($va, 'admin')->get('/admin/daily-task')->assertOk();
        $this->actingAs($leads, 'admin')->get('/admin/daily-task')->assertStatus(403);
    }
}
