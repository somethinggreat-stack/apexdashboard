/*
 * Apex dashboard service worker — INSTALLABILITY + STATIC-ASSET CACHING ONLY.
 *
 * Security rule for this app (live SSNs, CFPB credentials): never cache
 * authenticated HTML or any client data. Navigations always go to the network;
 * only versioned static brand/app assets (css / js / Images / lottie) are
 * cached. On logout the app posts CLEAR_CACHES so nothing lingers.
 */
const VERSION = 'v1';
const STATIC_CACHE = 'apex-static-' + VERSION;

// Non-sensitive shell assets safe to precache.
const PRECACHE = [
    '/offline.html',
    '/favicon.svg',
    '/manifest.webmanifest',
    '/Images/pwa/icon-192.png',
    '/Images/pwa/icon-512.png',
];

// Path prefixes that are safe to runtime-cache (static, non-sensitive).
const STATIC_PREFIXES = ['/css/', '/js/', '/Images/', '/lottie/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('apex-static-') && k !== STATIC_CACHE)
                    .map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// Let the page trigger an immediate update or a full cache purge (on logout).
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') { self.skipWaiting(); }
    if (event.data === 'CLEAR_CACHES') {
        caches.keys().then((keys) => keys.forEach((k) => caches.delete(k)));
    }
});

function isStaticAsset(url) {
    return STATIC_PREFIXES.some((p) => url.pathname.startsWith(p))
        || url.pathname === '/favicon.svg'
        || url.pathname === '/manifest.webmanifest';
}

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only ever touch same-origin GETs. Everything else (POST, cross-origin)
    // passes straight through untouched.
    if (req.method !== 'GET') { return; }
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }

    // Page navigations: network-first, NEVER cached. Fall back to the offline
    // page only when the network is unreachable.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Static assets: serve from cache, refresh in the background
    // (stale-while-revalidate). Cache key includes the ?v= build stamp, so a
    // deployed asset change is fetched fresh under a new key automatically.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then((cache) =>
                cache.match(req).then((cached) => {
                    const network = fetch(req).then((res) => {
                        if (res && res.status === 200 && res.type === 'basic') {
                            cache.put(req, res.clone());
                        }
                        return res;
                    }).catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
    // Anything else: default browser handling (no caching).
});
