<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleLeads
{
    /** Sales-leads pipeline: super admin OR a leads agent. Everyone else 403. */
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->isSuper() || $admin->isLeads()), 403);

        return $next($request);
    }
}
