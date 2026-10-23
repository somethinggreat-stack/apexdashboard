<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Keeps the three public hosts apart (only when config('app.site_split') is on):
 *
 *  - root (apexgrowthsolution.com / www)   the front door + every app (admin, owner
 *                                          portal, intake, API). The old B2B marketing
 *                                          URLs 301 to the partners host.
 *  - partners.*                            the B2B marketing pages only. Anything else
 *                                          (admin, owner login, intake, API…) is sent to
 *                                          the same path on the root host, so existing
 *                                          links and bookmarks keep working.
 *  - consumers.*                           the consumer site only; unknown paths go to its home.
 *
 * Every other host (chat, localhost, tests) is untouched, and with the flag off this
 * middleware does nothing at all — so it can be deployed safely ahead of the DNS/cPanel work.
 */
class SiteHostRouter
{
    /** B2B marketing: served on the partners host. */
    private const PARTNER_PATHS = [
        '', 'about', 'contact', 'results', 'trial', 'service-areas', 'sitemap.xml',
        'leads', 'contact-submit',          // the marketing forms' POST endpoints
    ];

    /** Consumer site paths (extended as pages are built). */
    private const CONSUMER_PATHS = [
        '',
    ];

    /** Needed on every public host (CSRF refresh for forms, health check). */
    private const SHARED = ['session/token', 'up'];

    public function handle(Request $request, Closure $next)
    {
        if (! config('app.site_split')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $root = strtolower((string) config('app.root_host'));

        if ($host === strtolower((string) config('app.partners_host'))) {
            return $this->allowed($request, self::PARTNER_PATHS)
                ? $next($request)
                : redirect()->away($this->on($root, $request), 302);
        }

        if ($host === strtolower((string) config('app.consumers_host'))) {
            return $this->allowed($request, self::CONSUMER_PATHS)
                ? $next($request)
                : redirect()->away($this->on($host, $request, '/'), 302);
        }

        if ($host === $root || $host === 'www.' . $root) {
            // Old B2B marketing URLs → the partners host (permanent, keeps SEO).
            if ($this->isPartnerMarketing($request)) {
                return redirect()->away($this->on((string) config('app.partners_host'), $request), 301);
            }
        }

        return $next($request);
    }

    private function allowed(Request $request, array $paths): bool
    {
        $path = trim($request->path(), '/');
        if ($path === '/') {
            $path = '';
        }
        foreach (array_merge($paths, self::SHARED) as $p) {
            if ($p === '') {
                if ($path === '') {
                    return true;
                }
                continue;
            }
            if ($path === $p || str_starts_with($path, $p . '/')) {
                return true;
            }
        }
        return false;
    }

    /** GET-only: the marketing pages themselves (form POSTs keep working on the root). */
    private function isPartnerMarketing(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }
        $path = trim($request->path(), '/');
        foreach (['about', 'contact', 'results', 'trial', 'service-areas'] as $p) {
            if ($path === $p || str_starts_with($path, $p . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Same path + query on another host, always https. */
    private function on(string $host, Request $request, ?string $path = null): string
    {
        $path  = $path ?? '/' . ltrim($request->path(), '/');
        $query = $request->getQueryString();
        return 'https://' . $host . ($path === '//' ? '/' : $path) . ($query ? '?' . $query : '');
    }
}
