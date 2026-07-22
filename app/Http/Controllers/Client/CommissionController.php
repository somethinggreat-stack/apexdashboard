<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\CommissionSummary;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    /**
     * Chantal's own referral commission, read-only, inside her portal. Only a BO
     * flagged is_commission_referrer can reach this. Figures are the exact same
     * live-derived numbers the admin sees (shared CommissionSummary).
     */
    public function index()
    {
        $bo = Auth::guard('client')->user();
        abort_unless($bo->is_commission_referrer, 404);

        $summary = CommissionSummary::forOwner($bo->admin_id);

        return view('client.commissions.index', compact('summary', 'bo'));
    }
}
