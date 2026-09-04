<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Support\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Results reporting for owners with results tracking on (Clinecea). Two reports,
 * both copy-paste ready:
 *   - EOD: what was done today + standing lists (approval, nearing completion, hold, issues).
 *   - Monthly: per client, what they came into the month with, what was deleted /
 *     updated that month, and what remains — pick any month.
 * Everything is computed from negative_items (opened_on / resolved_at / status).
 */
class ResultsController extends Controller
{
    /**
     * Business-owner ids to report on. When a business owner is selected (the
     * reports are opened from that owner's nav), scope to just that owner if it
     * has results tracking on; otherwise fall back to every enabled owner in the
     * org. Either way only results-tracking owners are ever included.
     */
    private function enabledOwnerIds()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $query   = Client::where('admin_id', $ownerId)->where('results_tracking', true);

        if ($selected = session('selected_client_id')) {
            $query->where('id', $selected);
        }

        return $query->pluck('id');
    }

    private function ownerName($boIds): string
    {
        return Client::whereIn('id', $boIds)->orderBy('business_name')->pluck('business_name')->join(', ') ?: '—';
    }

    public function eod(Request $request)
    {
        $boIds     = $this->enabledOwnerIds();
        // The EOD covers ONE SHIFT — the shared WorkDay window (4 PM → 10 AM PKT),
        // the same window as the Daily Task page, so the two always agree.
        $date                = WorkDay::normalise($request->query('date'));
        [$dayStart, $dayEnd] = WorkDay::bounds($date);   // [start, end) half-open

        $clients = EndUser::whereIn('client_id', $boIds)
            ->with(['negativeItems', 'client'])
            ->get();

        // What was worked this shift, keyed by client id.
        $worked = [];
        $add = function (EndUser $eu, string $task) use (&$worked) {
            $worked[$eu->id]['name'] ??= $eu->full_name;
            $worked[$eu->id]['tasks'][] = $task;
        };

        // New clients set up this shift.
        $newClients = $clients->filter(fn ($eu) => $eu->created_at && $eu->created_at->gte($dayStart) && $eu->created_at->lt($dayEnd));
        foreach ($newClients as $eu) {
            $add($eu, 'New client setup');
        }

        // Rounds sent this shift = each client+round whose Week-1 was started in
        // the window. A round's Week 1 has several steps, so dedupe per
        // (client, round) — one "Round N sent" per round, not per step.
        $roundsSentSet = [];
        ProcessStep::whereIn('end_user_id', $clients->pluck('id'))
            ->where('week', 1)
            ->where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $dayEnd)
            ->with('endUser')
            ->get()
            ->each(function (ProcessStep $step) use ($add, &$roundsSentSet) {
                if (!$step->endUser) {
                    return;
                }
                $key = $step->end_user_id . '-' . $step->round;
                if (isset($roundsSentSet[$key])) {
                    return;
                }
                $roundsSentSet[$key] = true;
                $add($step->endUser, 'Round ' . $step->round . ' sent');
            });
        $roundsSent = count($roundsSentSet);

        // Items deleted / updated this shift.
        foreach ($clients as $eu) {
            foreach ($eu->negativeItems as $item) {
                if ($item->resolved_at && $item->resolved_at->gte($dayStart) && $item->resolved_at->lt($dayEnd)) {
                    $add($eu, ($item->status === 'updated' ? 'Updated: ' : 'Deleted: ') . $item->displayName());
                }
            }
        }

        // Standing lists.
        $waitingApproval = $clients->where('round_approval_status', 'awaiting')
            ->map(fn ($eu) => ['name' => $eu->full_name, 'round' => $eu->round_approval_round])->values();

        $nearing = $clients->filter(fn ($eu) => $eu->isNearingCompletion())
            ->map(fn ($eu) => ['name' => $eu->full_name, 'left' => $eu->remainingNegativeCount()])->values();

        $onHold = $clients->whereNotNull('held_at')
            ->map(fn ($eu) => ['name' => $eu->full_name, 'reason' => $eu->move_reason])->values();

        // Show the SPECIFIC error type (e.g. "billing error - Account deactivated"),
        // falling back to the generic bucket label ("Round Error"/"Error") only when
        // a client has no error_type recorded.
        $issues = $clients->whereIn('intake_status', ['error', 'round_error'])
            ->map(fn ($eu) => [
                'name' => $eu->full_name,
                'type' => trim((string) $eu->error_type) !== '' ? $eu->error_type : $eu->resultsStatusLabel(),
            ])->values();

        ksort($worked);

        return view($this->adminView('admin.results.eod'), [
            'ownerName'       => $this->ownerName($boIds),
            'generatedAt'     => Carbon::now()->timezone(WorkDay::TZ),
            'worked'          => array_values($worked),
            'newClientsCount' => $newClients->count(),
            'roundsSent'      => $roundsSent,
            'waitingApproval' => $waitingApproval,
            'nearing'         => $nearing,
            'onHold'          => $onHold,
            'issues'          => $issues,
            'enabled'         => $boIds->isNotEmpty(),
            'workDate'        => $date,
            'workLabel'       => WorkDay::label($date),
            'isCurrent'       => WorkDay::isCurrent($date),
            'recentDays'      => collect(WorkDay::recent(15))->map(fn ($d) => ['date' => $d, 'label' => WorkDay::label($d)])->all(),
        ]);
    }

    public function monthly(Request $request)
    {
        $boIds = $this->enabledOwnerIds();

        $clients = EndUser::whereIn('client_id', $boIds)
            ->with(['negativeItems', 'processSteps:id,end_user_id,round'])
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        // Build the month picker from the span of item activity (open + resolve dates).
        $months = $this->availableMonths($clients);
        $month  = $request->query('month');
        if (!in_array($month, $months, true)) {
            $month = $months[0] ?? Carbon::now()->format('Y-m');
        }
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $rows = [];
        foreach ($clients as $eu) {
            $items = $eu->negativeItems;

            // Only include clients whose file had items on/before the month end.
            $existing = $items->filter(fn ($i) => $i->opened_on && $i->opened_on->lte($end));
            if ($existing->isEmpty()) {
                continue;
            }

            $cameIn    = $existing->filter(fn ($i) => is_null($i->resolved_at) || $i->resolved_at->gte($start));
            $deleted   = $existing->filter(fn ($i) => $i->status === 'deleted' && $i->resolved_at && $i->resolved_at->betweenIncluded($start, $end));
            $updated   = $existing->filter(fn ($i) => $i->status === 'updated' && $i->resolved_at && $i->resolved_at->betweenIncluded($start, $end));
            $remaining = $existing->filter(fn ($i) => is_null($i->resolved_at) || $i->resolved_at->gt($end));

            $names = fn ($col) => $col->map(fn ($i) => $i->displayName())->values()->all();
            $rows[] = [
                'name'       => $eu->full_name,
                'cameIn'     => $names($cameIn),
                'deleted'    => $names($deleted),
                'updated'    => $names($updated),
                'remaining'  => $names($remaining),
                'round'      => 'R' . $eu->current_round,
                'status'     => $eu->resultsStatusLabel(),
            ];
        }

        return view($this->adminView('admin.results.monthly'), [
            'ownerName'   => $this->ownerName($boIds),
            'month'       => $month,
            'monthLabel'  => $start->format('F Y'),
            'months'      => $months,
            'rows'        => $rows,
            'generatedAt' => Carbon::now(),
            'enabled'     => $boIds->isNotEmpty(),
        ]);
    }

    /** Distinct YYYY-MM months (newest first) that have any item activity, always incl. current month. */
    private function availableMonths($clients): array
    {
        $set = [];
        foreach ($clients as $eu) {
            foreach ($eu->negativeItems as $i) {
                if ($i->opened_on)   { $set[$i->opened_on->format('Y-m')] = true; }
                if ($i->resolved_at) { $set[$i->resolved_at->format('Y-m')] = true; }
            }
        }
        $set[Carbon::now()->format('Y-m')] = true;
        $months = array_keys($set);
        rsort($months);

        return $months;
    }
}
