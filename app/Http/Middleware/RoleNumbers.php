<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleNumbers
{
    /** Super admin always, or a VA the super admin has granted number access. */
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && $admin->canManageNumbers(), 403);

        return $next($request);
    }
}
