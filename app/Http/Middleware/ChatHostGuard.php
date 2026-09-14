<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The chat subdomain (config('app.chat_host')) is a chat-only front door, even
 * though it shares the dashboard's codebase. Any request there that isn't part of
 * the Team Chat surface is bounced to the chat, so nobody can reach dashboard pages
 * by typing their URL on chat.apexgrowthsolution.com. On every other host this is a
 * no-op. Static assets are served by the web server, not routed, so they're unaffected.
 */
class ChatHostGuard
{
    /** Path prefixes the chat host is allowed to serve (everything the chat app touches). */
    private const ALLOWED = [
        'admin/team-messages',   // the chat itself + all its poll/action endpoints + attachments
        'admin/chat-login',      // the chat sign-in
        'admin/logout',
        'admin/push',            // web-push subscribe/unsubscribe
        'admin/profile',         // own profile (avatar, password)
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->getHost() !== config('app.chat_host')) {
            return $next($request);
        }

        $path = $request->path();   // e.g. "admin/team-messages" — no leading slash, no query

        if ($path === 'up') {       // health check
            return $next($request);
        }

        foreach (self::ALLOWED as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // Anything else on the chat host → the chat (guests are then bounced to chat login).
        return redirect()->route('admin.team-messages.index', ['standalone' => 1]);
    }
}
