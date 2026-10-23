<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

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
 *                                          Until CONSUMERS_LIVE is on, the public only sees the
 *                                          "coming soon" page; the team previews the real site with
 *                                          ?preview=<CONSUMERS_PREVIEW_KEY>.
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

    /** Consumer site paths once it is live (or being previewed). */
    private const CONSUMER_PATHS = [
        '', 'sitemap.xml', 'services', 'how-it-works', 'score-guide', 'packages', 'results',
        'faq', 'about', 'contact', 'privacy', 'terms', 'consumer-credit-file-rights', 'notice-of-cancellation',
    ];

    /** All the public needs before launch. */
    private const CONSUMER_PATHS_PRELAUNCH = ['', 'sitemap.xml'];

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
            return $this->consumers($request, $next);
        }

        if ($host === $root || $host === 'www.' . $root) {
            // Old B2B marketing URLs → the partners host (permanent, keeps SEO).
            if ($this->isPartnerMarketing($request)) {
                return redirect()->away($this->on((string) config('app.partners_host'), $request), 301);
            }
        }

        return $next($request);
    }

    private function consumers(Request $request, Closure $next)
    {
        $previewKey = (string) config('app.consumers_preview_key');
        $cookieVal  = $previewKey !== '' ? hash('sha256', 'consumers-preview|' . $previewKey) : '';

        // Raw cookie on purpose: this runs before the web group decrypts cookies.
        $viaLink   = $previewKey !== '' && hash_equals($previewKey, (string) $request->query('preview', ''));
        $viaCookie = $cookieVal !== '' && hash_equals($cookieVal, (string) $request->cookies->get('consumers_preview', ''));
        $live      = (bool) config('app.consumers_live') || $viaLink || $viaCookie;

        $request->attributes->set('consumers_live', $live);

        $paths = $live ? self::CONSUMER_PATHS : self::CONSUMER_PATHS_PRELAUNCH;
        if (! $this->allowed($request, $paths)) {
            return redirect()->away($this->on(strtolower($request->getHost()), $request, '/'), 302);
        }

        $response = $next($request);

        // Remember a preview for 12 hours so the team can click around.
        if ($viaLink && ! $viaCookie) {
            $response->headers->setCookie(new Cookie(
                'consumers_preview', $cookieVal, time() + 43200, '/', null, true, true, false, 'Lax'
            ));
        }
        // A preview must never be cached or indexed.
        if ($live && ! config('app.consumers_live')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
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
