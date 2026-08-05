<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\CommissionSummary;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    /**
     * A referrer's own referral commission, read-only, inside their portal. Only
     * a business owner flagged is_commission_referrer can reach this, and they
     * only ever see their OWN figures (the exact same live-derived numbers the
     * admin sees for them, via the shared CommissionSummary).
     */
    public function index()
    {
        $bo = Auth::guard('client')->user();
        abort_unless($bo->is_commission_referrer, 404);

        $summary = CommissionSummary::forReferrer($bo);

        return view('client.commissions.index', compact('summary', 'bo'));
    }
}
