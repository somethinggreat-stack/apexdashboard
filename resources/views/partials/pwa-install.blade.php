{{-- Service worker registration — Web Push only (the installable PWA was removed).
     Registered at root scope so it can receive push while any Apex tab is open or
     the browser is closed. Also unregisters the old '/admin/'-scoped caching worker. --}}
<script>
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
        // Retire the previous installable-PWA worker (scope /admin/) if it's still around.
        navigator.serviceWorker.getRegistrations().then(function (regs) {
            regs.forEach(function (r) {
                if (r.scope && r.scope.indexOf('/admin/') !== -1 && r.scope.indexOf('/admin/') === r.scope.length - 7) {
                    r.unregister().catch(function () {});
                }
            });
        }).catch(function () {});
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
})();
</script>

@php $me = Auth::guard('admin')->user(); @endphp
@if ($me && in_array($me->role, ['super', 'va'], true))
{{-- Desktop notifications: raise a native toast when a new client arrives.
     Works while the app is open (the team's whole shift). Fulfillment team only. --}}
<script>
(function () {
    'use strict';
    if (!('Notification' in window)) { return; }

    var POLL_URL  = @json(route('admin.pwa.new-clients-poll'));
    var OPEN_URL  = @json(route('admin.new-clients'));
    var KNOWN_KEY = 'apex-known-new-clients';
    var POLL_MS   = 45000;

    var known       = loadKnown();
    var startedEmpty = known.size === 0;
    var firstPoll    = true;

    function loadKnown() {
        try { return new Set(JSON.parse(localStorage.getItem(KNOWN_KEY) || '[]')); }
        catch (e) { return new Set(); }
    }
    function saveKnown() {
        try { localStorage.setItem(KNOWN_KEY, JSON.stringify(Array.from(known))); } catch (e) {}
    }

    // Browsers only allow a permission prompt from a user gesture.
    if (Notification.permission === 'default') {
        var ask = function () { try { Notification.requestPermission(); } catch (e) {} };
        window.addEventListener('pointerdown', ask, { once: true });
        window.addEventListener('keydown', ask, { once: true });
    }

    // A soft two-tone chime so a busy VA notices without it being jarring.
    function chime() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            var ctx = new Ctx(), o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.045;
            o.connect(g); g.connect(ctx.destination); o.start();
            o.frequency.setValueAtTime(1175, ctx.currentTime + 0.12);
            g.gain.setValueAtTime(0.045, ctx.currentTime + 0.22);
            g.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.32);
            o.stop(ctx.currentTime + 0.34);
            setTimeout(function () { try { ctx.close(); } catch (e) {} }, 700);
        } catch (e) {}
    }

    function notify(fresh) {
        if (Notification.permission !== 'granted') { return; }
        var title, body;
        if (fresh.length === 1) {
            title = 'New client 🎉';
            body  = fresh[0].name + (fresh[0].bo ? ' · ' + fresh[0].bo : '');
        } else {
            title = fresh.length + ' new clients 🎉';
            body  = 'Click to review them in New Clients.';
        }
        try {
            var n = new Notification(title, {
                body: body,
                icon: '/Images/pwa/icon-192.png',
                badge: '/Images/pwa/icon-192.png',
                tag: 'apex-new-clients',
                renotify: true
            });
            n.onclick = function () { window.focus(); location.href = OPEN_URL; n.close(); };
            chime();
        } catch (e) {}
    }

    function poll() {
        fetch(POLL_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.clients)) { return; }

                var fresh = [];
                data.clients.forEach(function (c) {
                    var id = String(c.id);
                    if (!known.has(id)) {
                        // Suppress the backlog on the very first run ever; after
                        // that (or on any later poll) genuinely new ones notify.
                        if (!(firstPoll && startedEmpty)) { fresh.push(c); }
                        known.add(id);
                    }
                });

                // Forget ids that are no longer pending, so a client moved back to
                // New Clients later can notify again.
                var current = new Set(data.clients.map(function (c) { return String(c.id); }));
                Array.from(known).forEach(function (id) { if (!current.has(id)) { known.delete(id); } });

                saveKnown();
                firstPoll = false;
                if (fresh.length) { notify(fresh); }
            })
            .catch(function () {});
    }

    poll();
    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { poll(); }
    });
})();
</script>

{{-- Team Chat global realtime layer (always-on polling + cross-tab sync + notification
     center + desktop notifications). Replaces the old pause-on-hidden notifier. --}}
@include('partials.team-realtime')
@endif
