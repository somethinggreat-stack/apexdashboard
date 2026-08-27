{{-- PWA: service-worker registration + "Install desktop app" button.
     Team layouts only. The button appears only when the browser says the app
     is installable, and hides once it's installed / already running standalone. --}}
<style>
    #apexInstallBtn {
        position: fixed; right: 20px; bottom: 20px; z-index: 4000;
        display: none; align-items: center; gap: 9px;
        padding: 11px 18px; border: 0; border-radius: 999px; cursor: pointer;
        font-family: inherit; font-weight: 700; font-size: 13.5px; color: #fff;
        background: linear-gradient(135deg, #4f46e5, #38bdf8);
        box-shadow: 0 12px 28px rgba(79, 70, 229, .42);
        transition: transform .15s, box-shadow .15s, opacity .2s;
    }
    #apexInstallBtn:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(79, 70, 229, .55); }
    #apexInstallBtn svg { width: 17px; height: 17px; flex: none; }
    #apexInstallBtn .apex-install-x {
        margin-left: 4px; opacity: .8; font-size: 15px; line-height: 1;
        padding: 0 2px; border-radius: 6px;
    }
    #apexInstallBtn .apex-install-x:hover { opacity: 1; background: rgba(255,255,255,.18); }
    @media (prefers-reduced-motion: reduce) { #apexInstallBtn { transition: none; } }
</style>

<button id="apexInstallBtn" type="button" aria-label="Install the Apex desktop app">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>
    </svg>
    <span>Install desktop app</span>
    <span class="apex-install-x" role="button" aria-label="Dismiss" title="Not now">&times;</span>
</button>

<script>
(function () {
    'use strict';

    // Already running as an installed app? Never show the button.
    var standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true;

    // ---- Service worker (installability + static-asset caching) -------------
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

    // ---- Install prompt -----------------------------------------------------
    var btn = document.getElementById('apexInstallBtn');
    var deferred = null;
    var DISMISS_KEY = 'apex-install-dismissed';

    function dismissed() {
        try { return sessionStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; }
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        if (btn && !standalone && !dismissed()) { btn.style.display = 'inline-flex'; }
    });

    if (btn) {
        btn.addEventListener('click', function (e) {
            // The little "x" just dismisses for this session.
            if (e.target.classList.contains('apex-install-x')) {
                btn.style.display = 'none';
                try { sessionStorage.setItem(DISMISS_KEY, '1'); } catch (err) {}
                return;
            }
            if (!deferred) { return; }
            deferred.prompt();
            deferred.userChoice.finally(function () {
                deferred = null;
                btn.style.display = 'none';
            });
        });
    }

    window.addEventListener('appinstalled', function () {
        if (btn) { btn.style.display = 'none'; }
        deferred = null;
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
@endif
