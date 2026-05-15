<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $clientId = session('selected_client_id');
        $weekStart = now()->startOfWeek();

        $stats = [
            'total_end_users' => EndUser::forClient($clientId)->count(),
            'active_end_users' => EndUser::forClient($clientId)->where('status', 'active')->count(),
            'steps_this_week' => ProcessStep::forClient($clientId)
                ->where('created_at', '>=', $weekStart)
                ->count(),
            'deletions_this_week' => (int) ProcessStep::forClient($clientId)
                ->where('step_date', '>=', $weekStart)
                ->selectRaw('COALESCE(SUM(COALESCE(experian_accounts_disputed,0)+COALESCE(transunion_accounts_disputed,0)+COALESCE(equifax_accounts_disputed,0)),0) as total')
                ->value('total'),
            'documents_this_week' => Document::forClient($clientId)
                ->where('created_at', '>=', $weekStart)
                ->count(),
            'monthly_revenue' => DB::table('clients')
                ->join('end_users', 'end_users.client_id', '=', 'clients.id')
                ->where('clients.id', $clientId)
                ->where('end_users.status', 'active')
                ->sum('clients.monthly_fee'),
        ];

        $recentSteps = ProcessStep::forClient($clientId)
            ->with(['endUser', 'createdBy'])
            ->orderBy('step_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard.index', compact('stats', 'recentSteps'));
    }
}
