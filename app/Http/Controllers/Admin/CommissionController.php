<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CommissionPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    /** The referral partner and the flat commission per real client payment. */
    private const REFERRER = 'Chantal';
    private const RATE     = 5.00;

    /**
     * Commission overview for Chantal. Earned is derived live from real client
     * payments (test/free ones excluded): for each referred BO, (# of real
     * payments) × $5. Payouts are tracked separately.
     */
    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $referredBos = Client::where('admin_id', $ownerId)
            ->where('referred_by_chantal', true)
            ->orderBy('business_name')
            ->get();

        $lines = $referredBos->map(function ($bo) {
            $payments = ClientPayment::forClient($bo->id)->where('is_free', false)->count();
            return [
                'bo'       => $bo,
                'payments' => $payments,
                'rate'     => self::RATE,
                'earned'   => $payments * self::RATE,
            ];
        })->sortByDesc('earned')->values();

        $earned  = (float) $lines->sum('earned');
        $payouts = CommissionPayout::where('referrer_name', self::REFERRER)->latest('paid_at')->get();
        $paid    = (float) $payouts->sum('amount');

        $summary = [
            'name'        => self::REFERRER,
            'rate'        => self::RATE,
            'lines'       => $lines,
            'earned'      => $earned,
            'paid'        => $paid,
            'outstanding' => $earned - $paid,
            'payouts'     => $payouts,
        ];

        return view($this->adminView('admin.commissions.index'), compact('summary'));
    }

    /** Record money paid out to Chantal. */
    public function storePayout(Request $request)
    {
        $data = $request->validate([
            'amount'  => 'required|numeric|min:0.01|max:1000000',
            'paid_at' => 'required|date',
            'note'    => 'nullable|string|max:500',
        ]);

        CommissionPayout::create([
            'referrer_name'       => self::REFERRER,
            'amount'              => $data['amount'],
            'paid_at'             => $data['paid_at'],
            'note'                => $data['note'] ?? null,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('status', 'Payout to ' . self::REFERRER . ' recorded.');
    }

    public function destroyPayout(string $id)
    {
        CommissionPayout::findOrFail($id)->delete();

        return back()->with('status', 'Payout deleted.');
    }
}
