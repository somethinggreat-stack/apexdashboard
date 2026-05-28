<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AllClientsController extends Controller
{
    public function index(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        $query = EndUser::forAdmin($adminId)
            ->with('client:id,business_name')
            ->withCount([
                'processSteps',
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('bo')) {
            $query->where('client_id', $request->integer('bo'));
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $endUsers = $query->orderBy('start_date', 'asc')->orderBy('first_name')->get()
            ->sort(function ($a, $b) {
                $byIncomplete = ($b->is_incomplete ? 1 : 0) <=> ($a->is_incomplete ? 1 : 0);
                if ($byIncomplete !== 0) return $byIncomplete;
                return $a->start_date <=> $b->start_date;
            })
            ->values();

        $businessOwners = \App\Models\Client::where('admin_id', $adminId)
            ->orderBy('business_name')
            ->get(['id', 'business_name']);

        return view('admin.all-clients.index', compact('endUsers', 'businessOwners'));
    }

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
