<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PwaController extends Controller
{
    /**
     * The installed desktop app polls this to raise a native notification when
     * a new client arrives (intake / sign-up link / API). Scoped to the logged-
     * in admin's data owner — every one of their business owners — so it works
     * even before a business owner is selected.
     *
     * Returns the current "New Clients" (pending review) set; the browser diffs
     * it against what it has already seen, so only genuinely new arrivals ever
     * notify. No client data is cached — this is a live, authenticated fetch.
     */
    public function newClientsPoll(Request $request)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $rows = EndUser::whereHas('client', fn ($c) => $c->where('admin_id', $ownerId))
            ->where('intake_status', 'pending_review')
            ->with('client:id,business_name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'count'   => $rows->count(),
            'clients' => $rows->map(fn (EndUser $e) => [
                'id'   => $e->id,
                'name' => $e->full_name,
                'bo'   => $e->client?->business_name,
            ])->values(),
        ]);
    }
}
