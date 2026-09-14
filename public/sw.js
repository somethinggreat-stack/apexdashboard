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

// Presence-only fetch handler: required by some browsers for PWA installability. It caches
// NOTHING and never calls respondWith(), so every request is handled by the browser exactly
// as normal (authenticated HTML / client data is never stored).
self.addEventListener('fetch', () => { /* pass-through */ });

// Incoming push from the server → wake any open Apex tab to sync instantly, and show an
// OS notification unless the user is right now focused on that exact conversation.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) {}
    const title = data.title || 'Apex Team Chat';
    const body  = data.body  || 'New message';
    const url   = data.url   || '/admin/team-messages';
    const conv  = String(data.conv || '');

    event.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        // Nudge every open tab to poll immediately (instant in-app update, no waiting for the timer).
        wins.forEach((c) => { try { c.postMessage({ type: 'apex-push', conv: conv }); } catch (e) {} });

        // If a focused tab is already viewing this conversation, skip the OS toast (they can see it).
        const focusedHere = wins.some((c) => c.focused && conv && c.url.indexOf('c=' + conv) !== -1);
        if (focusedHere) return;

        return self.registration.showNotification(title, {
            body: body,
            tag: data.tag || 'apex-team-' + conv,
            renotify: true,
            icon: '/Images/pwa/icon-192.png',
            badge: '/Images/pwa/icon-192.png',
            // Desktop web push can't do an inline text reply (Android only), but an action
            // button that opens the chat is the closest to Teams' quick-reply.
            actions: [{ action: 'open', title: 'Open chat' }],
            data: { url: url }
        });
    })());
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
