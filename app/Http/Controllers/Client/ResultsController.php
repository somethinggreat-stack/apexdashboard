<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\BuildsEodReport;
use App\Http\Controllers\Controller;
use App\Support\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only results reporting for a business owner with results tracking on
 * (Clinecea). Reuses the exact EOD builder the VA side uses so the owner sees the
 * same figures — but scoped to only this owner's own clients, with no actions.
 */
class ResultsController extends Controller
{
    use BuildsEodReport;

    public function eod(Request $request)
    {
        $bo = Auth::guard('client')->user();
        abort_unless($bo?->resultsTrackingEnabled(), 403);

        $boIds = collect([$bo->id]);
        $date  = WorkDay::normalise($request->query('date'));

        return view('client.results.eod', $this->eodReportData($boIds, $date));
    }
}
