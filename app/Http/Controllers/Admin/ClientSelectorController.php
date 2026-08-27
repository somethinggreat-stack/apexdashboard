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

        // Active owners only — an inactive business owner drops off the picker,
        // its balance and its Needs-Attention work. Reactivate it from the
        // Add/Remove Business Owners page to bring it back.
        $clients = Client::forAdmin($adminId)
            ->active()
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

        // Total Collected counts money already taken from EVERY owner, active or
        // not — historical revenue doesn't disappear when an owner goes inactive.
        // (Total Outstanding, by contrast, ignores inactive owners: $owes above is
        // active-only, so we don't chase a churned owner's balance.)
        $collectedAll = array_sum(array_column($owes, 'done'));
        if ($isSuper) {
            $collectedAll += (float) Client::forAdmin($adminId)
                ->where('status', '!=', 'active')
                ->get()
                ->sum(fn ($c) => $c->paymentTotals()['done']);
        }

        return view('admin.client-selector.index', compact('clients', 'attention', 'owes', 'collectedAll'));
    }

    /**
     * Universal Search — its own dedicated page (a VA sidebar item). The page
     * calls search() below for live results.
     */
    public function universalSearch()
    {
        return view('admin.universal-search');
    }

    /**
     * Universal client search for the VA home page: find a client across ALL of
     * the VA's business owners (name / email / phone) and return JSON so the
     * results render inline on the Select Business Owner page. Scoped to the
     * data owner, so a VA never sees clients outside their own accounts.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $rows = EndUser::whereHas('client', fn ($c) => $c->where('admin_id', $ownerId))
            ->search($q)
            ->with('client:id,business_name')
            ->orderBy('first_name')
            ->limit(30)
            ->get();

        $label = function (EndUser $e) {
            if ($e->held_at) {
                return 'On Hold';
            }
            return match ($e->intake_status) {
                'pending_review' => 'New Client',
                'error'          => 'New Client Error',
                'round_error'    => 'Round Error',
                'done'           => 'Done',
                default          => 'In Progress',
            };
        };

        return response()->json([
            'results' => $rows->map(fn (EndUser $e) => [
                'id'      => $e->id,
                'name'    => $e->full_name,
                'email'   => $e->email,
                'bo_id'   => $e->client_id,
                'bo_name' => $e->client?->business_name,
                'status'  => $label($e),
            ])->values(),
        ]);
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
