<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EndUser;
use App\Models\ProcessStep;
use App\Models\ScoreHistory;
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
            'total_deletions' => (int) ProcessStep::forClient($clientId)
                ->selectRaw('COALESCE(SUM(COALESCE(experian_accounts_disputed,0)+COALESCE(transunion_accounts_disputed,0)+COALESCE(equifax_accounts_disputed,0)),0) as total')
                ->value('total'),
            'avg_score_increase' => $this->avgScoreIncrease($endUserIds),
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

    private function avgScoreIncrease($endUserIds): float
    {
        if ($endUserIds->isEmpty()) {
            return 0;
        }

        $changes = [];
        foreach ($endUserIds as $id) {
            $first = ScoreHistory::where('end_user_id', $id)->orderBy('recorded_at')->first();
            $endUser = EndUser::find($id);
            if ($first && $endUser && $endUser->current_score !== null) {
                $changes[] = $endUser->current_score - $first->score;
            }
        }

        return count($changes) ? round(array_sum($changes) / count($changes), 1) : 0;
    }
}
