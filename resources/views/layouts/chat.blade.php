<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Team Chat') · Apex</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root { --pro-surface:#fff; --pro-soft:#f4f6fb; --pro-line:#e6ebf2; --pro-text:#0f172a; color-scheme:light; }
        :root[data-theme="dark"] { --pro-surface:#0f1629; --pro-soft:#0b1120; --pro-line:#233150; --pro-text:#e2e8f0; color-scheme:dark; }
        * { box-sizing:border-box; }
        html, body { margin:0; height:100%; }
        body { font-family:ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:var(--pro-soft,#f4f6fb); color:var(--pro-text); }
        img { max-width:100%; }
        [hidden] { display:none !important; }
        .tc-standalone { height:100vh; padding:14px; }
        /* Fill the viewport (override the embedded calc height). */
        .tc-standalone .tc-wrap { height:100% !important; min-height:0 !important; }
        /* Tiny toast */
        #apexToast { position:fixed; left:50%; bottom:26px; transform:translateX(-50%); z-index:5000; display:flex; flex-direction:column; gap:8px; align-items:center; pointer-events:none; }
        #apexToast div { background:#0f172a; color:#fff; padding:10px 18px; border-radius:999px; font-size:13.5px; font-weight:600; box-shadow:0 10px 30px rgba(15,23,42,.35); opacity:0; transform:translateY(8px); transition:opacity .2s, transform .2s; }
        #apexToast div.in { opacity:1; transform:none; }
    </style>
    @stack('head')
</head>
<body>
    <div id="apexToast"></div>
    <div class="tc-standalone">
        @yield('content')
    </div>

    <script>
        // Respect a saved theme (matches the admin app's data-theme convention).
        (function () {
            try {
                var t = localStorage.getItem('apex-theme') || localStorage.getItem('theme');
                if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
                else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.setAttribute('data-theme', 'dark');
            } catch (e) {}
        })();
        // Minimal toast used across the chat UI.
        window.apexToast = function (msg) {
            var host = document.getElementById('apexToast'); if (!host) return;
            var el = document.createElement('div'); el.textContent = msg; host.appendChild(el);
            requestAnimationFrame(function () { el.classList.add('in'); });
            setTimeout(function () { el.classList.remove('in'); setTimeout(function () { el.remove(); }, 250); }, 2600);
        };
    </script>

    @include('partials.pwa-install')
    @stack('scripts')
</body>
</html>
