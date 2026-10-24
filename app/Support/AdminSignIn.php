<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The tail end of a staff sign-in, shared by the dashboard login, the Team Chat login and the
 * two-factor challenge so all three finish identically (session, log line, where to land).
 */
final class AdminSignIn
{
    public const PENDING = 'admin_2fa_pending';   // password OK, authenticator code still owed
    public const PASSED  = 'admin_2fa_at';        // this session proved a code (or enrolled)

    /** Sign the person in. $twoFactorDone marks the session as having passed the code check. */
    public static function complete(Request $request, Admin $user, bool $remember, bool $chat, bool $twoFactorDone = false)
    {
        Auth::guard('admin')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget(self::PENDING);
        if ($twoFactorDone) {
            $request->session()->put(self::PASSED, now()->timestamp);
        }

        self::log($request, $user, 'login', 'Logged in', $chat ? '/admin/chat-login' : '/admin/login');

        if ($chat) {
            return redirect()->route('admin.team-messages.index', ['standalone' => 1]);
        }

        // One-shot flag: the walk-on animation plays on the first page the super admin or a VA
        // sees after signing in, then clears itself.
        if ($user->isSuper() || $user->isVa()) {
            $request->session()->put('walker_once', true);
        }

        // Leads agents can't reach the business-owner workflow — land them on their leads
        // pipeline (and don't honour a stored "intended" URL they'd only get a 403 on).
        if ($user->isLeads()) {
            return redirect()->route('admin.prospect-leads.index', ['channel' => 'whatsapp']);
        }
        if ($user->isSuper()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('admin.client-selector.index'));
    }

    /** Park a correct password until the authenticator code arrives. Nothing is signed in yet. */
    public static function holdForCode(Request $request, Admin $user, bool $remember, bool $chat)
    {
        $request->session()->regenerate();
        $request->session()->put(self::PENDING, [
            'id'       => $user->id,
            'remember' => $remember,
            'chat'     => $chat,
            'at'       => now()->timestamp,
        ]);

        return redirect()->route('admin.two-factor.challenge');
    }

    /** The parked sign-in, or null if there is none or it has gone stale. */
    public static function pending(Request $request): ?array
    {
        $p = $request->session()->get(self::PENDING);
        if (! is_array($p) || empty($p['id'])) {
            return null;
        }
        if (now()->timestamp - (int) ($p['at'] ?? 0) > (int) config('team.two_factor.pending_minutes', 5) * 60) {
            $request->session()->forget(self::PENDING);

            return null;
        }

        return $p;
    }

    /** One-line security trail. Never stores codes, secrets or request bodies. */
    public static function log(Request $request, ?Admin $user, string $action, string $description, ?string $path = null): void
    {
        ActivityLog::create([
            'admin_id'    => $user?->id,
            'action'      => $action,
            'description' => $description,
            'method'      => $request->method(),
            'path'        => $path ?? '/' . ltrim($request->path(), '/'),
            'ip'          => $request->ip(),
        ]);
    }
}
