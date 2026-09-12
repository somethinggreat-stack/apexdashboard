<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntakeDomainTest extends TestCase
{
    use RefreshDatabase;

    private function super(): Admin
    {
        $a = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $a->role = 'super';
        $a->save();
        return $a;
    }

    private function client(Admin $super, array $attrs = []): Client
    {
        return Client::create(array_merge([
            'admin_id' => $super->id, 'business_name' => 'Victoria', 'email' => 'v@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'intake_token' => 'tok'.str_repeat('a', 20),
        ], $attrs));
    }

    public function test_owner_domain_is_used_when_set(): void
    {
        $c = $this->client($this->super(), ['intake_domain' => 'intake.victorialovecredit.com']);
        $this->assertSame(
            'https://intake.victorialovecredit.com/intake/' . $c->intake_token,
            $c->intakeUrl()
        );
    }

    public function test_falls_back_to_global_base_then_app_url(): void
    {
        $c = $this->client($this->super());

        config(['intake.public_base' => 'https://securecreditintake.neutral.workers.dev']);
        $this->assertSame(
            'https://securecreditintake.neutral.workers.dev/intake/' . $c->intake_token,
            $c->intakeUrl()
        );

        config(['intake.public_base' => '']);
        $this->assertSame(url('/intake/' . $c->intake_token), $c->intakeUrl());
    }

    public function test_update_normalizes_a_pasted_url_to_a_bare_host(): void
    {
        $super = $this->super();
        $c = $this->client($super);

        $this->actingAs($super, 'admin')->put("/admin/clients/{$c->id}", [
            'business_name'    => 'Victoria',
            'email'            => 'v@test.com',
            'status'           => 'active',
            'round_cycle_days' => 30,
            'intake_domain'    => 'https://intake.victorialovecredit.com/',
        ])->assertRedirect();

        $this->assertSame('intake.victorialovecredit.com', $c->fresh()->intake_domain);
    }

    public function test_new_owner_gets_a_readable_name_slug_token(): void
    {
        $super = $this->super();
        $c = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Clear Rise CO', 'email' => 'cr@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
        $this->assertMatchesRegularExpression('/^clear-rise-co-[a-z0-9]{2}$/', $c->intake_token);
    }

    public function test_regenerate_produces_a_fresh_name_slug(): void
    {
        $super = $this->super();
        $c = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Benny', 'email' => 'benny@test.com',
            'password' => 'secret', 'monthly_fee' => 0, 'status' => 'active',
            'compensation_model' => 'per_round', 'per_round_fee' => 15,
        ]);
        $old = $c->intake_token;

        $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $c->id])
            ->post('/admin/new-clients/regenerate-link')->assertRedirect();

        $c->refresh();
        $this->assertStringStartsWith('benny-', $c->intake_token);
        $this->assertNotSame($old, $c->intake_token);
    }

    public function test_export_intake_links_csv(): void
    {
        $super = $this->super();
        $this->client($super, ['business_name' => 'Victoria', 'intake_domain' => 'intake.victorialovecredit.com']);

        $resp = $this->actingAs($super, 'admin')->get('/admin/select-business-owner/intake-links');
        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('Business Owner', $body);
        $this->assertStringContainsString('Victoria', $body);
        $this->assertStringContainsString('https://intake.victorialovecredit.com/intake/', $body);
    }

    public function test_blank_domain_clears_it(): void
    {
        $super = $this->super();
        $c = $this->client($super, ['intake_domain' => 'intake.victorialovecredit.com']);

        $this->actingAs($super, 'admin')->put("/admin/clients/{$c->id}", [
            'business_name'    => 'Victoria',
            'email'            => 'v@test.com',
            'status'           => 'active',
            'round_cycle_days' => 30,
            'intake_domain'    => '',
        ])->assertRedirect();

        $this->assertNull($c->fresh()->intake_domain);
    }
}
