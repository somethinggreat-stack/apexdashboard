<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\AdminSignIn;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Runs on every signed-in staff page.
 *
 *  - Enrolled account whose session has NOT passed the code check (a "remember me" cookie, or a
 *    session that started before they enrolled): signed out of this session and sent to the
 *    code page, so a stolen cookie or an old session can't skip the second factor.
 *  - Account whose role must have 2FA but doesn't yet: every page except the setup pages (and
 *    sign-out) sends them to set it up first.
 *
 * With TWO_FACTOR_ENFORCE=false this does nothing at all.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if (! $user instanceof Admin || ! Admin::twoFactorEnabled()) {
            return $next($request);
        }

        // The 2FA pages themselves and sign-out must always work, or there'd be no way through.
        if ($request->routeIs('admin.two-factor.*', 'admin.logout')) {
            return $next($request);
        }

        if ($user->hasTwoFactor()) {
            if ($request->session()->has(AdminSignIn::PASSED)) {
                return $next($request);
            }

            $chat = str_contains((string) $request->userAgent(), 'ApexDesktop')
                || $request->getHost() === config('app.chat_host');

            Auth::guard('admin')->logout();   // also retires any remember-me cookie
            $request->session()->put(AdminSignIn::PENDING, [
                'id'       => $user->id,
                'remember' => false,
                'chat'     => $chat,
                'at'       => now()->timestamp,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Two-factor verification required.'], 401)
                : redirect()->route('admin.two-factor.challenge');
        }

        if ($user->requiresTwoFactor()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Two-factor setup required.', 'redirect' => route('admin.two-factor.setup')], 403)
                : redirect()->route('admin.two-factor.setup')
                    ->with('status', 'For security, please set up your authenticator app before continuing.');
        }

        return $next($request);
    }
}
