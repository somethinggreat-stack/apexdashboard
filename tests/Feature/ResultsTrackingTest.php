<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\NegativeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResultsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Client $clinecea;   // results tracking ON
    private Client $other;      // results tracking OFF

    private function seedWorld(): void
    {
        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        $this->clinecea = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Clinecea Phillips', 'email' => 'cp@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'results_tracking' => true,
        ]);

        $this->other = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Benny', 'email' => 'benny@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'results_tracking' => false,
        ]);
    }

    private function eu(Client $bo, string $first, array $attrs = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id' => $bo->id, 'first_name' => $first, 'last_name' => 'X', 'suffix' => 'None',
            'email' => strtolower($first) . '@t.com', 'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-08-01',
            'intake_status' => 'done', 'rounds' => ['1st Round', '2nd Round', '3rd Round'],
        ], $attrs));
    }

    private function item(EndUser $eu, string $name, string $goal, ?string $resolvedOn, string $openedOn = '2026-08-01'): NegativeItem
    {
        $status = 'reporting';
        if ($resolvedOn) {
            $status = $goal === 'update' ? 'updated' : 'deleted';
        }
        return $eu->negativeItems()->create([
            'name' => $name, 'category' => 'negative_account', 'goal' => $goal,
            'status' => $status, 'opened_on' => $openedOn, 'resolved_at' => $resolvedOn,
        ]);
    }

    private function addClientPayload(array $extra = []): array
    {
        return array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'suffix' => 'None', 'email' => 'jane@t.com',
            'phone' => '555-1', 'date_of_birth' => '1990-01-01', 'current_address' => '1 St',
            'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345', 'ssn' => '123456789',
            'credit_monitoring_name' => 'IdentityIQ', 'credit_monitoring_username' => 'jane',
            'credit_monitoring_password' => 'pw', 'credit_monitoring_security_answer' => 'blue',
            'start_date' => '2026-08-01',
        ], $extra);
    }

    public function test_add_client_saves_negative_items_for_enabled_owner(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->post('/admin/end-users', $this->addClientPayload([
                'negative_items' => [
                    ['name' => 'Portfolio Recovery', 'category' => 'negative_account', 'goal' => 'delete', 'bureau' => 'experian'],
                    ['name' => 'Capital One', 'category' => 'negative_account', 'goal' => 'update', 'bureau' => ''],
                    ['name' => '', 'category' => 'negative_account', 'goal' => 'delete', 'bureau' => ''], // blank → ignored
                ],
            ]))->assertRedirect();

        $eu = EndUser::where('email', 'jane@t.com')->firstOrFail();
        $this->assertCount(2, $eu->negativeItems);
        $this->assertEquals('2026-08-01', $eu->negativeItems->first()->opened_on->toDateString());
        $this->assertSame('update', $eu->negativeItems->firstWhere('name', 'Capital One')->goal);
    }

    public function test_only_negative_accounts_can_have_the_update_goal(): void
    {
        $this->seedWorld();

        // Add Client path: update goal survives only for negative accounts.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->post('/admin/end-users', $this->addClientPayload([
                'negative_items' => [
                    ['name' => 'Cap One', 'category' => 'negative_account', 'goal' => 'update', 'bureau' => 'all'],
                    ['name' => 'Hard Pull', 'category' => 'inquiry', 'goal' => 'update', 'bureau' => 'all'],
                    ['name' => 'Old Address', 'category' => 'personal_information', 'goal' => 'update', 'bureau' => 'all'],
                ],
            ]))->assertRedirect();

        $eu = EndUser::where('email', 'jane@t.com')->firstOrFail();
        $this->assertSame('update', $eu->negativeItems->firstWhere('name', 'Cap One')->goal);
        $this->assertSame('delete', $eu->negativeItems->firstWhere('name', 'Hard Pull')->goal);
        $this->assertSame('delete', $eu->negativeItems->firstWhere('name', 'Old Address')->goal);

        // Results-tab add path: an inquiry with goal=update is forced to delete.
        $kay = $this->eu($this->clinecea, 'Kay');
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->post('/admin/negative-items', ['end_user_id' => $kay->id, 'name' => 'Inq', 'category' => 'inquiry', 'goal' => 'update', 'bureau' => 'all'])
            ->assertRedirect();
        $this->assertSame('delete', $kay->negativeItems()->first()->goal);
    }

    public function test_add_client_is_a_full_page(): void
    {
        $this->seedWorld();
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->get('/admin/end-users/create')
            ->assertOk()
            ->assertSee('Add Client')
            ->assertSee('Negative Items');
    }

    public function test_item_detail_is_kept_per_category(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->post('/admin/end-users', $this->addClientPayload([
                'negative_items' => [
                    ['category' => 'negative_account', 'name' => 'SYNCHRONY BANK', 'detail' => '224825XX', 'goal' => 'delete', 'bureau' => 'experian'],
                    ['category' => 'inquiry', 'name' => 'NAVY FCU', 'detail' => '08/08/2025', 'goal' => 'delete', 'bureau' => 'equifax'],
                    ['category' => 'bankruptcy', 'name' => 'CHAPTER 7', 'detail' => 'BK-123', 'goal' => 'delete', 'bureau' => 'transunion'],
                    ['category' => 'personal_information', 'name' => '11006 S STATE ST', 'detail' => 'dropme', 'goal' => 'delete', 'bureau' => 'all'],
                ],
            ]))->assertRedirect();

        $eu = EndUser::where('email', 'jane@t.com')->firstOrFail();
        $this->assertSame('224825XX', $eu->negativeItems->firstWhere('name', 'SYNCHRONY BANK')->detail);
        $this->assertSame('08/08/2025', $eu->negativeItems->firstWhere('name', 'NAVY FCU')->detail);
        $this->assertSame('BK-123', $eu->negativeItems->firstWhere('name', 'CHAPTER 7')->detail);
        // Personal Information has no detail — it's dropped.
        $this->assertNull($eu->negativeItems->firstWhere('name', '11006 S STATE ST')->detail);
    }

    public function test_add_client_requires_at_least_one_item_for_clinecea(): void
    {
        $this->seedWorld();

        // No negative_items at all → rejected, client not created.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->post('/admin/end-users', $this->addClientPayload())
            ->assertSessionHasErrors('negative_items');

        $this->assertSame(0, EndUser::where('client_id', $this->clinecea->id)->count());
    }

    public function test_items_do_not_save_for_owner_without_tracking(): void
    {
        $this->seedWorld();

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->other->id])
            ->post('/admin/end-users', $this->addClientPayload([
                'email' => 'nо@t.com', 'negative_items' => [['name' => 'X', 'category' => 'negative_account', 'goal' => 'delete']],
            ]))->assertRedirect();

        $this->assertSame(0, NegativeItem::count());
    }

    public function test_item_management_is_404_for_non_tracking_owner(): void
    {
        $this->seedWorld();
        $eu = $this->eu($this->other, 'Ben');

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->other->id])
            ->post('/admin/negative-items', ['end_user_id' => $eu->id, 'name' => 'X', 'category' => 'negative_account', 'goal' => 'delete'])
            ->assertNotFound();
    }

    public function test_resolve_marks_delete_and_update_correctly(): void
    {
        $this->seedWorld();
        $eu = $this->eu($this->clinecea, 'Jane');
        $del = $this->item($eu, 'A', 'delete', null);
        $upd = $this->item($eu, 'B', 'update', null);

        $sess = ['selected_client_id' => $this->clinecea->id];
        $this->actingAs($this->super, 'admin')->withSession($sess)
            ->post("/admin/negative-items/{$del->id}/resolve", ['resolved_on' => '2026-08-10', 'resolved_round' => 2])
            ->assertRedirect();
        $this->actingAs($this->super, 'admin')->withSession($sess)
            ->post("/admin/negative-items/{$upd->id}/resolve", ['resolved_on' => '2026-08-11'])
            ->assertRedirect();

        $this->assertSame('deleted', $del->refresh()->status);
        $this->assertSame(2, $del->resolved_round);
        $this->assertSame('updated', $upd->refresh()->status);
    }

    public function test_monthly_report_computes_each_month_independently(): void
    {
        $this->seedWorld();
        $eu = $this->eu($this->clinecea, 'Jane');
        $this->item($eu, 'A', 'delete', '2026-08-10');   // deleted Aug
        $this->item($eu, 'B', 'delete', '2026-08-20');   // deleted Aug
        $this->item($eu, 'C', 'update', '2026-08-15');   // updated Aug
        $this->item($eu, 'D', 'delete', null);           // reporting
        $this->item($eu, 'E', 'delete', '2026-09-05');   // deleted Sep

        $sess = ['selected_client_id' => $this->clinecea->id];

        // August: came in 5, deleted 2, updated 1, remaining 2 (D, E still reporting end of Aug).
        $this->actingAs($this->super, 'admin')->withSession($sess)->get('/admin/results/monthly?month=2026-08')
            ->assertOk()
            ->assertSee('Came into month with (5)')
            ->assertSee('Deleted this month (2)')
            ->assertSee('Updated to positive this month (1)')
            ->assertSee('Remaining (2)');

        // September: came in 2 (D, E), deleted 1 (E), remaining 1 (D).
        $this->actingAs($this->super, 'admin')->withSession($sess)->get('/admin/results/monthly?month=2026-09')
            ->assertOk()
            ->assertSee('Came into month with (2)')
            ->assertSee('Deleted this month (1)')
            ->assertSee('Remaining (1)');
    }

    public function test_eod_flags_nearing_completion(): void
    {
        $this->seedWorld();
        $eu = $this->eu($this->clinecea, 'Jane');
        $this->item($eu, 'A', 'delete', null);   // 2 reporting → nearing completion
        $this->item($eu, 'B', 'delete', null);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->get('/admin/results/eod')
            ->assertOk()
            ->assertSee('Jane X')                // full_name (first + last)
            ->assertSee('Nearing completion');
    }

    public function test_round_approval_flow(): void
    {
        $this->seedWorld();
        $eu = $this->eu($this->clinecea, 'Jane');
        $sess = ['selected_client_id' => $this->clinecea->id];

        $this->actingAs($this->super, 'admin')->withSession($sess)
            ->post("/admin/end-users/{$eu->id}/request-approval")->assertRedirect();
        $this->assertSame('awaiting', $eu->refresh()->round_approval_status);
        $this->assertSame(4, $eu->round_approval_round);   // on round 3 → next is 4

        $this->actingAs($this->super, 'admin')->withSession($sess)
            ->post("/admin/end-users/{$eu->id}/approve-round")->assertRedirect();
        $this->assertSame('approved', $eu->refresh()->round_approval_status);
    }

    public function test_client_show_page_renders_results_tab_only_when_enabled(): void
    {
        $this->seedWorld();
        $jane = $this->eu($this->clinecea, 'Jane');
        $this->item($jane, 'Portfolio Recovery', 'delete', null);
        $ben = $this->eu($this->other, 'Ben');

        // Clinecea selected → Results tab + sidebar report links appear.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->clinecea->id])
            ->get("/admin/end-users/{$jane->id}")
            ->assertOk()
            ->assertSee('Negative Items')
            ->assertSee('Portfolio Recovery')
            ->assertSee('EOD Report')
            ->assertSee('Monthly Results');

        // A non-tracking owner selected → none of it appears.
        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->other->id])
            ->get("/admin/end-users/{$ben->id}")
            ->assertOk()
            ->assertDontSee('Negative Items &amp; Results')
            ->assertDontSee('EOD Report')
            ->assertDontSee('Monthly Results');
    }

    public function test_leads_cannot_reach_results_reports(): void
    {
        $this->seedWorld();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret-pass', 'full_name' => 'VA']);
        $va->role = 'va'; $va->parent_admin_id = $this->super->id; $va->save();

        $leads = new Admin(['email' => 'leads@test.com', 'password' => 'secret-pass', 'full_name' => 'Leads']);
        $leads->role = 'leads'; $leads->parent_admin_id = $this->super->id; $leads->save();

        $sess = ['selected_client_id' => $this->clinecea->id];
        $this->actingAs($this->super, 'admin')->withSession($sess)->get('/admin/results/eod')->assertOk();
        $this->actingAs($va, 'admin')->withSession($sess)->get('/admin/results/monthly')->assertOk();
        $this->actingAs($leads, 'admin')->get('/admin/results/eod')->assertStatus(403);
    }
}
