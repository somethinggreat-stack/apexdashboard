<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Models\RoundSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Round selection is the ONLY signal the Daily Task / Tasks View trust. A row is
 * written when a round is added to the rounds strip (picker, first step marking a
 * round, closeout advancing) and attributed to whoever did it. Filling missing
 * process steps — the "Mark All Incomplete Complete" button — must never write
 * one, so the super admin is never credited as having worked a client.
 */
class RoundSelectionTrackingTest extends TestCase
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

    private function client(array $attrs = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id' => $this->bo->id, 'first_name' => 'C', 'last_name' => uniqid(), 'suffix' => 'None',
            'email' => uniqid() . '@t.com', 'current_address' => '1 St', 'city' => 'T', 'state' => 'ST',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->subDays(40)->toDateString(),
            'intake_status' => null, 'rounds' => [],
        ], $attrs));
    }

    public function test_growing_the_rounds_strip_records_a_selection_with_the_actor(): void
    {
        $eu = $this->client(['rounds' => ['1st Round']]);   // created, not updated → no event yet
        $this->assertSame(0, RoundSelection::count());

        // A VA selects the 2nd round on the strip.
        $this->actingAs($this->va, 'admin');
        $eu->update(['rounds' => ['1st Round', '2nd Round']]);

        $sel = RoundSelection::all();
        $this->assertCount(1, $sel);
        $this->assertSame(2, (int) $sel->first()->round);
        $this->assertSame($this->va->id, $sel->first()->admin_id);
    }

    public function test_logging_the_first_step_of_a_new_round_records_a_selection(): void
    {
        $eu = $this->client(['rounds' => []]);

        $this->actingAs($this->va, 'admin')
            ->withSession(['selected_client_id' => $this->bo->id])
            ->post(route('admin.process-steps.store'), [
                'end_user_id' => $eu->id,
                'round'       => 1,
                'week'        => 1,
                'step_types'  => ['ex_tu_eq_letters_generated'],
                'step_date'   => now()->toDateString(),
            ])->assertSessionHasNoErrors();

        $sel = RoundSelection::where('end_user_id', $eu->id)->get();
        $this->assertCount(1, $sel);
        $this->assertSame(1, (int) $sel->first()->round);
        $this->assertSame($this->va->id, $sel->first()->admin_id);
    }

    public function test_the_button_records_no_round_selection(): void
    {
        // On Round 1, 10 days in, Week 1 fully logged but the Week-2 follow-up is
        // missing — exactly what the button fills. Round 1 is already on the strip.
        $eu = $this->client([
            'rounds' => ['1st Round'],
            'round_dates' => ['1st Round' => now()->subDays(10)->toDateString()],
        ]);
        foreach (array_keys(ProcessStep::stepTypesByWeek(30)[1]) as $type) {
            ProcessStep::create([
                'end_user_id' => $eu->id, 'round' => 1, 'week' => 1, 'step_type' => $type,
                'step_date' => now()->subDays(10)->toDateString(), 'created_by_admin_id' => $this->va->id,
            ]);
        }

        $before = RoundSelection::count();

        $this->actingAs($this->super, 'admin')
            ->post(route('admin.end-users.clear-incomplete-all'))
            ->assertRedirect();

        // It filled the missing follow-up step…
        $this->assertGreaterThan(0, ProcessStep::where('end_user_id', $eu->id)->where('week', 2)->count());
        // …but credited NO ONE: no round was selected, so the super never appears
        // on the Daily Task / Tasks View for this client.
        $this->assertSame($before, RoundSelection::count());
    }
}
