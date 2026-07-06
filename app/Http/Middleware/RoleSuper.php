<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleSuper
{
    /** Only the super admin may pass — VAs get 403 (payments, leads, users). */
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && $admin->isSuper(), 403);

        return $next($request);
    }
}
