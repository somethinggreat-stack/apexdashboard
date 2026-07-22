<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CommissionPayout;

/**
 * Single source of truth for Chantal's referral commission, shared by the admin
 * Commissions page and Chantal's read-only view in her own portal, so the two
 * can never drift. Earned is derived live: for each business owner she referred,
 * (# of real, non-free client payments) × the flat per-payment rate.
 */
class CommissionSummary
{
    public const REFERRER = 'Chantal';
    public const RATE     = 5.00;

    /**
     * Build the summary for one organisation (the super admin who owns the BOs).
     * Returns: name, rate, lines[] (bo, payments, rate, earned), earned, paid,
     * outstanding, payouts.
     */
    public static function forOwner(int $ownerId): array
    {
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

        return [
            'name'        => self::REFERRER,
            'rate'        => self::RATE,
            'lines'       => $lines,
            'earned'      => $earned,
            'paid'        => $paid,
            'outstanding' => $earned - $paid,
            'payouts'     => $payouts,
        ];
    }
}
