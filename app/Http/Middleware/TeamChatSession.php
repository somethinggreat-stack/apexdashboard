<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-PC guard for the chat's own requests.
 *
 * A browser profile shares one cookie jar, so a tab left open by the PREVIOUS VA would send
 * the NEW VA's cookie and quietly read/post as them. Chat requests therefore carry
 * "X-Apex-Chat-Session: <admin id>.<token>"; this middleware compares it with the session
 * actually signed in:
 *   - a different admin  → 401: that tab belongs to someone who is no longer signed in here.
 *   - the same admin, older token (Laravel renewed the session — e.g. "keep me signed in"
 *     after an idle period) → 409 plus a fresh token, so the page updates it and carries on
 *     instead of throwing the VA out mid-sentence.
 * The header is optional: requests without it are untouched, so nothing else breaks.
 */
class TeamChatSession
{
    /**
     * A per-sign-in scope id kept IN the session. It survives Laravel rotating the session id
     * (which happens on login and can happen mid-life), but a genuinely new session — a fresh
     * sign-in, or "keep me signed in" re-authenticating after an idle spell — gets a new one.
     */
    private static function scope(Request $request): string
    {
        $session = $request->session();
        if (! $session->has('apex_chat_scope')) {
            $session->put('apex_chat_scope', bin2hex(random_bytes(16)));
        }

        return (string) $session->get('apex_chat_scope');
    }

    public static function token(Request $request): string
    {
        return hash_hmac('sha256', self::scope($request) . ':' . Auth::guard('admin')->id(), (string) config('app.key'));
    }

    /** What the chat page sends back with each request: who it thinks it is, and its token. */
    public static function stamp(Request $request): string
    {
        return Auth::guard('admin')->id() . '.' . self::token($request);
    }

    /**
     * How long a chat session stays valid, in minutes. The chat is a desk tool that sits open
     * all shift: with the app's default 2-hour lifetime, the first send after a quiet spell
     * failed and showed "Reconnecting…". Chat requests keep their own session alive for this
     * long instead, so an idle stretch never interrupts the next message. Configurable with
     * TEAM_CHAT_SESSION_DAYS.
     */
    private function chatLifetimeMinutes(): int
    {
        return max((int) config('session.lifetime'), (int) config('team.chat.session_days', 30) * 24 * 60);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/team-messages', 'admin/team-messages/*', 'admin/chat-login', 'admin/logout')
            || str_contains((string) $request->userAgent(), 'ApexDesktop')) {
            config(['session.lifetime' => $this->chatLifetimeMinutes()]);
        }

        $sent = (string) $request->header('X-Apex-Chat-Session', '');

        if ($sent !== '') {
            [$uid, $token] = array_pad(explode('.', $sent, 2), 2, '');
            $mine = (string) Auth::guard('admin')->id();

            if ($uid !== $mine) {
                return response()->json([
                    'message' => 'Your chat session has ended — this window belongs to a different sign-in.',
                    'reason'  => 'account-changed',
                ], 401)->header('Cache-Control', 'no-store');
            }

            if (! hash_equals(self::token($request), (string) $token)) {
                // Same person, renewed session: hand back the new token and let them continue.
                return response()->json([
                    'reason' => 'session-renewed',
                    'stamp'  => self::stamp($request),
                ], 409)->header('Cache-Control', 'no-store');
            }
        }

        $response = $next($request);

        // Chat pages and chat data are per-person and must never sit in a shared cache. Other
        // admin responses keep their own caching (attachments and avatars set theirs
        // deliberately, and the dashboard stays cacheable for back/forward).
        // (An explicit max-age means the controller deliberately made it cacheable — chat
        // images and avatars, which are private but safe for the browser to keep.)
        if ($request->is('admin/team-messages', 'admin/team-messages/*')
            && ! str_contains((string) $response->headers->get('Cache-Control'), 'max-age')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
