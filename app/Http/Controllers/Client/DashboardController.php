<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPayment;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Models\TimePayout;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
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
            'in_progress'     => EndUser::forClient($clientId)->notHeld()->inProgress()->count(),
            'done'            => EndUser::forClient($clientId)->notHeld()->done()->count(),
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

        return view('client.dashboard.index', compact('stats', 'recentSteps', 'client'));
    }
}
