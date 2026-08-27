<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client search matches however you type the name — first, last, full name in
 * any order, or a partial — across every search bar (they all use scopeSearch).
 */
class ClientSearchTest extends TestCase
{
    use RefreshDatabase;

    private Client $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $super = new Admin(['email' => 's@t.com', 'password' => 'x', 'full_name' => 'S']);
        $super->role = 'super';
        $super->save();
        $this->bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'BO', 'email' => 'bo@t.com',
            'password' => 'x', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15, 'round_cycle_days' => 30,
            'intake_token' => \Illuminate\Support\Str::random(20),
        ]);
    }

    private function mk(string $first, string $last, ?string $middle = null): EndUser
    {
        return EndUser::create([
            'client_id' => $this->bo->id, 'first_name' => $first, 'middle_name' => $middle,
            'last_name' => $last, 'suffix' => 'None', 'email' => strtolower("$first.$last@t.com"),
            'current_address' => '1 St', 'city' => 'T', 'state' => 'ST', 'zipcode' => '12345',
            'status' => 'active', 'start_date' => '2026-06-01', 'intake_status' => null, 'rounds' => ['1st Round'],
        ]);
    }

    public function test_search_matches_however_the_name_is_typed(): void
    {
        $steve = $this->mk('Steve', 'Depasse');
        $this->mk('Mary', 'Watson');   // a decoy that must never match "Steve..."

        foreach (['Steve', 'Depasse', 'Steve Depasse', 'Depasse Steve', 'ste', 'depas', 'STEVE DEPASSE'] as $term) {
            $ids = EndUser::search($term)->pluck('id')->all();
            $this->assertContains($steve->id, $ids, "‘{$term}’ should find Steve Depasse");
            $this->assertCount(1, $ids, "‘{$term}’ should match only Steve Depasse");
        }
    }

    public function test_search_matches_middle_name_and_partial(): void
    {
        $mary = $this->mk('Mary', 'Watson', 'Jane');
        $this->assertContains($mary->id, EndUser::search('Jane Watson')->pluck('id')->all());
        $this->assertContains($mary->id, EndUser::search('Mary Jane')->pluck('id')->all());
    }

    public function test_search_composes_with_owner_scope(): void
    {
        $steve = $this->mk('Steve', 'Depasse');

        // The universal search scopes to the owner, then searches — full name must
        // still match through the whereHas + scope chain.
        $ids = EndUser::whereHas('client', fn ($c) => $c->where('admin_id', $this->bo->admin_id))
            ->search('Steve Depasse')->pluck('id')->all();

        $this->assertSame([$steve->id], $ids);
    }
}
