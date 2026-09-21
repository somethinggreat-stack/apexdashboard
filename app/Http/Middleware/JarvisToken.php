<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only door into the JARVIS API: one static bearer token from .env.
 *
 * This is deliberately NOT a user session — JARVIS is a program on the owner's
 * machine, not a person with a login. Three things matter here:
 *
 *  - `hash_equals`, so a wrong token takes the same time as a right one and can't
 *    be discovered a byte at a time.
 *  - The token is never written to a log, an exception or a response. Every
 *    request IS logged (route, params, when, from where) — just never the credential.
 *  - No token configured means no access, even though routes/api-jarvis.php
 *    already refuses to register without one. Two locks on one door: if the route
 *    file is ever loaded another way, this still holds.
 */
class JarvisToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('jarvis.token', '');

        if ($expected === '') {
            return $this->deny($request, 'jarvis token not configured', 503);
        }

        $presented = $this->presentedToken($request);

        if ($presented === null || ! hash_equals($expected, $presented)) {
            return $this->deny($request, 'bad or missing token', 401);
        }

        Log::channel('jarvis')->info('jarvis request', [
            'route'  => $request->path(),
            'params' => $this->safeParams($request),
            'ip'     => $request->ip(),
            'at'     => now()->utc()->toIso8601String(),
        ]);

        return $next($request);
    }

    /** Authorization: Bearer <token>. No query-string fallback — URLs end up in logs. */
    private function presentedToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : null;
    }

    /**
     * Query parameters are safe to log — they are filters, never secrets — but the
     * token is stripped defensively in case a caller ever puts one there by mistake.
     */
    private function safeParams(Request $request): array
    {
        return collect($request->query())
            ->except(['token', 'api_token', 'access_token'])
            ->all();
    }

    private function deny(Request $request, string $why, int $status): Response
    {
        Log::channel('jarvis')->warning('jarvis denied', [
            'route'  => $request->path(),
            'reason' => $why,
            'ip'     => $request->ip(),
            'at'     => now()->utc()->toIso8601String(),
        ]);

        return response()->json(['error' => 'unauthorized'], $status);
    }
}
