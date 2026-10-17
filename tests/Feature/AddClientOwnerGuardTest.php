<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A client must land under the business owner the Add Client form was opened
 * for — even if "Working on" was switched in another tab — and the team is
 * warned when the same person already exists under a different owner. Super
 * admins can move a client to the right owner; every move is logged.
 */
class AddClientOwnerGuardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;
    private Admin $va;
    private Client $epps;
    private Client $miranda;

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

        $this->epps = $this->owner('Mr. Epps', 'epps@t.com');
        $this->miranda = $this->owner('Miranda', 'miranda@t.com');
    }

    private function owner(string $name, string $email): Client
    {
        return Client::create([
            'admin_id' => $this->super->id, 'business_name' => $name, 'email' => $email,
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'first_name' => 'Alana', 'last_name' => 'Jackson', 'suffix' => 'None',
            'email' => 'alana@t.com', 'phone' => '5551234567', 'date_of_birth' => '1990-01-01',
            'current_address' => '1 St', 'city' => 'T', 'state' => 'TX', 'zipcode' => '12345',
            'ssn' => '123456789', 'credit_monitoring_name' => 'MyFreeScoreNow',
            'credit_monitoring_username' => 'alana@t.com', 'credit_monitoring_password' => 'pw',
            'credit_monitoring_security_answer' => 'a', 'start_date' => now()->toDateString(),
        ], $extra);
    }

    private function client(Client $owner, string $first = 'Alana', string $last = 'Jackson', string $email = 'alana@t.com'): EndUser
    {
        return EndUser::create([
            'client_id' => $owner->id, 'first_name' => $first, 'last_name' => $last, 'suffix' => 'None',
            'email' => $email, 'current_address' => '1 St', 'city' => 'T', 'state' => 'TX',
            'zipcode' => '12345', 'status' => 'active', 'start_date' => now()->toDateString(),
            'intake_status' => 'done', 'ssn' => '123456789',
        ]);
    }

    public function test_add_client_page_shows_the_owner_and_asks_for_confirmation(): void
    {
        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->miranda->id])
            ->get(route('admin.end-users.create'))
            ->assertOk()
            ->assertSee('Adding to')
            ->assertSee('Miranda')
            ->assertSee('data-confirm-action', false)
            ->assertSee('name="client_id" value="' . $this->miranda->id . '"', false);
    }

    public function test_client_is_saved_to_the_owner_the_form_was_opened_for_not_the_session(): void
    {
        // Form opened for Miranda, but "Working on" was switched to Mr. Epps in another tab.
        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->epps->id])
            ->post(route('admin.end-users.store'), $this->payload(['client_id' => $this->miranda->id]))
            ->assertRedirect();

        $eu = EndUser::where('email', 'alana@t.com')->firstOrFail();
        $this->assertSame($this->miranda->id, $eu->client_id);
        $this->assertSame($this->miranda->id, session('selected_client_id'));
    }

    public function test_cannot_add_to_an_owner_outside_the_admins_org(): void
    {
        $outsider = new Admin(['email' => 'other@t.com', 'password' => 'x', 'full_name' => 'Other']);
        $outsider->role = 'super';
        $outsider->save();
        $foreign = Client::create([
            'admin_id' => $outsider->id, 'business_name' => 'Foreign', 'email' => 'f@t.com', 'password' => 'x',
            'monthly_fee' => 0, 'status' => 'active', 'intake_token' => \Illuminate\Support\Str::random(20),
        ]);

        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->epps->id])
            ->post(route('admin.end-users.store'), $this->payload(['client_id' => $foreign->id]))
            ->assertNotFound();
        $this->assertSame(0, EndUser::count());
    }

    public function test_duplicate_check_warns_when_the_client_exists_under_another_owner(): void
    {
        $this->client($this->miranda);
        $as = fn () => $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->epps->id]);

        foreach ([['email', 'alana@t.com'], ['ssn', '123456789'], ['name', 'alana jackson']] as [$field, $value]) {
            $as()->getJson(route('admin.end-users.dup-check', ['field' => $field, 'value' => $value]))
                ->assertOk()
                ->assertJson(['exists' => false])
                ->assertJsonPath('warn', fn ($w) => str_contains((string) $w, 'already a client of Miranda'));
        }

        // Same owner + same email still blocks, as before.
        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->miranda->id])
            ->getJson(route('admin.end-users.dup-check', ['field' => 'email', 'value' => 'alana@t.com']))
            ->assertJson(['exists' => true]);
    }

    public function test_super_admin_moves_a_client_to_another_owner_and_it_is_logged(): void
    {
        $eu = $this->client($this->epps);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->epps->id])
            ->put(route('admin.end-users.move-owner', $eu->id), [
                'target_client_id' => $this->miranda->id, 'reason' => 'Added to the wrong owner',
            ])->assertRedirect(route('admin.client-list'));

        $this->assertSame($this->miranda->id, $eu->fresh()->client_id);
        $this->assertDatabaseHas('client_events', [
            'end_user_id' => $eu->id, 'event' => 'owner_moved',
            'description' => 'Moved from Mr. Epps to Miranda — Added to the wrong owner',
        ]);
    }

    public function test_move_is_refused_when_target_owner_already_has_the_client(): void
    {
        $eu = $this->client($this->epps);
        $this->client($this->miranda);

        $this->actingAs($this->super, 'admin')->withSession(['selected_client_id' => $this->epps->id])
            ->put(route('admin.end-users.move-owner', $eu->id), [
                'target_client_id' => $this->miranda->id, 'reason' => 'x',
            ])->assertSessionHasErrors('target_client_id');

        $this->assertSame($this->epps->id, $eu->fresh()->client_id);
    }

    public function test_only_super_admin_can_move_a_client(): void
    {
        $eu = $this->client($this->epps);

        $this->actingAs($this->va, 'admin')->withSession(['selected_client_id' => $this->epps->id])
            ->put(route('admin.end-users.move-owner', $eu->id), [
                'target_client_id' => $this->miranda->id, 'reason' => 'x',
            ])->assertForbidden();

        $this->assertSame($this->epps->id, $eu->fresh()->client_id);
    }
}
