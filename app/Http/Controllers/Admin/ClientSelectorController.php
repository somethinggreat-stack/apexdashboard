<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientSelectorController extends Controller
{
    public function index()
    {
        $admin   = Auth::guard('admin')->user();
        $adminId = $admin->dataOwnerId();
        $isSuper = $admin->isSuper();

        $clients = Client::forAdmin($adminId)
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();

        $attention = [];   // VAs: what needs work
        $owes      = [];    // super admin: per-BO balances

        foreach ($clients as $client) {
            if ($isSuper) {
                // Super admin sees per-BO balances instead of Needs Attention
                // (Needs Attention lives on their Dashboard).
                $totals = $client->paymentTotals();
                $owes[] = [
                    'client'  => $client,
                    'pending' => $totals['pending'],
                    'done'    => $totals['done'],
                ];
                continue;
            }

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

            if ($pending || $incomplete || $overdue) {
                $attention[] = [
                    'client'     => $client,
                    'pending'    => $pending,
                    'incomplete' => $incomplete,
                    'overdue'    => $overdue,
                    'score'      => $pending + $incomplete + $overdue,
                ];
            }
        }

        usort($attention, fn ($a, $b) => $b['score'] <=> $a['score']);
        usort($owes, fn ($a, $b) => $b['pending'] <=> $a['pending']);

        return view('admin.client-selector.index', compact('clients', 'attention', 'owes'));
    }

    public function select(Request $request, string $id)
    {
        $client = Client::forAdmin(Auth::guard('admin')->user()->dataOwnerId())->findOrFail($id);
        $request->session()->put('selected_client_id', $client->id);

        $redirect = $request->input('redirect_to');
        if ($redirect && str_starts_with($redirect, url('/admin'))) {
            return redirect($redirect);
        }

        // Picking a business owner lands on their Clients list (the main working
        // list), not In Progress.
        return redirect()->route('admin.client-list');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('selected_client_id');

        return redirect()->route('admin.client-selector.index');
    }
}
