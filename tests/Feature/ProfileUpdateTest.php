<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorld(): array
    {
        $super = new Admin(['email' => 'super@test.com', 'password' => 'secret', 'full_name' => 'Super']);
        $super->role = 'super';
        $super->save();

        $va = new Admin(['email' => 'va@test.com', 'password' => 'secret', 'full_name' => 'Mujeeb']);
        $va->role = 'va';
        $va->parent_admin_id = $super->id;
        $va->save();

        $bo = Client::create([
            'admin_id' => $super->id, 'business_name' => 'Test BO', 'email' => 'bo@test.com',
            'password' => 'secret', 'monthly_fee' => 149, 'status' => 'active',
            'compensation_model' => 'per_round',
        ]);

        $eu = EndUser::create([
            'client_id' => $bo->id, 'first_name' => 'John', 'last_name' => 'Doe', 'suffix' => 'None',
            'email' => 'john@test.com', 'current_address' => '1 St', 'city' => 'Town',
            'state' => 'ST', 'zipcode' => '12345', 'status' => 'active', 'start_date' => '2026-01-01',
        ]);

        return [$super, $va, $bo, $eu];
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'John', 'last_name' => 'Doe', 'suffix' => 'None', 'email' => 'john@test.com',
            'current_address' => '1 St', 'city' => 'Town', 'state' => 'ST', 'zipcode' => '12345',
            'start_date' => '2026-01-01', 'status' => 'active',
        ], $override);
    }

    public function test_va_updates_cfpb_with_selected_owner(): void
    {
        [, $va, $bo, $eu] = $this->makeWorld();

        $resp = $this->actingAs($va, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'cfpb_email' => 'cfpb@test.com', 'cfpb_password' => 'newsecret',
            ]));

        fwrite(STDERR, "\n[with-session] status=" . $resp->status() . " redirect=" . ($resp->headers->get('Location') ?? 'none') . "\n");

        $resp->assertSessionHas('confirm', 'Client updated');
        $eu->refresh();
        $this->assertSame('cfpb@test.com', $eu->cfpb_email, 'CFPB email should be saved');
        $this->assertNotEmpty($eu->cfpb_password, 'CFPB password should be saved');
    }

    public function test_va_update_self_heals_when_owner_missing_from_session(): void
    {
        [, $va, , $eu] = $this->makeWorld();

        // No selected_client_id in session at all — the reported failure case.
        $resp = $this->actingAs($va, 'admin')
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'cfpb_email' => 'heal@test.com',
            ]));

        fwrite(STDERR, "[no-session] status=" . $resp->status() . " redirect=" . ($resp->headers->get('Location') ?? 'none') . "\n");

        // The exact reported bug: it must NOT bounce to the business-owner picker.
        $resp->assertRedirect(route('admin.end-users.show', $eu->id));
        $this->assertStringNotContainsString('select-business-owner', $resp->headers->get('Location') ?? '');

        $eu->refresh();
        $this->assertSame('heal@test.com', $eu->cfpb_email, 'Self-heal should let the save go through');
    }

    public function test_done_client_cfpb_save_does_not_bounce_to_picker(): void
    {
        // Mirrors the video exactly: a COMPLETED (done) client, VA edits CFPB,
        // and the multipart save arrives with no session (host stripped it).
        [, $va, , $eu] = $this->makeWorld();
        $eu->update(['intake_status' => 'done', 'rounds' => ['1st Round', '2nd Round']]);

        $resp = $this->actingAs($va, 'admin')
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'cfpb_email'    => 'done.client@cfpb.com',
                'cfpb_password' => 'donepass123',
            ]));

        fwrite(STDERR, "[done-client] status=" . $resp->status() . " redirect=" . ($resp->headers->get('Location') ?? 'none') . "\n");

        $resp->assertRedirect(route('admin.end-users.show', $eu->id));
        $eu->refresh();
        $this->assertSame('done.client@cfpb.com', $eu->cfpb_email, 'Done-client CFPB email must save');
        $this->assertNotEmpty($eu->cfpb_password, 'Done-client CFPB password must save');
        $this->assertSame('done', $eu->intake_status, 'Status must stay done');
    }

    public function test_cfpb_saves_when_address_fields_are_blank(): void
    {
        // The exact production repro: a client (Heather Pearson) with an empty
        // address. The form posts empty boxes, ConvertEmptyStringsToNull turns
        // them into null, and WITHOUT nullable the old rules failed
        // "current address must be a string" — silently bouncing to the picker.
        [, $va, $bo, $eu] = $this->makeWorld();

        $resp = $this->actingAs($va, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'current_address' => '', 'city' => '', 'state' => '', 'zipcode' => '',
                'cfpb_email'      => 'blankaddr@cfpb.com', 'cfpb_password' => 'pw12345',
            ]));

        fwrite(STDERR, "[blank-address] status=" . $resp->status() . " redirect=" . ($resp->headers->get('Location') ?? 'none') . "\n");

        $resp->assertRedirect(route('admin.end-users.show', $eu->id));
        $resp->assertSessionHasNoErrors();
        $eu->refresh();
        $this->assertSame('blankaddr@cfpb.com', $eu->cfpb_email, 'CFPB must save even with a blank address');
    }

    public function test_super_admin_updates_profile(): void
    {
        [$super, , $bo, $eu] = $this->makeWorld();

        $resp = $this->actingAs($super, 'admin')
            ->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'phone' => '555-1234', 'cfpb_email' => 'super@cfpb.com',
            ]));

        fwrite(STDERR, "[super] status=" . $resp->status() . " redirect=" . ($resp->headers->get('Location') ?? 'none') . "\n");

        $eu->refresh();
        $this->assertSame('555-1234', $eu->phone);
        $this->assertSame('super@cfpb.com', $eu->cfpb_email);
    }

    public function test_reached_round_numbers_tracks_rounds_array(): void
    {
        [, , , $eu] = $this->makeWorld();

        $eu->update(['rounds' => ['1st Round', '2nd Round', '3rd Round']]);
        $this->assertSame([1, 2, 3], $eu->reachedRoundNumbers());

        $eu->update(['rounds' => []]);
        $this->assertSame([1], $eu->reachedRoundNumbers(), 'always at least round 1');
    }

    public function test_per_round_cfpb_saves_and_blank_password_keeps_existing(): void
    {
        [, $va, $bo, $eu] = $this->makeWorld();
        $eu->update(['rounds' => ['1st Round', '2nd Round']]);

        // Save round 1 + 2 credentials.
        $this->actingAs($va, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'cfpb_rounds' => [
                    1 => ['email' => 'r1@cfpb.com', 'password' => 'pw1'],
                    2 => ['email' => 'r2@cfpb.com', 'password' => 'pw2'],
                ],
            ]))->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertSame('r1@cfpb.com', $eu->cfpbForRound(1)['email']);
        $this->assertSame('pw1', $eu->cfpbForRound(1)['password']);
        $this->assertSame('pw2', $eu->cfpbForRound(2)['password']);

        // Change round 1 email, leave its password blank -> keeps pw1.
        $this->actingAs($va, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'cfpb_rounds' => [
                    1 => ['email' => 'r1new@cfpb.com', 'password' => ''],
                    2 => ['email' => 'r2@cfpb.com', 'password' => ''],
                ],
            ]))->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertSame('r1new@cfpb.com', $eu->cfpbForRound(1)['email']);
        $this->assertSame('pw1', $eu->cfpbForRound(1)['password'], 'blank password keeps the existing one');
        $this->assertSame('pw2', $eu->cfpbForRound(2)['password']);
    }

    public function test_round_schedule_editor_sets_rounds_and_per_round_dates(): void
    {
        [, $va, $bo, $eu] = $this->makeWorld();

        $this->actingAs($va, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'round_schedule_present' => '1',
                'rounds' => ['1st Round', '2nd Round', '3rd Round'],
                'round_start_dates' => [
                    '1st Round' => '2026-06-17',
                    '2nd Round' => '2026-07-04',
                    '3rd Round' => '2026-08-06',
                ],
                'next_round_override' => '2026-09-06',
            ]))->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertSame(['1st Round', '2nd Round', '3rd Round'], $eu->rounds);
        // Every round's marked date now lives in round_dates — the 1st round
        // included (it is NOT the client's added date).
        $this->assertSame('2026-06-17', $eu->round_dates['1st Round']);
        $this->assertSame('2026-06-17', $eu->roundStartDate(1));
        $this->assertSame('2026-01-01', $eu->start_date->toDateString(), 'added date is untouched');
        // Later rounds live in round_dates.
        $this->assertSame('2026-07-04', $eu->round_dates['2nd Round']);
        $this->assertSame('2026-08-06', $eu->round_dates['3rd Round']);
        // Manual next-round override wins.
        $this->assertSame('2026-09-06', \Carbon\Carbon::parse($eu->next_round_date)->toDateString());
    }

    public function test_round_schedule_blank_next_round_reverts_to_auto(): void
    {
        [, $va, $bo, $eu] = $this->makeWorld();

        $this->actingAs($va, 'admin')->withSession(['selected_client_id' => $bo->id])
            ->put("/admin/end-users/{$eu->id}", $this->payload([
                'round_schedule_present' => '1',
                'rounds' => ['1st Round'],
                'round_start_dates' => ['1st Round' => '2026-06-01'],
                'next_round_override' => '',
            ]))->assertSessionHasNoErrors();

        $eu->refresh();
        $this->assertNull($eu->next_round_override, 'blank clears the override');
        // Auto = one month after the 1st-round start.
        $this->assertSame('2026-07-01', \Carbon\Carbon::parse($eu->next_round_date)->toDateString());
    }
}
