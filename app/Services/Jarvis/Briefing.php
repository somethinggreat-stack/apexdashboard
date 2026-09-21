<?php

namespace App\Services\Jarvis;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProspectLead;
use App\Models\Prospect;
use Illuminate\Support\Carbon;

/**
 * The morning briefing: `summary` (the numbers) and `alerts` (the things that
 * should be noticed without being asked).
 *
 * Every count here goes through the same model scopes the dashboard uses, so
 * JARVIS and the screen the team looks at can never quietly disagree.
 */
class Briefing
{
    public function __construct(
        private EndUserFeed $feed,
        private \App\Services\OwnerSnapshot $snapshot,
    ) {
    }

    /** One call, the whole picture. */
    public function summary(): array
    {
        // New / Incomplete / Overdue come from the Needs Attention panel itself, not
        // from a second definition here. They used to be recomputed with a narrower
        // client set and disagreed with the screen (112 overdue against 195, 4 new
        // against 1). The panel wins, always.
        $panel = $this->snapshot->attentionTotals();
        $balances = $this->snapshot->balanceTotals();
        $owners = $this->snapshot->balanceRows()->filter(fn ($r) => $r['outstanding'] > 0);

        return [
            'as_of'                 => now()->utc()->toIso8601String(),
            'new_clients'           => $panel['new'],
            'incomplete'            => $panel['incomplete'],
            'owners_requiring_action' => $panel['owners'],
            'new_client_errors'     => EndUser::query()->where('intake_status', 'error')->count(),
            'round_errors'          => EndUser::query()->roundError()->count(),
            'in_progress'           => EndUser::query()->inProgress()->notHeld()->count(),
            'done'                  => EndUser::query()->done()->count(),
            'on_hold'               => EndUser::query()->onHold()->count(),
            'rounds_due'            => [
                // overdue is the panel's; the other two have no panel equivalent and
                // are the API's own, off the model's per-owner round clock.
                'overdue'       => $panel['overdue'],
                'due_today'     => $this->roundsDueCounts()['due_today'],
                'due_in_3_days' => $this->roundsDueCounts()['due_in_3_days'],
            ],
            'no_movement_14d'       => $this->noMovementCount(14),
            'outstanding_invoices'  => [
                'count'           => $owners->count(),
                'total'           => $balances['outstanding'],
                'collected_total' => $balances['collected'],
                'owners_affected' => $owners->count(),
            ],
            'new_leads_7d'          => $this->newLeadsCount(7),
        ];
    }

    /**
     * Round timings, counted off the model's own clock so the per-owner cycle
     * (20 or 30 days) is respected rather than assuming 30 for everyone.
     */
    public function roundsDueCounts(): array
    {
        $out = ['overdue' => 0, 'due_today' => 0, 'due_in_3_days' => 0];

        foreach ($this->activeWorkingSet() as $e) {
            $left = $e->days_left_in_round;
            if ($left === null) {
                continue;   // round not marked yet — nothing is due
            }
            if ($left < 0) {
                $out['overdue']++;
            } elseif ($left === 0) {
                $out['due_today']++;
            } elseif ($left <= 3) {
                $out['due_in_3_days']++;
            }
        }

        return $out;
    }

    /** Clients at or past their round mark, most overdue first. */
    public function roundsDue(int $withinDays = 3)
    {
        return $this->activeWorkingSet()
            ->filter(fn (EndUser $e) => $e->days_left_in_round !== null && $e->days_left_in_round <= $withinDays)
            ->sortBy(fn (EndUser $e) => $e->days_left_in_round)
            ->values();
    }

    /**
     * The set the team is actually working: not held, not parked awaiting the
     * owner's approval, and not in an error bucket that has to be fixed first.
     */
    public function activeWorkingSet()
    {
        return $this->feed->base()
            ->where('status', 'active')
            ->clientsList()
            ->notHeld()
            ->notAwaitingApproval()
            ->get();
    }

    /** Clients with nothing logged for N days — the ones quietly going stale. */
    public function noMovementCount(int $days): int
    {
        $cutoff = now()->subDays($days);

        return $this->activeWorkingSet()
            ->filter(function (EndUser $e) use ($cutoff) {
                $last = $this->feed->lastStepAt($e);

                return $last === null || $last->lt($cutoff);
            })->count();
    }

    /**
     * Outstanding money, per business owner.
     *
     * NOTE: the `invoices` table records issued invoices but carries no status,
     * due date or currency — so "outstanding" cannot come from there. The real
     * figure the dashboard shows is `Client::paymentTotals()['pending']`: rounds a
     * client has actually reached, minus what has been paid. That is what this
     * uses, so JARVIS agrees with the Select Business Owner screen.
     */
    public function outstandingInvoices(): array
    {
        $rows = $this->outstandingByOwner();

        return [
            'count'           => $rows->count(),
            'total'           => (float) $rows->sum('amount'),
            'owners_affected' => $rows->count(),
            'rows'            => $rows,
        ];
    }

    /**
     * Per-owner balances — delegated straight to the Business Owner Balances panel.
     *
     * This used to select only the API's allow-listed columns and then call
     * paymentTotals() on the result. That method reads compensation_model and the
     * per-round fee fields, which weren't selected, so it returned 0 for nearly every
     * owner: $20 total against the panel's $5,414. Calling the right method with a
     * starved model is indistinguishable from calling the wrong one.
     */
    public function outstandingByOwner()
    {
        return $this->snapshot->balanceRows()
            ->filter(fn ($r) => $r['outstanding'] > 0)
            ->map(fn ($r) => [
                'business_owner_id'   => $r['business_owner_id'],
                'business_owner_name' => $r['business_owner_name'],
                'amount'              => $r['outstanding'],
                'collected_total'     => $r['collected'],
                'currency'            => 'USD',
                'compensation_model'  => $r['compensation_model'],
            ])
            ->sortByDesc('amount')
            ->values();
    }

    /** New leads across the three lead tables. */
    public function newLeadsCount(int $days): int
    {
        $since = now()->subDays($days);

        return Prospect::where('created_at', '>=', $since)->count()
            + ProspectLead::where('created_at', '>=', $since)->count()
            + \App\Models\BusinessLead::where('created_at', '>=', $since)->count();
    }

    /**
     * The alert feed. Ordered by severity so the first thing JARVIS reads out is
     * the thing that matters most.
     */
    public function alerts(): array
    {
        $alerts = [];

        foreach ($this->activeWorkingSet() as $e) {
            $left = $e->days_left_in_round;
            if ($left !== null && $left < 0) {
                $alerts[] = [
                    'type'              => 'round_overdue',
                    'severity'          => $left <= -7 ? 'high' : 'medium',
                    'end_user_id'       => $e->id,
                    'business_owner_id' => $e->client_id,
                    'label'             => sprintf(
                        '%s, %s, %d day%s overdue',
                        $this->feed->name($e),
                        $this->roundLabel($e) ?? 'no round marked',
                        abs($left),
                        abs($left) === 1 ? '' : 's'
                    ),
                    'since'             => $this->feed->iso($e->current_round_start_date),
                ];
            }
        }

        foreach ($this->feed->base()->roundError()->get() as $e) {
            $alerts[] = [
                'type'              => 'round_error',
                'severity'          => 'high',
                'end_user_id'       => $e->id,
                'business_owner_id' => $e->client_id,
                'label'             => $this->feed->name($e) . ' is in Round Errors'
                    . ($e->error_type ? ' (' . $e->error_type . ')' : ''),
                'since'             => $this->feed->iso($e->updated_at),
            ];
        }

        foreach ($this->feed->base()->newError()->get() as $e) {
            $alerts[] = [
                'type'              => 'new_client_error',
                'severity'          => 'medium',
                'end_user_id'       => $e->id,
                'business_owner_id' => $e->client_id,
                'label'             => $this->feed->name($e) . ' is a New Client Error'
                    . ($e->error_type ? ' (' . $e->error_type . ')' : ''),
                'since'             => $this->feed->iso($e->created_at),
            ];
        }

        $cutoff = now()->subDays(14);
        foreach ($this->activeWorkingSet() as $e) {
            $last = $this->feed->lastStepAt($e);
            if ($last === null || $last->lt($cutoff)) {
                $alerts[] = [
                    'type'              => 'no_movement',
                    'severity'          => 'low',
                    'end_user_id'       => $e->id,
                    'business_owner_id' => $e->client_id,
                    'label'             => $this->feed->name($e) . ' has had nothing logged for 14 days or more',
                    'since'             => $this->feed->iso($last),
                ];
            }
        }

        foreach ($this->outstandingByOwner() as $row) {
            $alerts[] = [
                'type'              => 'invoice_overdue',
                'severity'          => $row['amount'] >= 1000 ? 'medium' : 'low',
                'end_user_id'       => null,
                'business_owner_id' => $row['business_owner_id'],
                'label'             => sprintf('%s owes $%s', $row['business_owner_name'], number_format($row['amount'], 2)),
                'since'             => null,
            ];
        }

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($alerts, fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return $alerts;
    }

    private function roundLabel(EndUser $e): ?string
    {
        $n = $e->current_round;

        return $n > 0 ? (EndUser::ROUND_OPTIONS[$n - 1] ?? null) : null;
    }
}
