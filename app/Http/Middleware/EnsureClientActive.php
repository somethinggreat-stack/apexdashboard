<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Non-payment wall. When a business owner's access is revoked, every page in
 * their portal is replaced with the "access revoked" notice showing what they
 * owe — they can do nothing but read it and log out.
 */
class EnsureClientActive
{
    public function handle(Request $request, Closure $next)
    {
        $client = Auth::guard('client')->user();

        if ($client && $client->access_revoked) {
            // Always allow logout so they can leave.
            if ($request->routeIs('client.logout')) {
                return $next($request);
            }

            $outstanding = 0.0;
            try {
                $outstanding = (float) ($client->paymentTotals()['pending'] ?? 0);
            } catch (\Throwable $e) {
                $outstanding = 0.0;
            }

            return response()->view('client.access-revoked', [
                'client'      => $client,
                'outstanding' => $outstanding,
            ], 403);
        }

        return $next($request);
    }
}
