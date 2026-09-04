<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\BuildsEodReport;
use App\Http\Controllers\Controller;
use App\Models\ClientPayment;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\NegativeItem;
use App\Models\ProcessStep;
use App\Models\TimePayout;
use App\Support\WorkDay;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use BuildsEodReport;

    public function index()
    {
        $client = Auth::guard('client')->user();
        $clientId = $client->id;
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $endUserIds = EndUser::forClient($clientId)->pluck('id');

        // Real billing figures (matches the Billing page), by comp model.
        if (($client->compensation_model ?? 'per_round') === 'hourly') {
            $totalPaid     = (float) TimePayout::where('client_id', $clientId)->sum('amount_paid');
            $paidThisMonth = (float) TimePayout::where('client_id', $clientId)->where('paid_at', '>=', $monthStart)->sum('amount_paid');
        } else {
            $totalPaid     = (float) ClientPayment::forClient($clientId)->sum('amount');
            $paidThisMonth = (float) ClientPayment::forClient($clientId)->where('paid_at', '>=', $monthStart)->sum('amount');
        }

        $stats = [
            'total_end_users' => $endUserIds->count(),
            // Exclude custom-list clients so these tiles match the portal lists
            // (no-op for owners without custom lists).
            'in_progress'     => EndUser::forClient($clientId)->notHeld()->noCustomList()->inProgress()->count(),
            'done'            => EndUser::forClient($clientId)->notHeld()->noCustomList()->done()->count(),
            'errors'          => EndUser::forClient($clientId)->notHeld()->where('intake_status', 'error')->count(),
            'hold'            => EndUser::forClient($clientId)->onHold()->count(),
            'unread_msgs'     => (int) ($client->unreadCountForClient() ?? 0),
            'total_paid'      => $totalPaid,
            'paid_this_month' => $paidThisMonth,
            'steps_this_week' => ProcessStep::forClient($clientId)
                ->where('created_at', '>=', $weekStart)->count(),
            'documents_this_week' => Document::forClient($clientId)
                ->where('created_at', '>=', $weekStart)->count(),
        ];

        $recentSteps = ProcessStep::forClient($clientId)
            ->with(['endUser', 'createdBy'])
            ->orderBy('step_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get();

        // Results & EOD snapshot — Clinecea only (results-tracking owners). Gives
        // her the same figures the EOD carries, right on the dashboard, for peace
        // of mind: what was done this shift + lifetime deletions/updates + what's
        // waiting on her.
        $results = null;
        if ($client->resultsTrackingEnabled()) {
            $eod = $this->eodReportData(collect([$clientId]), WorkDay::normalise(null));

            $ni = NegativeItem::whereIn('end_user_id', $endUserIds)
                ->selectRaw("
                    SUM(CASE WHEN status = 'reporting' THEN 1 ELSE 0 END) AS reporting,
                    SUM(CASE WHEN status = 'deleted'   THEN 1 ELSE 0 END) AS deleted,
                    SUM(CASE WHEN status = 'updated'   THEN 1 ELSE 0 END) AS updated
                ")->first();

            $totalItems = (int) $ni->reporting + (int) $ni->deleted + (int) $ni->updated;
            $resolved   = (int) $ni->deleted + (int) $ni->updated;

            $results = [
                'reporting'        => (int) $ni->reporting,
                'deleted'          => (int) $ni->deleted,
                'updated'          => (int) $ni->updated,
                'resolved'         => $resolved,
                'success_rate'     => $totalItems > 0 ? (int) round($resolved / $totalItems * 100) : 0,
                'worked_today'     => count($eod['worked']),
                'rounds_sent'      => $eod['roundsSent'],
                'new_today'        => $eod['newClientsCount'],
                'awaiting'         => $eod['waitingApproval']->count(),
                'nearing'          => $eod['nearing']->count(),
                'on_hold'          => $eod['onHold']->count(),
                'issues'           => $eod['issues']->count(),
                'shift_label'      => $eod['workLabel'],
                'is_current'       => $eod['isCurrent'],
            ];
        }

        return view('client.dashboard.index', compact('stats', 'recentSteps', 'client', 'results'));
    }
}
