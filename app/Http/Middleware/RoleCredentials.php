<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleCredentials
{
    /** Super admin always, or a VA the super admin has granted access. */
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && $admin->canManageCredentials(), 403);

        return $next($request);
    }
}
