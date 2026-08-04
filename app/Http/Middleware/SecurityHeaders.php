<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defense-in-depth security response headers on every request.
 *
 * Pragmatic Content-Security-Policy: the whole app is same-origin, the only
 * external resources are Google Fonts on the public marketing pages, and
 * WhatsApp (wa.me) links are plain anchors (navigation, not subject to CSP).
 * Inline <script>/<style> and on* handlers are used throughout the dashboard,
 * so 'unsafe-inline' is allowed — but eval() is NOT ('unsafe-eval' omitted) and
 * framing is denied, which blocks the highest-impact XSS/clickjacking vectors.
 *
 * Tightening later (nonce-based script-src, removing 'unsafe-inline') is a code
 * change only; no infrastructure dependency.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            'Content-Security-Policy'    => $csp,
            'X-Content-Type-Options'     => 'nosniff',
            'X-Frame-Options'            => 'DENY',
            'Referrer-Policy'            => 'strict-origin-when-cross-origin',
            'Permissions-Policy'         => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            // Never clobber a header a specific response deliberately set.
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // HSTS only over real HTTPS, so it can never poison local http:// dev.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
