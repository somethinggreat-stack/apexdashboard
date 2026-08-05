<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CommissionPayout;
use Illuminate\Support\Collection;

/**
 * Single source of truth for referral commissions, shared by the admin
 * Commissions pages and each referrer's read-only view in their own portal, so
 * the two can never drift. Earned is derived live: for each business owner a
 * referrer referred, (# of real, non-free client payments) × the flat rate.
 *
 * Generalised from the original single "Chantal" referrer to any number of
 * referrers (a referrer is a business owner flagged is_commission_referrer;
 * a referred BO points to them via clients.referrer_id).
 */
class CommissionSummary
{
    public const RATE = 5.00;

    /** Detailed summary for one referrer (used by admin detail + their portal). */
    public static function forReferrer(Client $referrer): array
    {
        $referredBos = Client::where('referrer_id', $referrer->id)
            ->where('admin_id', $referrer->admin_id)   // same org only (defense in depth)
            ->orderBy('business_name')
            ->get();

        $lines = $referredBos->map(fn ($bo) => [
            'bo'       => $bo,
            'payments' => $payments = ClientPayment::forClient($bo->id)->where('is_free', false)->count(),
            'rate'     => self::RATE,
            'earned'   => $payments * self::RATE,
        ])->sortByDesc('earned')->values();

        $earned  = (float) $lines->sum('earned');
        $payouts = CommissionPayout::where('referrer_id', $referrer->id)->latest('paid_at')->get();
        $paid    = (float) $payouts->sum('amount');

        return [
            'referrer'    => $referrer,
            'name'        => $referrer->business_name,
            'rate'        => self::RATE,
            'lines'       => $lines,
            'earned'      => $earned,
            'paid'        => $paid,
            'outstanding' => $earned - $paid,
            'payouts'     => $payouts,
        ];
    }

    /**
     * List every referrer in an organisation with headline totals, for the admin
     * Commissions index. Each row: referrer, referred_count, payments, earned,
     * paid, outstanding.
     */
    public static function referrersForOwner(int $ownerId): Collection
    {
        return Client::where('admin_id', $ownerId)
            ->referrers()
            ->orderBy('business_name')
            ->get()
            ->map(function ($referrer) {
                $referredBos = Client::where('referrer_id', $referrer->id)
                    ->where('admin_id', $referrer->admin_id)   // same org only
                    ->get();
                $payments = $referredBos->sum(
                    fn ($bo) => ClientPayment::forClient($bo->id)->where('is_free', false)->count()
                );
                $earned = $payments * self::RATE;
                $paid   = (float) CommissionPayout::where('referrer_id', $referrer->id)->sum('amount');

                return [
                    'referrer'       => $referrer,
                    'referred_count' => $referredBos->count(),
                    'payments'       => $payments,
                    'earned'         => $earned,
                    'paid'           => $paid,
                    'outstanding'    => $earned - $paid,
                ];
            });
    }
}
