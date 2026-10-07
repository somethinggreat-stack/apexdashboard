<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When GoHighLevel refuses the submissions fetch, Sync now must say why
 * instead of answering a bare 500 "Server Error".
 */
class GhlSyncErrorTest extends TestCase
{
    use RefreshDatabase;

    private function syncAs(): \Illuminate\Testing\TestResponse
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair']);
        $super->role = 'super';
        $super->save();

        $owner = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Benny', 'email' => 'b@test.com',
            'password' => 'secret-pass', 'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
        ]);

        config(['services.ghl' => [
            'api_key' => 'k', 'location_id' => 'loc', 'credit_repair_survey_id' => 'srv', 'client_id' => $owner->id,
        ]]);

        return $this->actingAs($super, 'admin')->withSession(['selected_client_id' => $owner->id])
            ->postJson('/admin/ghl-clients/sync');
    }

    public function test_a_rejected_token_is_reported_not_crashed(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid JWT'], 401)]);

        $this->syncAs()->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, '401')
                && str_contains($m, 'Invalid JWT') && str_contains($m, 'GHL_API_KEY'))->etc());
    }

    public function test_an_unreachable_ghl_is_reported_not_crashed(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->syncAs()->assertStatus(502)->assertJsonPath('ok', false);
    }
}
