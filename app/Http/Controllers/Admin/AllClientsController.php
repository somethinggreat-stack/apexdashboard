<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use Illuminate\Http\Request;

class AllClientsController extends Controller
{
    public function todayQueue(Request $request)
    {
        $clientId = session('selected_client_id');

        $endUsers = EndUser::forClient($clientId)
            ->with('processSteps:id,end_user_id,round,week,step_type,step_date')
            ->clientsList()   // In Progress + Clients Done (exclude New Clients / Errors)
            ->where('status', 'active')
            ->withCount([
                'processSteps',
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ])
            ->get()
            ->filter(fn ($eu) => $eu->is_incomplete)
            ->sortBy(fn ($eu) => $eu->start_date)
            ->values()
            ->groupBy(fn ($eu) => 'Week ' . ($eu->missing_week ?? 1));

        return view('admin.all-clients.today', compact('endUsers'));
    }
}
