/*
 * Apex service worker — WEB PUSH ONLY.
 *
 * The installable-PWA/offline behaviour was removed at the team's request. This
 * worker no longer caches anything and no longer intercepts navigations; it only
 * exists to receive Web Push messages so chat notifications arrive even when the
 * tab is backgrounded/throttled or the browser is closed.
 *
 * Security rule for this app (live SSNs, CFPB credentials): never cache
 * authenticated HTML or client data. This worker caches nothing at all.
 */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        // Purge every cache left behind by the old installable-PWA worker.
        try {
            const keys = await caches.keys();
            await Promise.all(keys.map((k) => caches.delete(k)));
        } catch (e) {}
        await self.clients.claim();
    })());
});

// Incoming push from the server → show an OS notification.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) {}
    const title = data.title || 'Apex Team Chat';
    const body  = data.body  || 'New message';
    const url   = data.url   || '/admin/team-messages';
    event.waitUntil(self.registration.showNotification(title, {
        body: body,
        tag: data.tag || 'apex-team',
        renotify: true,
        icon: '/Images/pwa/icon-192.png',
        badge: '/Images/pwa/icon-192.png',
        data: { url: url }
    }));
});

// Click a notification → focus an existing Apex tab (navigating it) or open one.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/admin/team-messages';
    event.waitUntil((async () => {
        const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of all) {
            if (c.url.indexOf('/admin/') >= 0 && 'focus' in c) {
                await c.focus();
                if (c.navigate) { try { await c.navigate(url); } catch (e) {} }
                return;
            }
        }
        if (self.clients.openWindow) return self.clients.openWindow(url);
    })());
});

self.addEventListener('message', (event) => { if (event.data === 'SKIP_WAITING') self.skipWaiting(); });
