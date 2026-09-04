<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Support\WorkDay;
use Illuminate\Support\Carbon;

/**
 * Builds the EOD report data for a shift — shared by the admin (VA) view and the
 * business owner's read-only view so both always show identical figures. Given a
 * set of results-tracking owner ids and a normalised shift date, returns every
 * view variable the eod blade needs.
 */
trait BuildsEodReport
{
    protected function eodReportData($boIds, string $date): array
    {
        // The EOD covers ONE SHIFT — the shared WorkDay window (4 PM → 10 AM PKT),
        // the same window as the Daily Task page, so the two always agree.
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

        // Rounds sent this shift = each client+round whose Week-1 was started in the
        // window, deduped per (client, round) — one "Round N sent" per round.
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
        // falling back to the generic bucket label only when none is recorded.
        $issues = $clients->whereIn('intake_status', ['error', 'round_error'])
            ->map(fn ($eu) => [
                'name' => $eu->full_name,
                'type' => trim((string) $eu->error_type) !== '' ? $eu->error_type : $eu->resultsStatusLabel(),
            ])->values();

        ksort($worked);

        return [
            'ownerName'       => Client::whereIn('id', $boIds)->orderBy('business_name')->pluck('business_name')->join(', ') ?: '—',
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
        ];
    }
}
