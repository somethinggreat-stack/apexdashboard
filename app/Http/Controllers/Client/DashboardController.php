<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $client = Auth::guard('client')->user();
        $clientId = $client->id;
        $weekStart = now()->startOfWeek();

        $endUserIds = EndUser::forClient($clientId)->pluck('id');

        $stats = [
            'total_end_users' => $endUserIds->count(),
            'steps_this_week' => ProcessStep::forClient($clientId)
                ->where('created_at', '>=', $weekStart)->count(),
            'documents_this_week' => Document::forClient($clientId)
                ->where('created_at', '>=', $weekStart)->count(),
            'monthly_revenue' => $endUserIds->count() * $client->monthly_fee,
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
