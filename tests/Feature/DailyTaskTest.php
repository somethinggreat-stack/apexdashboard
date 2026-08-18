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

        // Recent process step → client + VA appear.
        $worked = $this->eu('Recent Worked', 'approved', 'w@test.com');
        ProcessStep::create([
            'end_user_id' => $worked->id, 'round' => 1, 'week' => 1,
            'step_type' => 'ex_tu_eq_letters_generated', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);

        // Newly listed in Clients (created as done → listed_at stamped now).
        $listed = $this->eu('Newly Listed', 'done', 'l@test.com');
        $this->assertNotNull($listed->fresh()->listed_at);

        // Old step (13h ago) → hidden.
        $old = $this->eu('Old Worked', 'approved', 'o@test.com');
        $oldStep = ProcessStep::create([
            'end_user_id' => $old->id, 'round' => 1, 'week' => 1,
            'step_type' => 'phone_call_disputes', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);
        ProcessStep::where('id', $oldStep->id)->update(['created_at' => now()->subHours(13)]);

        // Old listing (13h ago) → hidden.
        $oldListed = $this->eu('Old Listed', 'done', 'ol@test.com');
        EndUser::where('id', $oldListed->id)->update(['listed_at' => now()->subHours(13)]);

        // Recent step but NOT week 1 (a later-week CFPB, like the reported bug) → hidden.
        $laterWeek = $this->eu('Later Week Only', 'approved', 'f@test.com');
        ProcessStep::create([
            'end_user_id' => $laterWeek->id, 'round' => 1, 'week' => 2,
            'step_type' => 'cfpb_3b_and_innovis', 'step_date' => '2026-06-02',
            'created_by_admin_id' => $this->super->id,
        ]);

        $resp = $this->actingAs($this->super, 'admin')->get('/admin/daily-task')->assertOk();

        $resp->assertSee('Alin');            // business owner block
        $resp->assertSee('Recent Worked');   // via process step
        $resp->assertSee('Umair');           // the VA who logged it
        $resp->assertSee('EX, TU, EQ Letter Generated'); // what was marked
        $resp->assertSee('Newly Listed');    // via listed_at
        $resp->assertSee('New to Clients');

        $resp->assertDontSee('Old Worked');
        $resp->assertDontSee('Old Listed');
        $resp->assertDontSee('Later Week Only');     // week 2 step → excluded (only week 1 counts)
        $resp->assertDontSee('Phone Call Disputes'); // the old step's label must not leak
    }

    public function test_only_super_admin_can_open_daily_task(): void
    {
        $this->seedWorld();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'VA']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        $this->actingAs($va, 'admin')->get('/admin/daily-task')->assertStatus(403);
        $this->actingAs($this->super, 'admin')->get('/admin/daily-task')->assertOk();
    }
}
