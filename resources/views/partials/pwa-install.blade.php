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
