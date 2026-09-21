<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Super-admin cross-business-owner overview. */
    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        // Needs Attention and Business Owner Balances both come from ONE place now:
        // App\Services\OwnerSnapshot. They used to be computed here, and the JARVIS
        // API computed them again slightly differently — $20 outstanding against this
        // screen's $5,414, 112 overdue against 195. A figure with one implementation
        // cannot drift from itself, so the API calls exactly this.
        $snapshot = \App\Services\OwnerSnapshot::forAdmin(Auth::guard('admin')->user());

        $clients   = $snapshot->clients();
        $attention = $snapshot->attentionRows();

        $totals        = $snapshot->attentionTotals();
        $sumPending    = $totals['new'];
        $sumIncomplete = $totals['incomplete'];
        $sumOverdue    = $totals['overdue'];

        $balances   = $snapshot->balanceTotals();
        $payDone    = $balances['collected'];
        $payPending = $balances['outstanding'];

        $totalClients = (int) $clients->sum('end_users_count');
        $activeOwners = $clients->count();

        $newThisMonth = EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId)->where('status', 'active'))
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // Business-owner growth — total on the books + how many joined this month,
        // last month and this year (from each owner's created_at).
        $now            = Carbon::now();
        $startThisMonth = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $startThisYear  = $now->copy()->startOfYear();

        $totalOwners        = $clients->count();
        $ownersNewThisMonth = $clients->filter(fn ($c) => $c->created_at && $c->created_at->gte($startThisMonth))->count();
        $ownersNewLastMonth = $clients->filter(fn ($c) => $c->created_at && $c->created_at->gte($startLastMonth) && $c->created_at->lt($startThisMonth))->count();
        $ownersNewThisYear  = $clients->filter(fn ($c) => $c->created_at && $c->created_at->gte($startThisYear))->count();
        $avgClientsPerOwner = $totalOwners > 0 ? round($totalClients / $totalOwners, 1) : 0;

        $ownerStats = [
            'total'         => $totalOwners,
            'newThisMonth'  => $ownersNewThisMonth,
            'newLastMonth'  => $ownersNewLastMonth,
            'newThisYear'   => $ownersNewThisYear,
            'avgClients'    => $avgClientsPerOwner,
            'thisMonthName' => $now->format('F'),
            'lastMonthName' => $now->copy()->subMonthNoOverflow()->format('F'),
            'yearName'      => $now->format('Y'),
        ];

        // On-track rate: active clients with logs up to date and no overdue round
        $activeTotal = max(0, $totalClients - $sumPending);
        $onTrack     = max(0, $activeTotal - $sumIncomplete - $sumOverdue);
        $onTrackRate = $activeTotal > 0 ? (int) round($onTrack / $activeTotal * 100) : 0;

        $payment = [
            'done'    => $payDone,
            'pending' => $payPending,
            'total'   => $payDone + $payPending,
        ];

        return view('admin.dashboard', compact(
            'clients', 'attention', 'sumPending', 'sumIncomplete', 'sumOverdue',
            'payment', 'totalClients', 'activeOwners',
            'newThisMonth', 'onTrackRate', 'ownerStats'
        ));
    }
}
