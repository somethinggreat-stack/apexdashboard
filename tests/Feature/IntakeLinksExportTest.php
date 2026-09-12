<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntakeLinksExportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $this->super->role = 'super';
        $this->super->save();

        Client::create([
            'admin_id' => $this->super->id, 'business_name' => 'Chantal', 'email' => 'chantal@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
    }

    private function va(): Admin
    {
        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'VA Sam']);
        $va->role = 'va';
        $va->parent_admin_id = $this->super->id;
        $va->save();

        return $va;
    }

    public function test_super_sees_the_export_button_and_can_download(): void
    {
        $this->actingAs($this->super, 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertSee('Export intake links');

        $this->actingAs($this->super, 'admin')->get('/admin/select-business-owner/intake-links')->assertOk();
    }

    public function test_va_never_sees_the_button_and_cannot_reach_the_export(): void
    {
        $va = $this->va();

        $this->actingAs($va, 'admin')->get('/admin/select-business-owner')
            ->assertOk()->assertDontSee('Export intake links');

        $this->actingAs($va, 'admin')->get('/admin/select-business-owner/intake-links')->assertForbidden();
    }
}
