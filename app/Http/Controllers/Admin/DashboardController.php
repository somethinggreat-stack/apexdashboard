<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
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

        $attention   = [];
        $sumPending  = 0;   // new intake awaiting review
        $sumIncomplete = 0; // incomplete weekly logs
        $sumOverdue  = 0;   // overdue rounds
        $payDone     = 0.0;
        $payPending  = 0.0;

        foreach ($clients as $client) {
            $eus = EndUser::forClient($client->id)
                ->withCount([
                    'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                    'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                    'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                    'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
                ])->get();

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

        // All payments across all business owners (newest first)
        $recent = ClientPayment::whereHas('endUser.client', fn ($q) => $q->where('admin_id', $ownerId))
            ->with('endUser.client')
            ->latest('paid_at')
            ->latest('id')
            ->limit(300)
            ->get();

        // Client activity — new clients per day for the last 14 days (sparkline)
        $since = Carbon::now()->subDays(13)->startOfDay();
        $activity = [];
        for ($i = 13; $i >= 0; $i--) {
            $activity[Carbon::now()->subDays($i)->toDateString()] = 0;
        }
        EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->where('created_at', '>=', $since)
            ->pluck('created_at')
            ->each(function ($c) use (&$activity) {
                $d = Carbon::parse($c)->toDateString();
                if (isset($activity[$d])) {
                    $activity[$d]++;
                }
            });
        $activityVals = array_values($activity);

        $newThisMonth = EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // ---- New clients over time: by month (12), by week (12), by day (30) ----
        $byMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $byMonth[$m->format('Y-m')] = ['label' => $m->format('M'), 'sub' => $m->format('Y'), 'count' => 0];
        }
        $byWeek = [];
        for ($i = 11; $i >= 0; $i--) {
            $w = Carbon::now()->subWeeks($i)->startOfWeek();
            $byWeek[$w->format('Y-m-d')] = ['label' => $w->format('M j'), 'sub' => 'wk', 'count' => 0];
        }
        $byDay = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i);
            $byDay[$d->format('Y-m-d')] = ['label' => $d->format('j'), 'sub' => $d->format('M'), 'count' => 0];
        }

        EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->where('created_at', '>=', Carbon::now()->subMonths(12)->startOfMonth())
            ->pluck('created_at')
            ->each(function ($c) use (&$byMonth, &$byWeek, &$byDay) {
                $dt = Carbon::parse($c);
                $mk = $dt->format('Y-m');
                $wk = $dt->copy()->startOfWeek()->format('Y-m-d');
                $dk = $dt->format('Y-m-d');
                if (isset($byMonth[$mk])) { $byMonth[$mk]['count']++; }
                if (isset($byWeek[$wk]))  { $byWeek[$wk]['count']++; }
                if (isset($byDay[$dk]))   { $byDay[$dk]['count']++; }
            });

        $byMonth = array_values($byMonth);
        $byWeek  = array_values($byWeek);
        $byDay   = array_values($byDay);

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
            'payment', 'totalClients', 'activeOwners', 'recent',
            'activityVals', 'newThisMonth', 'onTrackRate',
            'byMonth', 'byWeek', 'byDay'
        ));
    }
}
