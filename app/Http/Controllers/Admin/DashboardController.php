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

        $clients = Client::forAdmin($ownerId)
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();

        // Load every client of every business owner in ONE query (with the four
        // week-step counts) and group them in memory, instead of firing a heavy
        // 4-subquery SELECT per business owner. Same numbers, far fewer round
        // trips — the old loop was the dashboard's main slowdown.
        $eusByClient = EndUser::whereIn('client_id', $clients->pluck('id'))
            ->withCount([
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ])
            ->get()
            ->groupBy('client_id');

        $attention   = [];
        $sumPending  = 0;   // new intake awaiting review
        $sumIncomplete = 0; // incomplete weekly logs
        $sumOverdue  = 0;   // overdue rounds
        $payDone     = 0.0;
        $payPending  = 0.0;

        foreach ($clients as $client) {
            $eus = $eusByClient->get($client->id, collect());

            $pending    = $eus->where('intake_status', 'pending_review')->count();
            $active     = $eus->filter(fn ($e) => $e->intake_status !== 'pending_review');
            $incomplete = $active->filter(fn ($e) => $e->is_incomplete)->count();
            $overdue    = $active->filter(fn ($e) => $e->days_left_in_round !== null && $e->days_left_in_round < 0)->count();

            $sumPending    += $pending;
            $sumIncomplete += $incomplete;
            $sumOverdue    += $overdue;

            if ($pending || $incomplete || $overdue) {
                $attention[] = [
                    'client'     => $client,
                    'pending'    => $pending,
                    'incomplete' => $incomplete,
                    'overdue'    => $overdue,
                    'score'      => $pending + $incomplete + $overdue,
                ];
            }

            $totals     = $client->paymentTotals();
            $payDone    += $totals['done'];
            $payPending += $totals['pending'];
        }

        usort($attention, fn ($a, $b) => $b['score'] <=> $a['score']);

        $totalClients = (int) $clients->sum('end_users_count');
        $activeOwners = $clients->count();

        $newThisMonth = EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
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
