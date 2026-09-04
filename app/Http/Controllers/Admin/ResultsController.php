<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsEodReport;
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
    use BuildsEodReport;

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
        $boIds = $this->enabledOwnerIds();
        $date  = WorkDay::normalise($request->query('date'));

        return view($this->adminView('admin.results.eod'), $this->eodReportData($boIds, $date));
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
