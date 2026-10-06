<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Results tracking was enabled for one owner —
 * Clinecea — for over a month. Genius Credit Boutique is the second.
 *
 * These tests exist because "works for the first owner" and "works for any owner"
 * are different claims, and the difference was already hiding a bug: the approve
 * button was hardcoded to read "Clinecea approved ✓", which nobody could see while
 * Clinecea was the only owner who had the panel.
 */
class ResultsTrackingSecondOwnerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $this->super->role = 'super';
        $this->super->save();
    }

    private function owner(string $name, bool $tracking): Client
    {
        return Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name,
            'email' => md5($name) . '@test.com', 'password' => 'secret-pass',
            'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
            'results_tracking' => $tracking,
        ]);
    }

    private function client(Client $owner): EndUser
    {
        return EndUser::create([
            'client_id' => $owner->id, 'first_name' => 'Dominique', 'last_name' => 'Johnson',
            'email' => 'd' . $owner->id . '@example.com', 'status' => 'active',
            'intake_status' => 'done',
            'start_date' => now()->subDays(40)->toDateString(),
            'rounds' => ['1st Round'], 'round_dates' => ['1st Round' => now()->subDays(40)->toDateString()],
        ]);
    }

    /** Act as the super admin with a business owner selected, as the console does. */
    private function asOwner(Client $owner)
    {
        return $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $owner->id]);
    }

    public function test_the_approve_button_shows_the_owners_own_name_not_clineceas(): void
    {
        $genius = $this->owner('Genius Credit Boutique', true);
        $e = $this->client($genius);

        // Park it awaiting approval, which is when the approve button appears.
        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/request-approval');
        $this->assertSame('awaiting', $e->fresh()->round_approval_status);

        $page = $this->asOwner($genius)->get('/admin/end-users/' . $e->id)->assertOk();

        $page->assertSee('Genius Credit Boutique approved', false);
        $page->assertDontSee('Clinecea approved', false);
    }

    public function test_the_approval_flow_works_end_to_end_for_a_second_owner(): void
    {
        $genius = $this->owner('Genius Credit Boutique', true);
        $e = $this->client($genius);

        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/request-approval')->assertRedirect();
        $this->assertSame('awaiting', $e->fresh()->round_approval_status);

        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/approve-round')->assertRedirect();
        $this->assertSame('approved', $e->fresh()->round_approval_status);

        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/clear-approval')->assertRedirect();
        $this->assertNull($e->fresh()->round_approval_status);
    }

    public function test_an_owner_without_the_flag_gets_the_approval_flow_too(): void
    {
        // Sent for Approval is on for every owner; only the negative-items/results
        // features stay behind the results_tracking flag.
        $other = $this->owner('Someone Else Ltd', false);
        $e = $this->client($other);

        $this->asOwner($other)->post('/admin/end-users/' . $e->id . '/request-approval')->assertRedirect();
        $this->assertSame('awaiting', $e->fresh()->round_approval_status);

        $this->asOwner($other)->get('/admin/sent-for-approval')->assertOk()->assertSee('Dominique');
        $this->asOwner($other)->post('/admin/end-users/' . $e->id . '/clear-approval')->assertRedirect();
        $this->assertNull($e->fresh()->round_approval_status);
    }

    public function test_the_approval_flow_cannot_reach_another_organisations_client(): void
    {
        $otherAdmin = new Admin(['email' => 'other@test.com', 'password' => 'secret-pass', 'full_name' => 'Other Org']);
        $otherAdmin->role = 'super';
        $otherAdmin->save();
        $theirs = Client::create([
            'admin_id' => $otherAdmin->id, 'business_name' => 'Their Owner',
            'email' => 'their@test.com', 'password' => 'secret-pass',
            'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
        ]);
        $e = $this->client($theirs);

        $this->asOwner($this->owner('Ours Ltd', false))
            ->post('/admin/end-users/' . $e->id . '/request-approval')->assertNotFound();
        $this->assertNull($e->fresh()->round_approval_status);
    }

    public function test_approving_does_not_advance_the_round_or_credit_anyone(): void
    {
        // Same guarantee the JARVIS write slice relies on, asserted for a second
        // owner: approval is a flag, not an advance.
        $genius = $this->owner('Genius Credit Boutique', true);
        $e = $this->client($genius);
        $before = $e->rounds;

        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/request-approval');
        $this->asOwner($genius)->post('/admin/end-users/' . $e->id . '/approve-round');

        $this->assertSame($before, $e->fresh()->rounds);
        $this->assertSame(0, DB::table('round_selections')->count());
    }

    public function test_the_migration_enables_only_genius(): void
    {
        $genius = $this->owner('Genius Credit Boutique', false);
        $clinecea = $this->owner('Clinecea Phillips', true);
        $other = $this->owner('Genius Something Else', false);   // must NOT be caught

        // What the migration does.
        DB::table('clients')->where('business_name', 'like', 'Genius Credit Boutique%')
            ->update(['results_tracking' => true]);

        $this->assertTrue((bool) $genius->fresh()->results_tracking);
        $this->assertTrue((bool) $clinecea->fresh()->results_tracking, 'Clinecea was disturbed');
        $this->assertFalse((bool) $other->fresh()->results_tracking, 'a similarly-named owner was caught');
    }
}
