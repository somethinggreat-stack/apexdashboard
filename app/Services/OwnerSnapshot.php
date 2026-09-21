<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Client;
use App\Models\EndUser;

/**
 * The one implementation of the super-admin dashboard's two panels: **Needs
 * Attention** and **Business Owner Balances**.
 *
 * This exists because there were briefly two. The JARVIS API recomputed the same
 * figures with its own (reasonable-looking) definitions and disagreed with the
 * screens the team works from — $20 outstanding against the panel's $5,414, 112
 * overdue against 195. Every one of those gaps was a slightly different idea of
 * which clients count.
 *
 * So: the dashboard, the business-owner picker and the API all call this. A figure
 * with one implementation cannot drift from itself. If you change a rule here, it
 * changes everywhere at once — which is the point.
 *
 * Extracted verbatim from DashboardController::index(); the definitions below are
 * the panel's, not new ones.
 */
class OwnerSnapshot
{
    /** @var array<int, array>|null */
    private ?array $rows = null;

    private ?float $collected = null;

    private ?float $outstanding = null;

    public function __construct(private int $ownerAdminId)
    {
    }

    /**
     * The owner whose console this is. JARVIS has no session, so it has to resolve
     * the owner itself — and that must never be a guess.
     *
     * `admins.role` DEFAULTS to 'super' (see the roles migration), so "the first
     * admin with role super" can easily be a dormant seeded account with no business
     * owners attached. JARVIS would then answer every question with a confident zero
     * — the exact failure mode this whole class exists to prevent. Order:
     *
     *   1. JARVIS_OWNER_ADMIN_ID, if someone has stated it outright.
     *   2. The account the app itself calls the super admin (config team.super.email).
     *   3. The super admin who actually owns the most business owners, so a dormant
     *      account can never win by having a lower id.
     */
    public static function forSuperAdmin(): self
    {
        if ($id = (int) config('jarvis.owner_admin_id')) {
            $admin = Admin::find($id);
            if ($admin) {
                return new self($admin->dataOwnerId());
            }
        }

        $email = (string) config('team.super.email');
        if ($email !== '') {
            $admin = Admin::where('email', $email)->first();
            if ($admin && Client::where('admin_id', $admin->dataOwnerId())->exists()) {
                return new self($admin->dataOwnerId());
            }
        }

        $admin = Admin::where('role', 'super')
            ->withCount('clients')
            ->orderByDesc('clients_count')
            ->orderBy('id')
            ->first();

        return new self($admin ? $admin->dataOwnerId() : 0);
    }

    public static function forAdmin(Admin $admin): self
    {
        return new self($admin->dataOwnerId());
    }

    /**
     * Active owners only — an inactive business owner drops off the picker, its
     * balance and its Needs-Attention work. (The panel's rule, kept.)
     */
    public function clients()
    {
        return Client::forAdmin($this->ownerAdminId)
            ->active()
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();
    }

    /**
     * Per-owner counts, exactly as the Needs Attention panel computes them.
     *
     * Note what is NOT filtered: every client of an active owner is counted except
     * those awaiting intake review, whatever their status, hold or approval state.
     * Narrowing that set is what made the API disagree with the screen.
     *
     * @return array<int, array{client: Client, pending: int, incomplete: int, overdue: int, score: int, priority: string, priority_level: int}>
     */
    public function attentionRows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $clients = $this->clients();

        // One query for every client of every owner, with the four week-step counts
        // `is_incomplete` needs — not a 4-subquery SELECT per owner.
        $eusByClient = EndUser::whereIn('client_id', $clients->pluck('id'))
            ->with('processSteps:id,end_user_id,round,week,step_type,step_date')
            ->withCount([
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ])
            ->get()
            ->groupBy('client_id');

        $rows = [];
        $collected = 0.0;
        $outstanding = 0.0;

        foreach ($clients as $client) {
            $eus = $eusByClient->get($client->id, collect());

            $pending    = $eus->where('intake_status', 'pending_review')->count();
            $active     = $eus->filter(fn ($e) => $e->intake_status !== 'pending_review');
            $incomplete = $active->filter(fn ($e) => $e->is_incomplete)->count();
            $overdue    = $active->filter(fn ($e) => $e->days_left_in_round !== null && $e->days_left_in_round < 0)->count();

            if ($pending || $incomplete || $overdue) {
                [$label, $level] = self::priority($pending, $incomplete, $overdue);

                $rows[] = [
                    'client'         => $client,
                    'pending'        => $pending,
                    'incomplete'     => $incomplete,
                    'overdue'        => $overdue,
                    'score'          => $pending + $incomplete + $overdue,
                    'priority'       => $label,
                    'priority_level' => $level,
                ];
            }

            // paymentTotals() needs a FULL Client model — it reads the compensation
            // model and per-round fees. Handing it a partially selected model is how
            // the API produced $20 instead of $5,414.
            $totals = $client->paymentTotals();
            $collected   += $totals['done'];
            $outstanding += $totals['pending'];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        $this->collected = $collected;
        $this->outstanding = $outstanding;

        return $this->rows = $rows;
    }

    /**
     * The panel's priority banding. Lifted from the Needs Attention partial so the
     * label on the screen and the label in the API are produced by one function.
     *
     * @return array{0: string, 1: int} ['Critical', 4] … ['Low', 1]
     */
    public static function priority(int $pending, int $incomplete, int $overdue): array
    {
        $w = $overdue * 3 + $pending * 2 + $incomplete;

        return $w >= 45 ? ['Critical', 4] : ($w >= 20 ? ['High', 3] : ($w >= 8 ? ['Medium', 2] : ['Low', 1]));
    }

    /** The panel's headline counts: New / Incomplete / Overdue, and owners needing action. */
    public function attentionTotals(): array
    {
        $rows = $this->attentionRows();

        return [
            'new'        => array_sum(array_column($rows, 'pending')),
            'incomplete' => array_sum(array_column($rows, 'incomplete')),
            'overdue'    => array_sum(array_column($rows, 'overdue')),
            'owners'     => count($rows),
        ];
    }

    /** Total Outstanding / Total Collected, as the Balances panel shows them. */
    public function balanceTotals(): array
    {
        $this->attentionRows();   // fills both figures in one pass

        return [
            'outstanding' => round((float) $this->outstanding, 2),
            'collected'   => round((float) $this->collected, 2),
        ];
    }

    /**
     * Per-owner balances — the Business Owner Balances panel's rows.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function balanceRows()
    {
        return $this->clients()->map(function (Client $client) {
            $totals = $client->paymentTotals();

            return [
                'business_owner_id'   => $client->id,
                'business_owner_name' => $client->business_name,
                'outstanding'         => round((float) $totals['pending'], 2),
                'collected'           => round((float) $totals['done'], 2),
                'compensation_model'  => $client->compensation_model ?: 'per_round',
            ];
        })->values();
    }
}
