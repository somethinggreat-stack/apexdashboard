<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
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

        // A VA who marks a Week-1 step in the window → the client + this VA appear.
        $va = new Admin(['email' => 'bea@test.com', 'password' => 'secret', 'full_name' => 'Bea']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        // Recent Week-1 step, marked by a VA → client + VA + step appear.
        $worked = $this->eu('Recent Worked', 'approved', 'w@test.com');
        ProcessStep::create([
            'end_user_id' => $worked->id, 'round' => 1, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $va->id,
        ]);

        // Moved to Clients/Done in the window, but its only Week-1 step is OLD
        // (13h ago). Being listed is NOT work → must NOT appear.
        $listed = $this->eu('Merely Listed', 'done', 'l@test.com');
        $this->assertNotNull($listed->fresh()->listed_at);
        $listedStep = ProcessStep::create([
            'end_user_id' => $listed->id, 'round' => 1, 'week' => 1,
            'step_type' => 'ftc_and_freezes', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);
        ProcessStep::where('id', $listedStep->id)->update(['created_at' => now()->subHours(13)]);

        // Old step (13h ago) → hidden.
        $old = $this->eu('Old Worked', 'approved', 'o@test.com');
        $oldStep = ProcessStep::create([
            'end_user_id' => $old->id, 'round' => 1, 'week' => 1,
            'step_type' => 'phone_call_disputes', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);
        ProcessStep::where('id', $oldStep->id)->update(['created_at' => now()->subHours(13)]);

        // Recent step but NOT week 1 (a later-week CFPB, like the reported bug) → hidden.
        $laterWeek = $this->eu('Later Week Only', 'approved', 'f@test.com');
        ProcessStep::create([
            'end_user_id' => $laterWeek->id, 'round' => 1, 'week' => 2,
            'step_type' => 'cfpb_3b_and_innovis', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);

        $resp = $this->actingAs($this->super, 'admin')->get('/admin/daily-task')->assertOk();

        $resp->assertSee('Alin');            // business owner block
        $resp->assertSee('Recent Worked');   // via in-window Week-1 step
        $resp->assertSee('Bea');             // the VA who logged it
        $resp->assertSee('EX, TU, EQ Letter Generated'); // what was marked

        $resp->assertDontSee('Merely Listed'); // moved to Done, no in-window step → excluded
        $resp->assertDontSee('Old Worked');
        $resp->assertDontSee('Later Week Only');     // week 2 step → excluded (only week 1 counts)
        $resp->assertDontSee('Phone Call Disputes'); // the old step's label must not leak
        $resp->assertDontSee('New to Clients');      // the listed concept is gone
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
