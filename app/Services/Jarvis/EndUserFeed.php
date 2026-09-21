<?php

namespace App\Services\Jarvis;

use App\Models\EndUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Client rows for JARVIS — the list, the filters and the one row shape they share.
 *
 * Two things are load-bearing here:
 *
 *  1. **The column allow-list.** `select(Columns::END_USER)` is the only way rows
 *     are fetched. Nothing runs `select *` on end_users.
 *  2. **Reused model logic, not a copy of it.** `days_left_in_round`, `current_round`
 *     and the bucket scopes already exist and already drive the dashboard the team
 *     looks at. Reimplementing them here would let JARVIS and the dashboard
 *     disagree about whether a round is overdue, which is worse than either
 *     answer alone. In particular the round clock is **per business owner**
 *     (20 or 30 days, `clients.round_cycle_days`), not a flat 30.
 */
class EndUserFeed
{
    /** Bucket names as the team says them, so JARVIS can speak the team's language. */
    public const BUCKETS = [
        'pending_review' => 'New Clients',
        'error'          => 'New Client Errors',
        'round_error'    => 'Round Errors',
        'done'           => 'Clients Done',
    ];

    /** The base query: allow-listed columns, no soft-deleted rows, owner joined for the round clock. */
    public function base(): Builder
    {
        return EndUser::query()
            ->select(Columns::END_USER)
            // One aggregate instead of a query per row: a 200-row page would
            // otherwise fire 200 extra queries just to find the last step.
            ->withMax('processSteps as last_step_at', 'created_at')
            ->with(['client' => fn ($q) => $q->select(['id', 'business_name', 'round_cycle_days'])]);
    }

    /** Apply the documented filters. Anything unrecognised is ignored, never guessed at. */
    public function filtered(Request $request): Builder
    {
        $q = $this->base();

        if ($id = $request->query('business_owner_id')) {
            $q->where('client_id', (int) $id);
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ['active', 'paused', 'graduated', 'cancelled'], true)) {
                $q->where('status', $status);
            }
        }

        // `intake_status=null` (the string) means "in progress" — the bucket with no status.
        if ($request->has('intake_status')) {
            $intake = (string) $request->query('intake_status');
            if ($intake === 'null' || $intake === '') {
                $q->inProgress();
            } elseif (array_key_exists($intake, self::BUCKETS)) {
                $q->where('intake_status', $intake);
            }
        }

        if ($request->has('held')) {
            $request->boolean('held') ? $q->onHold() : $q->notHeld();
        }

        if ($term = $request->query('q')) {
            $q->search((string) $term);
        }

        return $q;
    }

    /**
     * Filters that can only be judged per row, because they read computed model
     * attributes rather than columns. Applied after fetching, which is safe at
     * this scale and honest about the cost.
     */
    public function applyComputedFilters($rows, Request $request)
    {
        if ($request->boolean('overdue')) {
            $rows = $rows->filter(fn (EndUser $e) => $e->days_left_in_round !== null && $e->days_left_in_round < 0);
        }

        if ($days = (int) $request->query('no_movement_days')) {
            $cutoff = now()->subDays($days);
            $rows = $rows->filter(function (EndUser $e) use ($cutoff) {
                $last = $this->lastStepAt($e);

                return $last === null || $last->lt($cutoff);
            });
        }

        return $rows->values();
    }

    /** The row shape every list endpoint returns. Exactly these keys, nothing more. */
    public function row(EndUser $e): array
    {
        $roundNo = $e->current_round;

        return [
            'id'                       => $e->id,
            'name'                     => $this->name($e),
            'business_owner_id'        => $e->client_id,
            'business_owner_name'      => $e->client?->business_name,
            'status'                   => $e->status,
            'intake_status'            => $e->intake_status,
            'bucket_label'             => $this->bucketLabel($e),
            'current_round'            => $roundNo > 0 ? (EndUser::ROUND_OPTIONS[$roundNo - 1] ?? null) : null,
            'current_round_started_at' => $this->iso($e->current_round_start_date),
            'days_left_in_round'       => $e->days_left_in_round,
            'rounds_completed'         => max(0, $roundNo - 1),
            'last_process_step_at'     => $this->iso($this->lastStepAt($e)),
            'held_at'                  => $this->iso($e->held_at),
            'created_at'               => $this->iso($e->created_at),
        ];
    }

    /**
     * Names only — never the address, never the contact details.
     *
     * Intake stores the literal string "None" in middle_name/suffix when a client
     * leaves it blank, which reached JARVIS as "Dominique None Johnson" on 104
     * clients. Stripped for the label only: the stored value is untouched, and
     * first/last names are never filtered in case someone really is surnamed None.
     */
    public function name(EndUser $e): string
    {
        $placeholder = fn ($v) => $v !== null && strcasecmp(trim((string) $v), 'none') !== 0;

        $parts = array_filter([
            $e->first_name,
            $placeholder($e->middle_name) ? $e->middle_name : null,
            $e->last_name,
            $placeholder($e->suffix) ? $e->suffix : null,
        ]);

        return trim(implode(' ', $parts));
    }

    /**
     * Which list the team would find this client in. "On Hold" and "Sent for
     * Approval" win over the intake status, because a held or parked client is
     * pulled out of the normal buckets in the dashboard too.
     */
    public function bucketLabel(EndUser $e): string
    {
        if ($e->held_at) {
            return 'On Hold';
        }
        if ($e->round_approval_status === 'awaiting') {
            return 'Sent for Approval';
        }

        return self::BUCKETS[$e->intake_status] ?? 'In Progress';
    }

    /** When anything was last logged for this client — the "no movement" signal. */
    public function lastStepAt(EndUser $e): ?\Illuminate\Support\Carbon
    {
        // Prefer the aggregate loaded by base(); fall back to a query only for a
        // model that arrived some other way.
        $at = array_key_exists('last_step_at', $e->getAttributes())
            ? $e->getAttributes()['last_step_at']
            : $e->processSteps()->max('created_at');

        return $at ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    /** Every timestamp JARVIS sees is ISO 8601 UTC, whatever it was stored as. */
    public function iso($value): ?string
    {
        if (! $value) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->utc()->toIso8601String();
    }
}
