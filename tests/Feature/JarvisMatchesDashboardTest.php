<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
use App\Services\OwnerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API must never disagree with the screens Umair works from.
 *
 * It did, once, and badly: $20 outstanding against the dashboard's $5,414, 112
 * overdue against 195, 4 new clients against 1. Every gap came from the API having
 * its own definition of which clients count — definitions that looked reasonable
 * and were wrong.
 *
 * The fix was structural: one implementation (OwnerSnapshot) behind the dashboard,
 * the business-owner picker and the API. These tests are the smoke alarm on top of
 * that — they compare what the API returns against what the dashboard renders, so
 * if anyone ever reintroduces a second definition, this fails.
 */
class JarvisMatchesDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-jarvis-token-do-not-use-in-production';

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jarvis.token' => self::TOKEN]);

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();

        // `admins.role` defaults to 'super', so the seeded admin@umair.com counts as
        // one too. Without this the API would resolve that dormant account and report
        // zeros — which is exactly what happened the first time these tests ran.
        config(['jarvis.owner_admin_id' => $this->super->id]);
    }

    public function test_a_dormant_super_admin_never_becomes_the_owner(): void
    {
        // No explicit id: resolution must still find the admin who owns the clients,
        // not whichever super-by-default account happens to have the lowest id.
        config(['jarvis.owner_admin_id' => null, 'team.super.email' => 'nobody@nowhere.test']);

        $owner = $this->owner('Clinecea Phillips');
        $this->client($owner, 'Late', 2, 50);

        $this->assertDatabaseHas('admins', ['email' => 'admin@umair.com', 'role' => 'super']);

        $this->assertGreaterThan(0, OwnerSnapshot::forSuperAdmin()->attentionTotals()['owners'],
            'resolution picked a super admin with no business owners');
    }

    private function ask(string $path)
    {
        return $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)->getJson('/api/jarvis/' . $path);
    }

    private function owner(string $name, int $cycle = 30, float $fee = 100): Client
    {
        return Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@test.com', 'password' => 'secret-pass',
            'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => $cycle,
            'compensation_model' => 'per_round', 'per_round_fee' => $fee,
        ]);
    }

    /** A client on a given round, started N days ago. */
    private function client(Client $owner, string $first, int $roundsReached, int $startedDaysAgo, array $attr = []): EndUser
    {
        $labels = array_slice(EndUser::ROUND_OPTIONS, 0, $roundsReached);
        $dates = [];
        foreach ($labels as $i => $label) {
            $dates[$label] = now()->subDays($startedDaysAgo + (count($labels) - 1 - $i) * 30)->toDateString();
        }

        return EndUser::create(array_merge([
            'client_id' => $owner->id, 'first_name' => $first, 'last_name' => 'Client',
            'email' => strtolower($first) . '@example.com', 'status' => 'active',
            'start_date' => now()->subDays($startedDaysAgo + 30)->toDateString(),
            'rounds' => $labels, 'round_dates' => $dates,
        ], $attr));
    }

    /** Exactly what DashboardController hands the Blade view. */
    private function dashboard(): array
    {
        $snapshot = OwnerSnapshot::forAdmin($this->super);

        return [
            'totals'   => $snapshot->attentionTotals(),
            'balances' => $snapshot->balanceTotals(),
        ];
    }

    // ---- money --------------------------------------------------------------------------

    public function test_outstanding_matches_the_business_owner_balances_panel(): void
    {
        $a = $this->owner('Genius Credit Boutique', 30, 245);
        $b = $this->owner('Clinecea Phillips', 20, 100);
        $this->client($a, 'Aaa', 2, 40);
        $this->client($b, 'Bbb', 3, 10);

        $panel = $this->dashboard()['balances'];
        $this->assertGreaterThan(0, $panel['outstanding'], 'the fixture produced no balance to compare');

        // 1. The summary's total is the panel's total. (Compared by value: JSON
        //    renders 790.0 as 790, so assertJsonPath's strict compare would fail on
        //    the type rather than on the number being wrong.)
        $summary = $this->ask('summary')->assertOk()->json('outstanding_invoices');
        $this->assertEquals($panel['outstanding'], $summary['total']);
        $this->assertEquals($panel['collected'], $summary['collected_total']);

        // 2. And the per-owner rows add up to exactly that, which is the check that
        //    would have caught the $20: the rows were near-zero while the panel was not.
        $rows = $this->ask('business-owners?per_page=200')->assertOk()->json('data');
        $summed = round(array_sum(array_column($rows, 'outstanding_invoice_total')), 2);

        $this->assertEquals($panel['outstanding'], $summed,
            'sum(outstanding_invoice_total) across /business-owners must equal the panel Total Outstanding');
    }

    public function test_a_single_owner_balance_matches_the_panel(): void
    {
        $owner = $this->owner('Genius Credit Boutique', 30, 245);
        $this->client($owner, 'Aaa', 2, 40);
        ClientPayment::create([
            'end_user_id' => EndUser::first()->id, 'round' => 1, 'amount' => 245,
            'paid_at' => now(), 'created_by_admin_id' => $this->super->id,
        ]);

        $expected = $owner->paymentTotals();
        $row = collect($this->ask('business-owners')->json('data'))->firstWhere('id', $owner->id);

        // assertEquals, not assertSame: JSON renders 245.0 as 245, so the type is
        // lost on the wire and only the value is meaningful.
        $this->assertEquals(round((float) $expected['pending'], 2), $row['outstanding_invoice_total']);
        $this->assertEquals(round((float) $expected['done'], 2), $row['collected_total']);
    }

    public function test_a_partially_selected_client_model_would_have_broken_this(): void
    {
        // The original bug, pinned so it cannot come back quietly: paymentTotals()
        // reads compensation_model and the per-round fee off the model it is called on.
        $owner = $this->owner('Genius Credit Boutique', 30, 245);
        $this->client($owner, 'Aaa', 2, 40);

        $full = Client::find($owner->id)->paymentTotals()['pending'];
        $starved = Client::query()->select(['id', 'business_name', 'status'])->find($owner->id)->paymentTotals()['pending'];

        $this->assertGreaterThan(0, $full, 'the fixture should owe something');
        $this->assertNotSame($full, $starved,
            'if these ever match, the starvation trap is gone and this test can be deleted');
    }

    // ---- the Needs Attention panel --------------------------------------------------------

    public function test_summary_new_incomplete_and_overdue_match_the_panel(): void
    {
        $owner = $this->owner('Clinecea Phillips');
        $this->client($owner, 'Overdue', 2, 45);                                   // past its 30-day mark
        $this->client($owner, 'Newbie', 1, 5, ['intake_status' => 'pending_review']);
        $this->client($owner, 'Held', 2, 45, ['held_at' => now()]);                // held: panel still counts it

        $panel = $this->dashboard()['totals'];

        $this->ask('summary')->assertOk()
            ->assertJsonPath('new_clients', $panel['new'])
            ->assertJsonPath('incomplete', $panel['incomplete'])
            ->assertJsonPath('owners_requiring_action', $panel['owners'])
            ->assertJsonPath('rounds_due.overdue', $panel['overdue']);
    }

    public function test_needs_attention_mirrors_the_panel_row_for_row(): void
    {
        $a = $this->owner('Clinecea Phillips');
        $b = $this->owner('PETER');
        $this->client($a, 'One', 2, 50);
        $this->client($a, 'Two', 2, 50);
        $this->client($b, 'Three', 2, 50);

        $snapshot = OwnerSnapshot::forAdmin($this->super);
        $panelRows = $snapshot->attentionRows();
        $panelTotals = $snapshot->attentionTotals();

        $res = $this->ask('needs-attention')->assertOk();

        $res->assertJsonPath('totals.new', $panelTotals['new'])
            ->assertJsonPath('totals.incomplete', $panelTotals['incomplete'])
            ->assertJsonPath('totals.overdue', $panelTotals['overdue'])
            ->assertJsonPath('totals.owners', $panelTotals['owners']);

        $apiRows = $res->json('owners');
        $this->assertCount(count($panelRows), $apiRows, 'row counts differ from the panel');

        foreach ($panelRows as $i => $panelRow) {
            $this->assertSame($panelRow['client']->id, $apiRows[$i]['business_owner_id'], "row {$i} is a different owner — ordering drifted");
            $this->assertSame($panelRow['client']->business_name, $apiRows[$i]['name']);
            $this->assertSame($panelRow['pending'], $apiRows[$i]['new']);
            $this->assertSame($panelRow['incomplete'], $apiRows[$i]['incomplete']);
            $this->assertSame($panelRow['overdue'], $apiRows[$i]['overdue']);
            // The literal label the screen renders, not a second banding.
            $this->assertSame($panelRow['priority'], $apiRows[$i]['priority']);
        }
    }

    public function test_the_priority_label_is_the_one_the_screen_renders(): void
    {
        // The banding lives in one place; these are the panel's own thresholds.
        $this->assertSame(['Critical', 4], OwnerSnapshot::priority(0, 0, 15));   // 45
        $this->assertSame(['High', 3], OwnerSnapshot::priority(0, 0, 7));        // 21
        $this->assertSame(['Medium', 2], OwnerSnapshot::priority(0, 8, 0));      // 8
        $this->assertSame(['Low', 1], OwnerSnapshot::priority(0, 1, 0));         // 1
    }

    public function test_needs_attention_reports_the_owners_round_cycle(): void
    {
        $owner = $this->owner('Twenty Day Owner', 20);
        $this->client($owner, 'Late', 2, 40);

        $row = $this->ask('needs-attention')->assertOk()->json('owners.0');

        // "9 days overdue" means something different on a 20-day cycle.
        $this->assertSame(20, $row['round_cycle_days']);
    }

    // ---- alerts ---------------------------------------------------------------------------

    public function test_alerts_is_paginated_like_every_other_list(): void
    {
        $owner = $this->owner('Clinecea Phillips');
        foreach (range(1, 5) as $i) {
            $this->client($owner, 'Late' . $i, 2, 60);
        }

        $res = $this->ask('alerts?per_page=2')->assertOk();

        $res->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']])
            ->assertJsonPath('meta.per_page', 2);
        $this->assertLessThanOrEqual(2, count($res->json('data')));
        $this->assertGreaterThan(2, $res->json('meta.total'), 'the fixture should produce more alerts than one page');

        // And the cap still applies here.
        $this->assertSame(config('jarvis.pagination.max'), $this->ask('alerts?per_page=99999')->json('meta.per_page'));
    }
}
