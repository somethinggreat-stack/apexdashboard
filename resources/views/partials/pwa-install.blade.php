{{-- PWA: service-worker registration for static-asset caching. Team layouts only.
     (The "Install desktop app" button was removed at the team's request.) --}}
<script>
(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/admin/' }).catch(function () {});
        });

        // Best-effort: clear caches on logout (caches hold only non-sensitive
        // static assets, but tidy is better).
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form && form.action && /\/logout(?:$|[/?])/.test(form.action)) {
                try {
                    if (navigator.serviceWorker.controller) {
                        navigator.serviceWorker.controller.postMessage('CLEAR_CACHES');
                    }
                } catch (err) {}
            }
        }, true);
    }
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

{{-- Team Chat desktop notifications — pings you about new messages / @mentions, anywhere in the app. --}}
<script>
(function () {
    'use strict';
    if (!('Notification' in window)) { return; }

    var POLL_URL = @json(route('admin.team-messages.notifications'));
    var OPEN_URL = @json(route('admin.team-messages.index'));
    var KEY = 'apex-team-last-msg';
    var POLL_MS = 15000;
    var lastId = parseInt(localStorage.getItem(KEY) || '0', 10) || 0;
    var primed = lastId > 0;   // if we have a baseline, notify new messages (no backlog spam)
    function store(){ try { localStorage.setItem(KEY, String(lastId)); } catch (e) {} }

    // A one-click prompt so notifications work without the user hunting for a click.
    var pill = null;
    function showPill(){
        if (pill || Notification.permission !== 'default') return;
        pill = document.createElement('button');
        pill.type = 'button';
        pill.textContent = '🔔 Turn on chat notifications';
        pill.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:6000;border:0;cursor:pointer;padding:11px 16px;border-radius:999px;font:600 13px system-ui,sans-serif;color:#fff;background:linear-gradient(135deg,#6366f1,#7c3aed);box-shadow:0 12px 28px rgba(99,102,241,.45);';
        pill.onclick = function(){ Notification.requestPermission().then(function(){ if (pill){ pill.remove(); pill = null; } }); };
        document.body.appendChild(pill);
    }
    if (Notification.permission === 'default') {
        showPill();
        var ask = function () { if (Notification.permission === 'default') Notification.requestPermission().then(function(){ if (pill && Notification.permission !== 'default'){ pill.remove(); pill = null; } }); };
        window.addEventListener('pointerdown', ask, { once: true });
    }

    function chime() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext; if (!Ctx) return;
            var ctx = new Ctx(), o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 920; g.gain.value = 0.04;
            o.connect(g); g.connect(ctx.destination); o.start();
            g.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.28); o.stop(ctx.currentTime + 0.3);
            setTimeout(function () { try { ctx.close(); } catch (e) {} }, 600);
        } catch (e) {}
    }

    function show(m) {
        if (Notification.permission !== 'granted') return;
        try {
            var n = new Notification(m.title, {
                body: (m.mention ? '@ ' : '') + m.sender + ': ' + m.snippet,
                icon: '/Images/pwa/icon-192.png', badge: '/Images/pwa/icon-192.png',
                tag: 'apex-team-' + m.conversation_id, renotify: true
            });
            n.onclick = function () { window.focus(); location.href = OPEN_URL + '?c=' + m.conversation_id; n.close(); };
        } catch (e) {}
    }

    function poll() {
        fetch(POLL_URL + '?after=' + lastId, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                if (!primed) { if (data.lastId) { lastId = data.lastId; store(); } primed = true; return; }  // first ever: baseline only
                var msgs = data.messages || [];
                if (msgs.length && Notification.permission === 'granted') {
                    msgs.forEach(show); chime();
                    if (data.lastId > lastId) { lastId = data.lastId; store(); }
                } else if (msgs.length) {
                    showPill();                       // hold the window; keep un-notified messages until permission is on
                } else if (data.lastId > lastId) {
                    lastId = data.lastId; store();
                }
            })
            .catch(function () {});
    }

    poll();
    var timer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        // Pause polling while the tab is hidden (saves CPU/network); resume on return.
        if (document.visibilityState === 'visible') { if (!timer) timer = setInterval(poll, POLL_MS); poll(); }
        else { clearInterval(timer); timer = null; }
    });
})();
</script>
@endif
