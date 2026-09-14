<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Team Chat is available ONLY through the Apex desktop app, which identifies itself with the
 * "ApexDesktop" user-agent. Any plain browser trying to reach the chat or its sign-in is sent
 * to the download page instead — so nobody can use Team Chat in a browser. Runs on every host;
 * all non-chat routes (the whole dashboard) are untouched.
 */
class ChatAppOnly
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        $isChat = $path === 'admin/chat-login'
            || str_starts_with($path, 'admin/team-messages');

        if (! $isChat) {
            return $next($request);
        }

        // The desktop app is allowed through.
        if (str_contains((string) $request->userAgent(), 'ApexDesktop')) {
            return $next($request);
        }

        // A browser → not allowed. JSON callers get 403; page loads go to the app download.
        if ($request->expectsJson() || $request->ajax()) {
            abort(403, 'Team Chat is only available in the Apex desktop app.');
        }

        return redirect()->away('https://' . config('app.chat_host') . '/download');
    }
}
