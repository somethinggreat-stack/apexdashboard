<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientEvent;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Round limit (the "2-round list"): a capped client is flagged in the main
 * Clients list once they reach their last round, can never be moved into a
 * round past it, and only a super admin can change the cap (logged).
 * Clients without a limit are untouched.
 */
class RoundLimitTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $va;
    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new Admin(['email' => 'super@t.com', 'password' => 'x', 'full_name' => 'Super']);
        $this->super->role = 'super';
        $this->super->save();

        $this->va = new Admin(['email' => 'va@t.com', 'password' => 'x', 'full_name' => 'Vee']);
        $this->va->role = 'va';
        $this->va->parent_admin_id = $this->super->id;
        $this->va->save();

        $this->bo = Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function client(array $rounds, ?int $limit = 2, string $last = 'Limited'): EndUser
    {
        $eu = EndUser::create([
            'client_id' => $this->bo->id, 'first_name' => 'C', 'last_name' => $last, 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(40)->toDateString(),
            'intake_status' => 'done', 'rounds' => $rounds,
        ]);
        $eu->forceFill(['round_limit' => $limit])->save();

        return $eu;
    }

    public function test_flag_states_follow_the_current_round(): void
    {
        $this->assertNull($this->client(['1st Round'])->round_limit_flag);
        $this->assertSame('at_limit', $this->client(['1st Round', '2nd Round'])->round_limit_flag);
        $this->assertSame('over_limit', $this->client(['1st Round', '2nd Round', '3rd Round'])->round_limit_flag);
        $this->assertNull($this->client(['1st Round', '2nd Round', '3rd Round'], null)->round_limit_flag);
    }

    public function test_round_three_cannot_be_started_from_any_path(): void
    {
        $eu = $this->client(['1st Round', '2nd Round']);

        // Model level (covers Jarvis and every editor).
        try {
            $eu->update(['rounds' => ['1st Round', '2nd Round', '3rd Round']]);
            $this->fail('Round 3 was allowed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('2-round limit', $e->errors()['rounds'][0]);
        }

        // The list round picker / Edit Rounds & Dates endpoint.
        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->putJson(route('admin.end-users.update', $eu->id), [
                'rounds_present' => '1', 'rounds' => ['1st Round', '2nd Round', '3rd Round'],
            ])->assertStatus(422)->assertJsonValidationErrors('rounds');

        $this->assertSame(['1st Round', '2nd Round'], $eu->fresh()->rounds);
    }

    public function test_reaching_round_two_and_removing_rounds_still_work(): void
    {
        $eu = $this->client(['1st Round']);
        $eu->update(['rounds' => ['1st Round', '2nd Round']]);
        $this->assertSame('at_limit', $eu->fresh()->round_limit_flag);

        // A client already over the limit can be edited and walked back.
        $over = $this->client(['1st Round', '2nd Round', '3rd Round']);
        $over->update(['status' => 'paused']);
        $over->update(['rounds' => ['1st Round', '2nd Round']]);
        $this->assertSame('at_limit', $over->fresh()->round_limit_flag);
    }

    public function test_unlisted_clients_are_unaffected(): void
    {
        $eu = $this->client(['1st Round', '2nd Round'], null);
        $eu->update(['rounds' => ['1st Round', '2nd Round', '3rd Round']]);
        $this->assertSame(3, $eu->fresh()->current_round);
    }

    public function test_badges_and_filters_on_the_clients_list(): void
    {
        $this->client(['1st Round', '2nd Round'], 2, 'AtLimit');
        $this->client(['1st Round', '2nd Round', '3rd Round'], 2, 'OverLimit');
        $this->client(['1st Round'], 2, 'Below');
        $this->client(['1st Round', '2nd Round'], null, 'Normal');

        $page = fn (array $q = []) => $this->actingAs($this->va, 'admin')
            ->withSession(['selected_client_id' => $this->bo->id])
            ->get(route('admin.client-list', $q));

        $page()->assertOk()
            ->assertSee('No more rounds')
            ->assertSee('Over limit (Round 3)');

        $page(['limit' => 'limited'])->assertSee('AtLimit')->assertSee('Below')->assertDontSee('Normal');
        $page(['limit' => 'flagged'])->assertSee('AtLimit')->assertSee('OverLimit')->assertDontSee('Below')->assertDontSee('Normal');
    }

    public function test_badge_shows_on_hold_and_round_error_lists_too(): void
    {
        $held = $this->client(['1st Round', '2nd Round'], 2, 'Held');
        $held->update(['held_at' => now()]);
        $err = $this->client(['1st Round', '2nd Round', '3rd Round'], 2, 'Errored');
        $err->update(['intake_status' => 'round_error']);

        $as = fn () => $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->bo->id]);

        $as()->get(route('admin.hold'))->assertOk()->assertSee('No more rounds');
        $as()->get(route('admin.round-errors'))->assertOk()->assertSee('Over limit (Round 3)');
    }

    public function test_only_super_admin_can_change_the_limit_and_it_is_logged(): void
    {
        $eu = $this->client(['1st Round', '2nd Round']);

        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put(route('admin.end-users.round-limit', $eu->id), ['round_limit' => ''])
            ->assertStatus(403);
        $this->assertSame(2, $eu->fresh()->round_limit);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->bo->id])
            ->put(route('admin.end-users.round-limit', $eu->id), ['round_limit' => ''])
            ->assertRedirect();

        $this->assertNull($eu->fresh()->round_limit);
        $this->assertDatabaseHas('client_events', [
            'end_user_id' => $eu->id, 'event' => 'round_limit', 'admin_id' => $this->super->id,
            'description' => 'Round limit changed from 2 rounds to no limit',
        ]);
    }

    public function test_round_limit_is_not_mass_assignable(): void
    {
        $eu = $this->client(['1st Round'], null);
        $eu->update(['round_limit' => 2]);
        $this->assertNull($eu->fresh()->round_limit);
    }
}
