<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;
use App\Services\Jarvis\Columns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * JARVIS is a read-only window onto a database full of other people's identities.
 * The happy-path tests matter least here; the four rules do:
 *
 *   1. GET only — nothing in the group can write.
 *   2. business_owner_credentials is never referenced.
 *   3. No `select *` on end_users, and no PII on the wire.
 *   4. No token, no API at all.
 */
class JarvisApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-jarvis-token-do-not-use-in-production';

    private Client $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jarvis.token' => self::TOKEN]);

        $admin = new Admin(['email' => 'super@test.com', 'password' => 'secret-pass', 'full_name' => 'Umair Arshad']);
        $admin->role = 'super';
        $admin->save();

        $this->owner = Client::create([
            'admin_id' => $admin->id, 'business_name' => 'Clinecea Phillips',
            'email' => 'owner@test.com', 'password' => 'secret-pass',
            'status' => 'active', 'monthly_fee' => 0, 'round_cycle_days' => 30,
        ]);
    }

    /** A client carrying every dangerous field, so the PII tests have something to leak. */
    private function endUser(array $attr = []): EndUser
    {
        return EndUser::create(array_merge([
            'client_id'                  => $this->owner->id,
            'first_name'                 => 'Dominique',
            'last_name'                  => 'Johnson',
            'email'                      => 'dominique@example.com',
            'phone'                      => '555-0100',
            'date_of_birth'              => '1988-04-02',
            'ssn'                        => '123456789',
            'current_address'            => '42 Peachtree Street',
            'address_line2'              => 'Apt 9',
            'city'                       => 'Atlanta',
            'state'                      => 'GA',
            'zipcode'                    => '30301',
            'credit_monitoring_username' => 'dom.johnson',
            'credit_monitoring_password' => 'hunter2',
            'photo_id_path'              => 'private/ids/dominique.jpg',
            'status'                     => 'active',
            'start_date'                 => now()->subDays(40)->toDateString(),
            'rounds'                     => ['1st Round', '2nd Round'],
            'round_dates'                => ['1st Round' => now()->subDays(70)->toDateString(),
                                             '2nd Round' => now()->subDays(40)->toDateString()],
        ], $attr));
    }

    /** Named `ask` rather than `get` — TestCase::get() already exists and is public. */
    private function ask(string $path)
    {
        return $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)->getJson('/api/jarvis/' . $path);
    }

    // ---- rule 4: no token, no API -------------------------------------------------------

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/jarvis/health')->assertStatus(401);
    }

    public function test_a_request_with_the_wrong_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer not-the-token')
            ->getJson('/api/jarvis/health')->assertStatus(401);
    }

    public function test_a_token_in_the_query_string_is_not_accepted(): void
    {
        // URLs end up in access logs and browser history — the token goes in a header only.
        $this->getJson('/api/jarvis/health?token=' . self::TOKEN)->assertStatus(401);
    }

    public function test_the_route_file_registers_nothing_without_a_token(): void
    {
        // The file is the gate: with no token configured it returns before defining
        // a single route, so an un-configured deploy has nothing to probe.
        config(['jarvis.token' => null]);

        // Re-run the route file against the REAL router with no token. If the guard
        // ever broke, routes would be added here and the count would move.
        $before = count(Route::getRoutes()->getRoutes());
        require base_path('routes/api-jarvis.php');
        $after = count(Route::getRoutes()->getRoutes());

        $this->assertSame($before, $after, 'the route file registered routes despite no token being configured');
    }

    // ---- rule 1: read-only ---------------------------------------------------------------

    public function test_every_jarvis_read_route_is_read_only(): void
    {
        // Writes now exist, under api/jarvis/clients/* only. Everything else in the
        // group must still be GET|HEAD — this is what catches a read endpoint
        // quietly gaining a write method.
        $offenders = [];
        $reads = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/jarvis')) {
                continue;
            }
            if (str_starts_with($route->uri(), 'api/jarvis/clients/')) {
                continue;   // the write surface, asserted separately below
            }
            $reads++;
            $methods = array_values(array_diff($route->methods(), ['GET', 'HEAD']));
            if ($methods) {
                $offenders[$route->uri()] = $methods;
            }
        }

        $this->assertSame([], $offenders, 'JARVIS read routes must be GET only: ' . json_encode($offenders));
        $this->assertGreaterThan(0, $reads, 'no jarvis read routes registered — the assertion above would pass vacuously');
    }

    public function test_every_jarvis_write_route_is_a_post_to_a_single_record(): void
    {
        $writes = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/jarvis/clients/')) {
                continue;
            }
            $writes++;

            // POST only: never a GET a link could trigger, never a DELETE.
            $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])), $route->uri());
            // One record per call, so a loop can't hide as a single bulk request.
            $this->assertStringContainsString('{id}', $route->uri());
        }

        $this->assertGreaterThan(0, $writes, 'no jarvis write routes registered');
    }

    public function test_writing_to_a_jarvis_route_is_refused(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->postJson('/api/jarvis/end-users')->assertStatus(405);
    }

    // ---- rule 2: the credentials table is untouchable -------------------------------------

    public function test_no_jarvis_code_mentions_the_credentials_table(): void
    {
        $files = collect([
            base_path('routes/api-jarvis.php'),
            base_path('app/Http/Middleware/JarvisToken.php'),
            base_path('app/Http/Controllers/Api/JarvisController.php'),
        ])->concat(glob(base_path('app/Services/Jarvis/*.php')));

        foreach ($files as $file) {
            // Comments are allowed to name the table — explaining WHY it is off limits
            // is the point. Only executable code is checked, so the rule stays about
            // behaviour rather than about which words may appear in a docblock.
            $code = $this->stripComments(file_get_contents($file));

            // Columns.php names it once, in FORBIDDEN_TABLES: that entry is the guard.
            $allowed = str_ends_with($file, 'Columns.php') ? 1 : 0;

            $this->assertSame(
                $allowed,
                substr_count($code, 'business_owner_credentials'),
                basename($file) . ' must never reference business_owner_credentials in code'
            );
        }
    }

    /** PHP source with every comment removed, so the check above tests code, not prose. */
    private function stripComments(string $code): string
    {
        $out = '';
        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    public function test_the_forbidden_table_list_matches_a_real_table(): void
    {
        // If the table were ever renamed, the guard above would silently protect nothing.
        foreach (Columns::FORBIDDEN_TABLES as $table) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table), $table . ' no longer exists');
        }
    }

    // ---- rule 3: no PII on the wire -------------------------------------------------------

    public function test_the_client_detail_response_carries_no_personal_information(): void
    {
        $e = $this->endUser();

        $body = $this->ask('end-users/' . $e->id)->assertOk()->getContent();

        $this->assertNoPii($body);
        // What it SHOULD carry: the name, so JARVIS can say who it is talking about.
        $this->assertStringContainsString('Dominique', $body);
    }

    public function test_a_blank_middle_name_does_not_become_the_word_none(): void
    {
        // Intake writes the literal string "None" when the field is left blank; it
        // reached JARVIS as "Dominique None Johnson" on 104 clients.
        $e = $this->endUser(['middle_name' => 'None', 'suffix' => 'None']);

        $row = $this->ask('end-users/' . $e->id)->assertOk()->json('data');

        $this->assertSame('Dominique Johnson', $row['name']);
        // The stored value is left alone — this is a display fix, not a data edit.
        $this->assertSame('None', $e->fresh()->middle_name);
    }

    public function test_the_client_list_response_carries_no_personal_information(): void
    {
        $this->endUser();

        $this->assertNoPii($this->ask('end-users')->assertOk()->getContent());
    }

    /** The one place the PII rules are enforced, so every endpoint tightens together. */
    private function assertNoPii(string $body): void
    {
        $decoded = json_decode($body, true);
        $keys = [];
        array_walk_recursive($decoded, function ($v, $k) use (&$keys) { $keys[] = $k; });

        foreach (Columns::FORBIDDEN_RESPONSE_KEYS as $banned) {
            $this->assertNotContains($banned, $keys, "response exposes a '{$banned}' key");
        }

        // The values themselves, not just the key names.
        $this->assertStringNotContainsString('123456789', $body, 'an SSN reached the response');
        $this->assertStringNotContainsString('1988-04-02', $body, 'a date of birth reached the response');
        $this->assertStringNotContainsString('42 Peachtree', $body, 'a street address reached the response');
        $this->assertStringNotContainsString('hunter2', $body, 'a credit-monitoring password reached the response');
        $this->assertStringNotContainsString('dominique@example.com', $body, 'an email reached the response');
        $this->assertStringNotContainsString('private/ids/', $body, 'an ID document path reached the response');

        // No bare 9-digit run anywhere — the shape of an SSN, however it got there.
        $this->assertSame(0, preg_match('/(?<!\d)\d{9}(?!\d)/', $body), 'a 9-digit number reached the response');
    }

    public function test_the_end_user_allow_list_excludes_every_dangerous_column(): void
    {
        $dangerous = [
            'ssn', 'ssn_picture_path', 'date_of_birth', 'ghl_dob_raw',
            'current_address', 'address_line2', 'zipcode', 'phone', 'email',
            'credit_monitoring_username', 'credit_monitoring_password', 'credit_monitoring_pin',
            'credit_monitoring_security_question', 'credit_monitoring_security_answer',
            'cfpb_email', 'cfpb_password', 'cfpb_round_credentials',
            'photo_id_path', 'proof_of_address_path', 'collage_path', 'intake_submitted_ip',
        ];

        foreach ($dangerous as $column) {
            $this->assertNotContains($column, Columns::END_USER, "'{$column}' must never be in the allow-list");
        }

        // And every allowed column must actually exist, or the select would fail in production.
        foreach (Columns::END_USER as $column) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('end_users', $column),
                "allow-listed column '{$column}' does not exist on end_users"
            );
        }
    }

    public function test_the_business_owner_allow_list_excludes_secrets(): void
    {
        foreach (['password', 'intake_api_key', 'remember_token'] as $column) {
            $this->assertNotContains($column, Columns::BUSINESS_OWNER);
        }
        foreach (Columns::BUSINESS_OWNER as $column) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('clients', $column), $column);
        }
    }

    // ---- happy paths ----------------------------------------------------------------------

    public function test_health(): void
    {
        $this->ask('health')->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('db_ok', true)
            ->assertJsonStructure(['ok', 'app_version', 'db_ok', 'server_time', 'counts' => ['business_owners', 'end_users_active']]);
    }

    public function test_summary_counts_each_bucket(): void
    {
        $this->endUser(['intake_status' => 'pending_review', 'first_name' => 'New']);
        $this->endUser(['intake_status' => 'error', 'first_name' => 'Err']);
        $this->endUser(['intake_status' => 'round_error', 'first_name' => 'RoundErr']);
        $this->endUser(['intake_status' => 'done', 'first_name' => 'Done']);
        $this->endUser(['held_at' => now(), 'first_name' => 'Held']);

        $this->ask('summary')->assertOk()
            ->assertJsonPath('new_clients', 1)
            ->assertJsonPath('new_client_errors', 1)
            ->assertJsonPath('round_errors', 1)
            ->assertJsonPath('done', 1)
            ->assertJsonPath('on_hold', 1)
            ->assertJsonStructure(['as_of', 'rounds_due' => ['overdue', 'due_today', 'due_in_3_days'],
                'no_movement_14d', 'outstanding_invoices' => ['count', 'total', 'owners_affected'], 'new_leads_7d']);
    }

    public function test_alerts_flags_an_overdue_round(): void
    {
        // 2nd Round started 40 days ago on a 30-day cycle — 10 days overdue.
        $this->endUser();

        $alerts = $this->ask('alerts')->assertOk()->json('data');

        $overdue = collect($alerts)->firstWhere('type', 'round_overdue');
        $this->assertNotNull($overdue, 'an overdue round produced no alert');
        $this->assertStringContainsString('2nd Round', $overdue['label']);
        $this->assertStringContainsString('overdue', $overdue['label']);
    }

    public function test_end_users_returns_the_agreed_row_shape(): void
    {
        $this->endUser();

        $row = $this->ask('end-users')->assertOk()->json('data.0');

        $this->assertSame([
            'id', 'name', 'business_owner_id', 'business_owner_name', 'status', 'intake_status',
            'bucket_label', 'current_round', 'current_round_started_at', 'days_left_in_round',
            'rounds_completed', 'last_process_step_at', 'held_at', 'created_at',
        ], array_keys($row), 'the row shape drifted from the agreed contract');

        $this->assertSame('Clinecea Phillips', $row['business_owner_name']);
        $this->assertSame('2nd Round', $row['current_round']);
        $this->assertSame(1, $row['rounds_completed']);
    }

    public function test_end_users_filters_by_bucket_and_owner(): void
    {
        $this->endUser(['intake_status' => 'round_error', 'first_name' => 'Broken']);
        $this->endUser(['first_name' => 'Fine']);

        $rows = $this->ask('end-users?intake_status=round_error')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('Round Errors', $rows[0]['bucket_label']);

        $this->assertCount(2, $this->ask('end-users?business_owner_id=' . $this->owner->id)->json('data'));
        $this->assertCount(0, $this->ask('end-users?business_owner_id=999999')->json('data'));
    }

    public function test_list_pagination_is_capped(): void
    {
        $this->endUser();

        $meta = $this->ask('end-users?per_page=100000')->assertOk()->json('meta');
        $this->assertSame(config('jarvis.pagination.max'), $meta['per_page']);

        $this->assertSame(50, $this->ask('end-users')->json('meta.per_page'));
    }

    public function test_end_user_detail_includes_history_but_not_notes(): void
    {
        $e = $this->endUser();
        DB::table('notes')->insert([
            'end_user_id' => $e->id, 'note_text' => 'SENSITIVE-NOTE-TEXT',
            'created_by_admin_id' => Admin::first()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = $this->ask('end-users/' . $e->id)->assertOk()->json('data');

        $this->assertArrayHasKey('rounds', $data);
        $this->assertArrayHasKey('round_selections', $data);
        $this->assertArrayHasKey('process_steps', $data);
        $this->assertArrayHasKey('score_history', $data);
        $this->assertTrue($data['notes_omitted']);
        $this->assertArrayNotHasKey('notes', $data);
        $this->assertStringNotContainsString('SENSITIVE-NOTE-TEXT', $this->ask('end-users/' . $e->id)->getContent());
    }

    public function test_rounds_due_is_sorted_most_overdue_first(): void
    {
        $this->endUser(['first_name' => 'Slightly', 'round_dates' => ['1st Round' => now()->subDays(29)->toDateString()], 'rounds' => ['1st Round']]);
        $this->endUser(['first_name' => 'Badly', 'round_dates' => ['1st Round' => now()->subDays(60)->toDateString()], 'rounds' => ['1st Round']]);

        $rows = $this->ask('rounds/due?within_days=3')->assertOk()->json('data');

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame('Badly Johnson', $rows[0]['name'], 'the most overdue client must come first');
        $this->assertLessThanOrEqual($rows[1]['days_left_in_round'], $rows[0]['days_left_in_round']);
    }

    public function test_outstanding_invoices(): void
    {
        $this->endUser();

        $this->ask('invoices/outstanding')->assertOk()
            ->assertJsonStructure(['note', 'data', 'meta' => ['page', 'per_page', 'total']]);
    }

    public function test_business_owners(): void
    {
        $this->endUser();

        $row = $this->ask('business-owners')->assertOk()->json('data.0');

        $this->assertSame('Clinecea Phillips', $row['name']);
        $this->assertSame(1, $row['end_user_counts']['active']);
        $this->assertArrayHasKey('outstanding_invoice_total', $row);
        // The owner row must not leak the owner's own login either.
        $this->assertArrayNotHasKey('password', $row);
    }

    public function test_team_workload_carries_no_money(): void
    {
        $body = $this->ask('team/workload')->assertOk()->getContent();

        foreach (['rate', 'pay', 'payout', 'amount', 'salary'] as $word) {
            $this->assertStringNotContainsString($word, strtolower($body), "workload must not mention {$word}");
        }
    }

    public function test_recent_leads_carry_no_contact_details(): void
    {
        DB::table('prospects')->insert([
            'admin_id' => null, 'name' => 'A Lead', 'status' => 'new', 'channel' => 'whatsapp',
            'whatsapp' => '+15550001111', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $body = $this->ask('leads/recent?days=7')->assertOk();

        $body->assertJsonPath('data.0.name', 'A Lead')->assertJsonPath('data.0.channel', 'WhatsApp');
        $this->assertStringNotContainsString('15550001111', $body->getContent(), 'a phone number reached the response');
    }

    public function test_activity_returns_summaries_only(): void
    {
        DB::table('activity_logs')->insert([
            'admin_id' => null, 'action' => 'admin.users.store', 'description' => 'Added a user',
            'method' => 'POST', 'path' => '/admin/users', 'subject' => null, 'ip' => '127.0.0.1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->ask('activity?days=1')->assertOk()->assertJsonPath('data.0.summary', 'Added a user');
    }

    public function test_every_timestamp_is_iso_8601_utc(): void
    {
        $e = $this->endUser();

        $row = $this->ask('end-users')->json('data.0');

        foreach (['current_round_started_at', 'created_at'] as $field) {
            if ($row[$field] !== null) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
                    $row[$field],
                    "{$field} is not ISO 8601 UTC"
                );
            }
        }
    }
}
