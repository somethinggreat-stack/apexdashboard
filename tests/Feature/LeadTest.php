<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    private function bo(string $email): Client
    {
        return Client::create([
            'admin_id' => 1, 'business_name' => 'BO ' . $email, 'email' => $email,
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round',
        ]);
    }

    public function test_guest_cannot_access_leads(): void
    {
        $this->get('/business-owner/leads')->assertRedirect();
    }

    public function test_owner_can_add_a_lead_scoped_to_themselves(): void
    {
        $bo = $this->bo('a@test.com');

        $this->actingAs($bo, 'client')->post('/business-owner/leads', [
            'source' => 'Instagram', 'name' => 'Jane Doe', 'email' => 'jane@x.com',
            'phone' => '555', 'notes' => 'hot lead', 'status' => 'new',
        ])->assertRedirect(route('client.leads.index'));

        $this->assertSame(1, BusinessLead::where('client_id', $bo->id)->count());
    }

    public function test_all_fields_optional(): void
    {
        $bo = $this->bo('b@test.com');
        $this->actingAs($bo, 'client')->post('/business-owner/leads', [])->assertSessionHasNoErrors();
        $this->assertSame('new', BusinessLead::first()->status);
    }

    public function test_owner_cannot_view_or_edit_another_owners_lead(): void
    {
        $boA = $this->bo('a2@test.com');
        $boB = $this->bo('b2@test.com');
        $leadB = BusinessLead::create(['client_id' => $boB->id, 'name' => 'B lead', 'status' => 'new']);

        $this->actingAs($boA, 'client')->get("/business-owner/leads/{$leadB->id}")->assertNotFound();
        $this->actingAs($boA, 'client')
            ->put("/business-owner/leads/{$leadB->id}", ['_status_only' => 1, 'status' => 'lost'])
            ->assertNotFound();

        $this->assertSame('new', $leadB->fresh()->status, 'Cross-tenant status change must not happen');
    }

    public function test_status_can_be_changed_after_saving(): void
    {
        $bo = $this->bo('c@test.com');
        $lead = BusinessLead::create(['client_id' => $bo->id, 'name' => 'x', 'status' => 'new']);

        $this->actingAs($bo, 'client')
            ->put("/business-owner/leads/{$lead->id}", ['_status_only' => 1, 'status' => 'converted'])
            ->assertRedirect();

        $this->assertSame('converted', $lead->fresh()->status);
    }

    public function test_invalid_status_rejected(): void
    {
        $bo = $this->bo('d@test.com');
        $lead = BusinessLead::create(['client_id' => $bo->id, 'name' => 'x', 'status' => 'new']);

        $this->actingAs($bo, 'client')
            ->put("/business-owner/leads/{$lead->id}", ['_status_only' => 1, 'status' => 'hacked'])
            ->assertSessionHasErrors('status');
    }
}
