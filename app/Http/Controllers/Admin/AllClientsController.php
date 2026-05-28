<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AllClientsController extends Controller
{
    public function todayQueue(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        $endUsers = EndUser::forAdmin($adminId)
            ->with('client:id,business_name')
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
