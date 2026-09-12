<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Refresh the signed-in admin's last_seen_at as they move around the app, so
 * presence ("online" / "last seen") works without websockets. Throttled to at
 * most one write every ~20s per user to avoid a write on every request.
 */
class TrackPresence
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && (! $admin->last_seen_at || $admin->last_seen_at->lt(now()->subSeconds(20)))) {
            $admin->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
