<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommissionPayout;
use App\Support\CommissionSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    /** List of all referrers in this organisation; each opens a detail view. */
    public function index()
    {
        $referrers = CommissionSummary::referrersForOwner(
            Auth::guard('admin')->user()->dataOwnerId()
        );

        return view($this->adminView('admin.commissions.index'), compact('referrers'));
    }

    /** Detailed commission view for one referrer. */
    public function show(string $id)
    {
        $referrer = $this->referrer($id);
        $summary  = CommissionSummary::forReferrer($referrer);

        return view($this->adminView('admin.commissions.show'), compact('summary'));
    }

    /** Record money paid out to a referrer. */
    public function storePayout(Request $request, string $id)
    {
        $referrer = $this->referrer($id);

        $data = $request->validate([
            'amount'  => 'required|numeric|min:0.01|max:1000000',
            'paid_at' => 'required|date',
            'note'    => 'nullable|string|max:500',
        ]);

        CommissionPayout::create([
            'referrer_id'         => $referrer->id,
            'referrer_name'       => $referrer->business_name,
            'amount'              => $data['amount'],
            'paid_at'             => $data['paid_at'],
            'note'                => $data['note'] ?? null,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('status', 'Payout to ' . $referrer->business_name . ' recorded.');
    }

    public function destroyPayout(string $id)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        // Only payouts belonging to a referrer this admin owns.
        $payout = CommissionPayout::whereHas('referrer', fn ($q) => $q->where('admin_id', $ownerId))
            ->findOrFail($id);
        $payout->delete();

        return back()->with('status', 'Payout deleted.');
    }

    /** Resolve a referrer scoped to this admin's organisation, or 404. */
    private function referrer(string $id): Client
    {
        return Client::where('admin_id', Auth::guard('admin')->user()->dataOwnerId())
            ->referrers()
            ->findOrFail($id);
    }
}
