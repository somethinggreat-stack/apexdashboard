<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use App\Services\Jarvis\Briefing;
use App\Services\Jarvis\Columns;
use App\Services\Jarvis\EndUserFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * JARVIS — read-only. Every method here is a GET and nothing in this class
 * writes, updates or deletes. A test asserts that for the whole route group.
 */
class JarvisController extends Controller
{
    public function __construct(private EndUserFeed $feed, private Briefing $briefing)
    {
    }

    /** Is the API up, is the database reachable, and roughly how much is in it. */
    public function health()
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
            DB::table('clients')->count();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return response()->json([
            'ok'          => true,
            'app_version' => (string) config('app.version', 'unknown'),
            'db_ok'       => $dbOk,
            'server_time' => now()->utc()->toIso8601String(),
            'counts'      => [
                'business_owners'  => $dbOk ? Client::query()->count() : null,
                'end_users_active' => $dbOk ? EndUser::query()->where('status', 'active')->count() : null,
            ],
        ]);
    }

    /** The morning briefing in one call. */
    public function summary()
    {
        return response()->json($this->briefing->summary());
    }

    /** What should be noticed without being asked, worst first. */
    public function alerts()
    {
        return response()->json(['data' => $this->briefing->alerts()]);
    }

    /** The client list, filtered. */
    public function endUsers(Request $request)
    {
        [$page, $perPage] = $this->paging($request);

        $rows = $this->feed->filtered($request)->orderBy('id')->get();
        $rows = $this->feed->applyComputedFilters($rows, $request);

        return $this->paginated($rows->map(fn (EndUser $e) => $this->feed->row($e)), $page, $perPage);
    }

    /** One client, with their history. Never their identity documents. */
    public function endUser(int $id)
    {
        $e = $this->feed->base()->findOrFail($id);

        $rounds = collect($e->rounds ?? [])->map(fn ($label) => [
            'round'      => $label,
            'started_at' => $this->feed->iso(($e->round_dates ?? [])[$label] ?? null),
        ])->values();

        $selections = DB::table('round_selections as rs')
            ->leftJoin('admins as a', 'a.id', '=', 'rs.admin_id')
            ->where('rs.end_user_id', $e->id)
            ->orderBy('rs.id')
            ->get(['rs.round', 'rs.admin_id', 'a.full_name', 'rs.created_at'])
            ->map(fn ($r) => [
                'round'               => $r->round,
                'advanced_by_user_id' => $r->admin_id,
                'advanced_by_name'    => $r->full_name,
                'at'                  => $this->feed->iso($r->created_at),
            ]);

        $steps = $e->processSteps()->get()->map(fn ($s) => [
            'round'              => $s->round,
            'week'               => $s->week,
            'step_type'          => $s->step_type,
            'accounts_disputed'  => [
                'experian'   => $s->experian_accounts_disputed,
                'transunion' => $s->transunion_accounts_disputed,
                'equifax'    => $s->equifax_accounts_disputed,
            ],
            'inquiries_disputed' => [
                'experian'   => $s->experian_inquiries_disputed,
                'transunion' => $s->transunion_inquiries_disputed,
                'equifax'    => $s->equifax_inquiries_disputed,
            ],
            'score_before'       => $s->previous_credit_score,
            'score_after'        => $s->credit_score_now,
            'deletions'          => $s->total_deletions,
            'items_added'        => $s->items_added,
            'created_at'         => $this->feed->iso($s->created_at),
        ])->values();

        $scores = DB::table('score_history')->where('end_user_id', $e->id)
            ->orderBy('recorded_at')
            ->get(['bureau', 'score', 'recorded_at'])
            ->map(fn ($r) => [
                'bureau'      => $r->bureau,
                'score'       => $r->score,
                'recorded_at' => $this->feed->iso($r->recorded_at),
            ]);

        return response()->json([
            'data' => $this->feed->row($e) + [
                'rounds'           => $rounds,
                'round_selections' => $selections,
                'process_steps'    => $steps,
                'score_history'    => $scores,
                // Notes are deliberately absent — see config/jarvis.php. VAs paste
                // report details and client specifics into them, so they cannot be
                // treated as PII-free.
                'notes_omitted'    => ! config('jarvis.expose_notes'),
                'sync'             => [
                    'gohighlevel_id' => $e->ghl_contact_id,
                    'disputefox_id'  => $e->disputefox_pushed_at ? (string) $e->id : null,
                ],
            ],
        ]);
    }

    /** Everything at or past its round mark, most overdue first. */
    public function roundsDue(Request $request)
    {
        [$page, $perPage] = $this->paging($request);
        $within = (int) $request->query('within_days', 3);

        $rows = $this->briefing->roundsDue($within)->map(fn (EndUser $e) => $this->feed->row($e));

        return $this->paginated($rows, $page, $perPage);
    }

    /**
     * Outstanding money by business owner.
     *
     * The `invoices` table has no status, due date or currency, so this reports
     * the balance the dashboard reports: rounds reached minus rounds paid.
     */
    public function outstandingInvoices(Request $request)
    {
        [$page, $perPage] = $this->paging($request);

        $rows = $this->briefing->outstandingByOwner();

        if ($id = $request->query('business_owner_id')) {
            $rows = $rows->where('business_owner_id', (int) $id)->values();
        }

        return $this->paginated($rows, $page, $perPage, [
            'note' => 'Derived from rounds reached minus payments recorded; the invoices table carries no status or due date.',
        ]);
    }

    /** The credit-repair companies, not consumers. */
    public function businessOwners(Request $request)
    {
        [$page, $perPage] = $this->paging($request);

        $q = Client::query()->select(Columns::BUSINESS_OWNER);

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($term = $request->query('q')) {
            $q->where('business_name', 'like', '%' . $term . '%');
        }

        $rows = $q->orderBy('business_name')->get()->map(function (Client $c) {
            $counts = EndUser::query()->where('client_id', $c->id)
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')
                ->pluck('n', 'status');

            return [
                'id'                        => $c->id,
                'name'                      => $c->business_name,
                'status'                    => $c->status,
                'round_cycle_days'          => $c->roundCycleDays(),
                'created_at'                => $this->feed->iso($c->created_at),
                'end_user_counts'           => [
                    'active'     => (int) ($counts['active'] ?? 0),
                    'paused'     => (int) ($counts['paused'] ?? 0),
                    'graduated'  => (int) ($counts['graduated'] ?? 0),
                    'cancelled'  => (int) ($counts['cancelled'] ?? 0),
                ],
                'outstanding_invoice_total' => round((float) ($c->paymentTotals()['pending'] ?? 0), 2),
                'last_activity_at'          => $this->feed->iso(
                    DB::table('process_steps as ps')
                        ->join('end_users as e', 'e.id', '=', 'ps.end_user_id')
                        ->where('e.client_id', $c->id)->max('ps.created_at')
                ),
            ];
        });

        return $this->paginated($rows, $page, $perPage);
    }

    /** Who did what lately. No pay rates, no payout amounts. */
    public function teamWorkload(Request $request)
    {
        $days = max(1, (int) $request->query('days', 7));
        $since = now()->subDays($days);

        $rounds = DB::table('round_selections')->where('created_at', '>=', $since)
            ->selectRaw('admin_id, COUNT(*) n, MAX(created_at) last')->groupBy('admin_id')->get()->keyBy('admin_id');
        $steps = DB::table('process_steps')->where('created_at', '>=', $since)
            ->selectRaw('created_by_admin_id, COUNT(*) n, MAX(created_at) last')->groupBy('created_by_admin_id')->get()->keyBy('created_by_admin_id');
        $hours = DB::table('time_entries')->where('work_date', '>=', $since->toDateString())
            ->selectRaw('created_by_admin_id, SUM(hours) h, MAX(created_at) last')->groupBy('created_by_admin_id')->get()->keyBy('created_by_admin_id');

        $ids = collect([$rounds->keys(), $steps->keys(), $hours->keys()])->flatten()->filter()->unique();
        $people = DB::table('admins')->whereIn('id', $ids)->pluck('full_name', 'id');

        $rows = $ids->map(function ($id) use ($rounds, $steps, $hours, $people) {
            $last = collect([
                $rounds[$id]->last ?? null, $steps[$id]->last ?? null, $hours[$id]->last ?? null,
            ])->filter()->max();

            return [
                'user_id'              => (int) $id,
                'name'                 => $people[$id] ?? 'Unknown',
                'rounds_advanced'      => (int) ($rounds[$id]->n ?? 0),
                'process_steps_logged' => (int) ($steps[$id]->n ?? 0),
                'hours_logged'         => round((float) ($hours[$id]->h ?? 0), 2),
                'last_active_at'       => $this->feed->iso($last),
            ];
        })->sortByDesc('process_steps_logged')->values();

        return response()->json(['data' => $rows]);
    }

    /** Recent leads, without a single phone number or email address. */
    public function recentLeads(Request $request)
    {
        $days = max(1, (int) $request->query('days', 7));
        $since = now()->subDays($days);

        $prospects = DB::table('prospects')->where('created_at', '>=', $since)
            ->get(['id', 'name', 'channel', 'status', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id, 'source_table' => 'prospects', 'name' => $r->name,
                'channel' => $this->channel($r->channel), 'created_at' => $this->feed->iso($r->created_at),
                'converted' => $r->status === 'interested',
            ]);

        $leads = DB::table('prospect_leads')->where('created_at', '>=', $since)
            ->get(['id', 'name', 'channel', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id, 'source_table' => 'prospect_leads', 'name' => $r->name,
                'channel' => $this->channel($r->channel), 'created_at' => $this->feed->iso($r->created_at),
                'converted' => false,
            ]);

        $business = DB::table('business_leads')->where('created_at', '>=', $since)
            ->get(['id', 'name', 'source', 'status', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id, 'source_table' => 'business_leads', 'name' => $r->name,
                'channel' => $this->channel($r->source), 'created_at' => $this->feed->iso($r->created_at),
                'converted' => $r->status === 'converted',
            ]);

        $rows = $prospects->concat($leads)->concat($business)
            ->sortByDesc('created_at')->values();

        return response()->json(['data' => $rows]);
    }

    /** Recent activity-log entries — the summary line only, never the payload. */
    public function activity(Request $request)
    {
        [$page, $perPage] = $this->paging($request);
        $days = max(1, (int) $request->query('days', 1));

        $rows = DB::table('activity_logs as l')
            ->leftJoin('admins as a', 'a.id', '=', 'l.admin_id')
            ->where('l.created_at', '>=', now()->subDays($days))
            ->orderByDesc('l.id')
            ->get(['l.created_at', 'a.full_name', 'l.action', 'l.subject', 'l.description'])
            ->map(fn ($r) => [
                'at'           => $this->feed->iso($r->created_at),
                'user_name'    => $r->full_name,
                'action'       => $r->action,
                'subject_type' => $r->subject ? 'client' : null,
                'subject_id'   => null,
                'summary'      => $r->description,
            ]);

        return $this->paginated($rows, $page, $perPage);
    }

    /** WhatsApp | phone | Instagram | website form, normalised from three tables. */
    private function channel(?string $raw): string
    {
        $v = strtolower((string) $raw);

        return match (true) {
            str_contains($v, 'whatsapp')                          => 'WhatsApp',
            str_contains($v, 'insta')                             => 'Instagram',
            str_contains($v, 'phone') || str_contains($v, 'call') => 'phone',
            $v === '' || str_contains($v, 'web') || str_contains($v, 'form') || str_contains($v, 'site') => 'website form',
            default                                               => 'website form',
        };
    }

    /** page + per_page, clamped. Never lets a caller ask for the whole table. */
    private function paging(Request $request): array
    {
        $max = (int) config('jarvis.pagination.max', 200);
        $perPage = (int) $request->query('per_page', (int) config('jarvis.pagination.default', 50));

        return [max(1, (int) $request->query('page', 1)), max(1, min($perPage, $max))];
    }

    private function paginated($rows, int $page, int $perPage, array $extra = [])
    {
        $rows = collect($rows)->values();

        return response()->json($extra + [
            'data' => $rows->forPage($page, $perPage)->values(),
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $rows->count(),
            ],
        ]);
    }
}
