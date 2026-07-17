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
    /**
     * Commission overview. Earned is derived live from client_payments:
     * for each referred BO, (# of client payments) × commission_per_payment.
     * Payouts (money actually paid to the referrer) are tracked separately.
     */
    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $allBos = Client::where('admin_id', $ownerId)->orderBy('business_name')->get();

        $referrers = $allBos
            ->filter(fn ($bo) => filled($bo->referrer_name))
            ->groupBy('referrer_name')
            ->map(function ($bos, $refName) {
                $lines = $bos->map(function ($bo) {
                    $payments = ClientPayment::forClient($bo->id)->count();
                    $rate     = (float) ($bo->commission_per_payment ?? 0);
                    return [
                        'bo'       => $bo,
                        'payments' => $payments,
                        'rate'     => $rate,
                        'earned'   => $payments * $rate,
                    ];
                })->sortByDesc('earned')->values();

                $earned  = (float) $lines->sum('earned');
                $payouts = CommissionPayout::where('referrer_name', $refName)->latest('paid_at')->get();
                $paid    = (float) $payouts->sum('amount');

                return [
                    'name'        => $refName,
                    'lines'       => $lines,
                    'earned'      => $earned,
                    'paid'        => $paid,
                    'outstanding' => $earned - $paid,
                    'payouts'     => $payouts,
                ];
            })
            ->sortByDesc('outstanding')
            ->values();

        return view($this->adminView('admin.commissions.index'), compact('allBos', 'referrers'));
    }

    /** Mark (or unmark) a business owner as referred, and set the per-payment rate. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'client_id'              => 'required|integer',
            'referrer_name'          => 'nullable|string|max:120',
            'commission_per_payment' => 'nullable|numeric|min:0|max:100000',
        ]);

        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $bo = Client::where('admin_id', $ownerId)->findOrFail($data['client_id']);

        $ref = trim((string) ($data['referrer_name'] ?? ''));

        if ($ref === '') {
            $bo->update(['referrer_name' => null, 'commission_per_payment' => null]);
            return back()->with('status', "Referral removed from {$bo->business_name}.");
        }

        $bo->update([
            'referrer_name'          => $ref,
            'commission_per_payment' => $data['commission_per_payment'] ?? 0,
        ]);

        return back()->with('status', "{$bo->business_name} is now referred by {$ref}.");
    }

    /** Record money paid out to a referrer. */
    public function storePayout(Request $request)
    {
        $data = $request->validate([
            'referrer_name' => 'required|string|max:120',
            'amount'        => 'required|numeric|min:0.01|max:1000000',
            'paid_at'       => 'required|date',
            'note'          => 'nullable|string|max:500',
        ]);

        CommissionPayout::create([
            'referrer_name'       => $data['referrer_name'],
            'amount'              => $data['amount'],
            'paid_at'             => $data['paid_at'],
            'note'                => $data['note'] ?? null,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('status', "Payout to {$data['referrer_name']} recorded.");
    }

    public function destroyPayout(string $id)
    {
        CommissionPayout::findOrFail($id)->delete();

        return back()->with('status', 'Payout deleted.');
    }
}
