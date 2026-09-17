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
            // A plain UPDATE, not a model save: saving also bumped `updated_at`, which is the
            // cache-buster in every avatar URL — so every avatar in the app changed URL (and was
            // re-downloaded) roughly every 20 seconds, for everyone.
            \App\Models\Admin::whereKey($admin->id)->toBase()->update(['last_seen_at' => now()]);
            $admin->last_seen_at = now();
        }

        return $next($request);
    }
}
