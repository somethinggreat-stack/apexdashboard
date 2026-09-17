<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Team Chat') · Apex</title>
    {{-- /favicon.ico does not exist: the request would fall through to Laravel and the chat
         host would render the whole chat page just to answer it. --}}
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    {{-- Installable app — scoped to THIS page only (Team Chat), so the rest of the
         dashboard never shows an install prompt. --}}
    <link rel="manifest" href="/team-chat.webmanifest">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Team Chat">
    <link rel="apple-touch-icon" href="/Images/pwa/apple-touch-180.png">
    <style>
        :root { --pro-surface:#fff; --pro-soft:#f4f6fb; --pro-line:#e6ebf2; --pro-text:#0f172a; color-scheme:light; }
        :root[data-theme="dark"] { --pro-surface:#0f1629; --pro-soft:#0b1120; --pro-line:#233150; --pro-text:#e2e8f0; color-scheme:dark; }
        * { box-sizing:border-box; }
        html, body { margin:0; height:100%; }
        body { font-family:ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#ffffff; color:var(--pro-text); }
        img { max-width:100%; }
        [hidden] { display:none !important; }
        .tc-standalone { height:100vh; padding:14px; }
        /* Fill the viewport (override the embedded calc height). */
        .tc-standalone .tc-wrap { height:100% !important; min-height:0 !important; }
        /* Tiny toast */
        #apexToast { position:fixed; left:50%; bottom:26px; transform:translateX(-50%); z-index:5000; display:flex; flex-direction:column; gap:8px; align-items:center; pointer-events:none; }
        #apexToast div { background:#0f172a; color:#fff; padding:10px 18px; border-radius:999px; font-size:13.5px; font-weight:600; box-shadow:0 10px 30px rgba(15,23,42,.35); opacity:0; transform:translateY(8px); transition:opacity .2s, transform .2s; }
        #apexToast div.in { opacity:1; transform:none; }
        /* Install-app button — only shown when the browser says the chat is installable. */
        #tcInstallApp { position:fixed; left:20px; bottom:20px; z-index:6000; display:none; align-items:center; gap:8px; border:0; cursor:pointer;
            padding:11px 16px; border-radius:999px; font:600 13px system-ui,sans-serif; color:#fff; background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 12px 30px -8px rgba(99,102,241,.6); }
        #tcInstallApp.show { display:inline-flex; }
        #tcInstallApp svg { width:16px; height:16px; }
    </style>
    @stack('head')
</head>
<body>
    <div id="apexToast"></div>
    <button type="button" id="tcInstallApp" title="Install Team Chat as an app">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Install app
    </button>
    <div class="tc-standalone">
        @yield('content')
    </div>

    <script>
        // Team Chat follows light mode only — always a clean white app, regardless of
        // the operating system's dark-mode setting.
        document.documentElement.setAttribute('data-theme', 'light');
        // Minimal toast used across the chat UI.
        window.apexToast = function (msg) {
            var host = document.getElementById('apexToast'); if (!host) return;
            var el = document.createElement('div'); el.textContent = msg; host.appendChild(el);
            requestAnimationFrame(function () { el.classList.add('in'); });
            setTimeout(function () { el.classList.remove('in'); setTimeout(function () { el.remove(); }, 250); }, 2600);
        };
    </script>

    <script>
        // Installable Team Chat (scoped PWA). We keep the browser's default mini-infobar
        // suppressed and show our own tidy button instead — only on this page.
        (function () {
            var btn = document.getElementById('tcInstallApp');
            var deferred = null;
            function alreadyInstalled(){
                return window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
            }
            if (btn && !alreadyInstalled()) {
                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    deferred = e;
                    btn.classList.add('show');
                });
                btn.addEventListener('click', function () {
                    if (!deferred) { return; }
                    btn.classList.remove('show');
                    deferred.prompt();
                    deferred.userChoice.finally(function () { deferred = null; });
                });
                window.addEventListener('appinstalled', function () {
                    deferred = null; btn.classList.remove('show');
                    if (window.apexToast) window.apexToast('Team Chat installed 🎉');
                });
            }
        })();
    </script>

    @include('partials.pwa-install')
    @stack('scripts')
</body>
</html>
