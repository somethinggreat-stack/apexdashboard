<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inactive = gone / not paying: its own list, out of Hold, out of the EOD.
 * Hold = paused, expected back. Round-limited clients go to Hold on their own
 * once their last allowed round is closed out (report pulled + deletions).
 */
class InactiveAndAutoHoldTest extends TestCase
{
    use RefreshDatabase;

    private Admin $va;
    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $super = new Admin(['email' => 'super@t.com', 'password' => 'x', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $this->va = new Admin(['email' => 'va@t.com', 'password' => 'x', 'full_name' => 'Vee']);
        $this->va->role = 'va';
        $this->va->parent_admin_id = $super->id;
        $this->va->save();

        $this->bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function client(string $last, array $attrs = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id' => $this->bo->id, 'first_name' => 'C', 'last_name' => $last, 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(40)->toDateString(),
            'intake_status' => 'done', 'rounds' => ['1st Round'],
        ], $attrs));
    }

    private function asVa()
    {
        return $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->bo->id]);
    }

    public function test_inactive_leaves_hold_and_every_list_and_comes_back_on_reactivate(): void
    {
        $eu = $this->client('Gone', ['held_at' => now()]);

        $this->asVa()->post(route('admin.end-users.inactive', $eu->id), ['reason' => 'Not paid per Sheet'])->assertRedirect();
        $eu->refresh();
        $this->assertNotNull($eu->inactive_at);
        $this->assertNotNull($eu->held_at);

        // (Checked on the list data — the flash message itself names the client.)
        $this->asVa()->get(route('admin.hold'))->assertOk()
            ->assertViewHas('endUsers', fn ($l) => ! $l->contains('id', $eu->id));
        $this->asVa()->get(route('admin.client-list'))->assertOk()
            ->assertViewHas('endUsers', fn ($l) => ! collect($l->items())->contains('id', $eu->id));
        $this->asVa()->get(route('admin.inactive'))->assertOk()->assertSee('C Gone')->assertSee('Not paid per Sheet');
        $this->assertSame(0, (int) $this->bo->navCounts()['hold']);

        $this->asVa()->post(route('admin.end-users.reactivate', $eu->id))->assertRedirect();
        $eu->refresh();
        $this->assertNull($eu->inactive_at);
        $this->assertNull($eu->held_at);
        $this->asVa()->get(route('admin.client-list'))->assertSee('C Gone');
    }

    public function test_inactive_needs_a_reason(): void
    {
        $eu = $this->client('NoReason');
        $this->asVa()->post(route('admin.end-users.inactive', $eu->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertNull($eu->fresh()->inactive_at);
    }

    public function test_owner_sees_inactive_read_only(): void
    {
        $this->client('Gone', ['held_at' => now(), 'inactive_at' => now(), 'move_reason' => 'Stopped service']);

        $this->actingAs($this->bo, 'client')->get(route('client.inactive'))
            ->assertOk()->assertSee('C Gone')->assertSee('Stopped service');
        $this->actingAs($this->bo, 'client')->get(route('client.hold'))
            ->assertOk()->assertDontSee('C Gone');
    }

    private function closeOut(EndUser $eu, int $round): void
    {
        foreach (EndUser::CLOSEOUT_STEPS as $type) {
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => $round, 'week' => 4,
                'step_type' => $type, 'step_date' => now()->toDateString(), 'created_by_admin_id' => $this->va->id,
            ]);
        }
    }

    public function test_round_two_closeout_puts_a_two_round_client_on_hold(): void
    {
        $eu = $this->client('Limited', ['rounds' => ['1st Round', '2nd Round'], 'intake_status' => 'round_error']);
        $eu->forceFill(['round_limit' => 2])->save();

        // Only one closeout step → not yet.
        ProcessStep::create(['end_user_id' => $eu->id, 'round' => 2, 'week' => 4,
            'step_type' => 'pull_latest_report', 'step_date' => now()->toDateString(), 'created_by_admin_id' => $this->va->id]);
        $this->assertNull($eu->fresh()->held_at);

        ProcessStep::create(['end_user_id' => $eu->id, 'round' => 2, 'week' => 4,
            'step_type' => 'record_deletions', 'step_date' => now()->toDateString(), 'created_by_admin_id' => $this->va->id]);
        $eu->refresh();
        $this->assertNotNull($eu->held_at);
        $this->assertSame('2 rounds completed. Progress report sent. On hold pending payment for additional rounds.', $eu->move_reason);
    }

    public function test_round_one_closeout_and_unlimited_clients_are_not_held(): void
    {
        $limited = $this->client('Limited');
        $limited->forceFill(['round_limit' => 2])->save();
        $this->closeOut($limited, 1);
        $this->assertNull($limited->fresh()->held_at);

        $free = $this->client('Free', ['rounds' => ['1st Round', '2nd Round']]);
        $this->closeOut($free, 2);
        $this->assertNull($free->fresh()->held_at);
    }
}
