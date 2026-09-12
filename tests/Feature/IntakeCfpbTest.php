<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntakeCfpbTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $super->role = 'super';
        $super->save();

        return Client::create([
            'admin_id' => $super->id,
            'business_name' => 'Victoria', 'email' => 'v@test.com', 'password' => 'secret',
            'monthly_fee' => 0, 'status' => 'active', 'compensation_model' => 'per_round', 'per_round_fee' => 15,
            'intake_enabled' => true, 'intake_token' => 'tok'.str_repeat('a', 20),
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'first_name' => 'Jasmine', 'last_name' => 'Harris', 'suffix' => 'None',
            'email' => 'jas@t.com', 'ssn' => '123456789', 'date_of_birth' => '1990-01-01',
            'current_address' => '1 St', 'city' => 'Town', 'state' => 'LA', 'zipcode' => '70001',
            'phone' => '985-549-4702',
            'credit_monitoring_name' => 'IdentityIQ',
            'credit_monitoring_username' => 'jas@t.com', 'credit_monitoring_password' => 'pw',
            'drivers_license' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf'),
            'proof_of_address' => UploadedFile::fake()->create('poa.pdf', 20, 'application/pdf'),
        ], $extra);
    }

    public function test_cfpb_logins_are_saved_from_intake(): void
    {
        Storage::fake('private');
        $client = $this->client();

        $this->post("/intake/{$client->intake_token}", $this->payload([
            'cfpb_email'    => 'jasmine@cfpb.com',
            'cfpb_password' => 'cfpb-pass-123',
        ]))->assertRedirect(route('intake.success', ['token' => $client->intake_token]));

        $eu = EndUser::where('email', 'jas@t.com')->firstOrFail();
        $this->assertSame('jasmine@cfpb.com', $eu->cfpb_email);
        $this->assertSame('cfpb-pass-123', $eu->cfpb_password);   // decrypts via cast
    }

    public function test_cfpb_logins_are_optional(): void
    {
        Storage::fake('private');
        $client = $this->client();

        $this->post("/intake/{$client->intake_token}", $this->payload())
            ->assertRedirect(route('intake.success', ['token' => $client->intake_token]));

        $eu = EndUser::where('email', 'jas@t.com')->firstOrFail();
        $this->assertNull($eu->cfpb_email);
        $this->assertNull($eu->cfpb_password);
    }

    public function test_intake_form_shows_the_cfpb_section(): void
    {
        $client = $this->client();
        $this->get("/intake/{$client->intake_token}")
            ->assertOk()
            ->assertSee('CFPB Logins')
            ->assertSee('SelfRegister');
    }
}
