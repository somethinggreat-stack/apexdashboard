<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleClients
{
    /**
     * Business-owner / client workflow: super admin OR a VA. A leads agent is
     * blocked (403) — they only ever see the leads pipeline.
     */
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->isSuper() || $admin->isVa()), 403);

        return $next($request);
    }
}
