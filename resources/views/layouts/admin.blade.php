<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Apex Growth Solutions</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>
        /* Logout — proper button, clean red hover */
        .sidebar-logout { padding:10px 16px !important; }
        .sidebar-logout .btn-link {
            display:block; width:100%; text-align:center; text-decoration:none;
            padding:9px 14px; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer;
            color:#fecaca !important; background: rgba(239,68,68,.12) !important; border:1px solid rgba(239,68,68,.4) !important;
            transition: background .15s, color .15s, border-color .15s;
        }
        .sidebar-logout .btn-link:hover { background:#dc2626 !important; color:#fff !important; border-color:#dc2626 !important; }

        /* Brand — logo removed on admin; keep just the portal badge, pulled up */
        .sidebar-brand { text-align:center; padding-top:0; padding-bottom:8px !important; border-bottom:0 !important; }
        .sidebar-brand .badge-portal { font-size:9.5px !important; padding:2px 9px !important; margin-top:0 !important; }
        .sidebar-brand .brand-logo {
            max-width:120px; max-height:40px; width:auto; display:block; margin:0 auto 6px;
            animation: brandIn .9s cubic-bezier(.2,.8,.2,1) both, brandGlow 4s ease-in-out 1s infinite;
            will-change: transform, filter;
        }
        .sidebar-brand .brand-logo:hover { animation-play-state: paused; transform: scale(1.05); transition: transform .25s ease; filter: drop-shadow(0 8px 22px rgba(56,189,248,.6)); }
        .sidebar-brand .badge-portal {
            background: linear-gradient(135deg,#2563eb,#38bdf8) !important; color:#fff !important; border:0 !important;
            letter-spacing:.05em;
        }
        @keyframes brandIn {
            0%   { opacity:0; transform: translateY(-10px) scale(.94); filter: drop-shadow(0 0 0 rgba(56,189,248,0)); }
            100% { opacity:1; transform: none; }
        }
        @keyframes brandGlow {
            0%,100% { filter: drop-shadow(0 4px 12px rgba(56,189,248,.22)); }
            50%     { filter: drop-shadow(0 8px 22px rgba(56,189,248,.55)); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sidebar-brand .brand-logo { animation: none; }
        }

        /* Sidebar section labels */
        .sidebar-nav .nav-label {
            display:block; font-size:9.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
            color:#8296b0; padding:11px 20px 4px; margin-top:3px; border-top:1px solid rgba(255,255,255,.09);
        }

        /* Compact sidebar so every item fits on one screen (no inner scroll) */
        .sidebar { padding-top:12px; padding-bottom:8px; }
        .sidebar-working { margin:6px 12px 4px !important; padding:9px 11px !important; }
        .sw-label { margin-bottom:3px !important; font-size:9px !important; }
        .sw-name { font-size:13px !important; margin-bottom:8px !important; }
        .sidebar-working .sw-switch { padding:9px 12px !important; font-size:12.5px !important; }
        .sidebar-nav { padding:6px 0 !important; }
        .sidebar-nav a { padding:6.5px 20px !important; font-size:12.5px !important; line-height:1.25; }

        /* Tighter, crisper density — real px sizes (NO zoom, so text stays sharp, no blur). */
        @media (min-width: 992px) {
            .main { padding:18px 26px; }
            .card { padding:16px; margin-bottom:16px; border-radius:10px; }
            .card-header { margin-bottom:14px; }
            .card-header h2 { font-size:17px; }
            .card-header h3 { font-size:16px; }
            .page-title { font-size:20px; }
            .data-table th, .data-table td { padding:8px 12px; }
            .data-table th { font-size:12px; }
            .stat-value { font-size:24px; }
        }

        /* 🌌 Galaxy cursor trail — a single decorative canvas over everything.
           pointer-events:none means it can never intercept a click. */
        #gxCanvas {
            position: fixed; inset: 0; width: 100%; height: 100%;
            z-index: 2147483000; pointer-events: none; user-select: none;
        }
        @media (prefers-reduced-motion: reduce) { #gxCanvas { display: none; } }
    </style>
    @stack('head')
</head>
<body class="admin-body">
<div class="layout">
    <div class="sidebar-scrim" id="sidebarScrim"></div>
    <aside class="sidebar" id="sidebar">
        @php
            $me        = Auth::guard('admin')->user();
            $isSuper   = $me?->isSuper();
            $roleLeads = $me?->isLeads();
        @endphp

        @unless ($roleLeads)
        @isset($selectedClient)
            <div class="sidebar-working">
                <div class="sw-label">Working On</div>
                <div class="sw-name">{{ $selectedClient->business_name }}</div>
                <form method="POST" action="{{ route('admin.client-selector.clear') }}">
                    @csrf
                    <button type="submit" class="sw-switch">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="17 1 21 5 17 9"></polyline>
                            <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                            <polyline points="7 23 3 19 7 15"></polyline>
                            <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                        </svg>
                        <span>Switch Business Owner</span>
                    </button>
                </form>
            </div>
        @else
            <div class="sidebar-working sidebar-working--none">
                <div class="sw-label">No Business Owner Selected</div>
                <a href="{{ route('admin.client-selector.index') }}" class="sw-switch">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                    <span>Select Business Owner</span>
                </a>
            </div>
        @endisset
        @endunless

        <nav class="sidebar-nav">
            @if ($isSuper)
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            @endif
            @isset($selectedClient)
                @if ($selectedClient->intake_enabled)
                    @php $pendingIntake = \App\Models\EndUser::forClient($selectedClient->id)->where('intake_status', 'pending_review')->count(); @endphp
                    <a href="{{ route('admin.new-clients') }}" class="{{ request()->routeIs('admin.new-clients*') ? 'active' : '' }}">
                        New Clients @if ($pendingIntake > 0)<span class="badge-portal" style="background:#dc2626;">{{ $pendingIntake }}</span>@endif
                    </a>
                @endif
                @php $errorCount = \App\Models\EndUser::forClient($selectedClient->id)->where('intake_status', 'error')->count(); @endphp
                <a href="{{ route('admin.errors') }}" class="{{ request()->routeIs('admin.errors') ? 'active' : '' }}">
                    Errors @if ($errorCount > 0)<span class="badge-portal" style="background:#dc2626;">{{ $errorCount }}</span>@endif
                </a>
                <a href="{{ route('admin.end-users.index') }}" class="{{ request()->routeIs('admin.end-users.index') ? 'active' : '' }}">In Progress</a>
                <a href="{{ route('admin.client-list') }}" class="{{ request()->routeIs('admin.client-list') || request()->routeIs('admin.end-users.show') ? 'active' : '' }}">Clients</a>
                @php $adminUnread = $selectedClient->unreadCountForAdmin(); @endphp
                <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                    Messages @if ($adminUnread > 0)<span class="badge-portal" style="background:#dc2626;">{{ $adminUnread }}</span>@endif
                </a>
                <a href="{{ route('admin.today-queue') }}" class="{{ request()->routeIs('admin.today-queue') ? 'active' : '' }}">Today's Queue</a>
                @if ($isSuper)
                    <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">Payments</a>
                @endif
            @else
                @php
                    $isLeads   = request()->routeIs('admin.prospect-leads.index');
                    $isContact = request()->routeIs('admin.prospects.index');
                    $curCh     = request('channel', 'whatsapp');
                    $curType   = request()->route('type');
                @endphp
                @if ($roleLeads)
                    {{-- Leads agent: sales pipeline only, nothing else --}}
                    <span class="nav-label">New Leads</span>
                    <a href="{{ route('admin.prospect-leads.index', ['channel' => 'whatsapp']) }}" class="{{ $isLeads && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads</a>
                    <a href="{{ route('admin.prospect-leads.index', ['channel' => 'phone']) }}" class="{{ $isLeads && $curCh === 'phone' ? 'active' : '' }}">Phone Leads</a>
                    <a href="{{ route('admin.prospect-leads.index', ['channel' => 'instagram']) }}" class="{{ $isLeads && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads</a>

                    <span class="nav-label">In Contact</span>
                    <a href="{{ route('admin.prospects.index', ['channel' => 'whatsapp']) }}" class="{{ $isContact && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads in Contact</a>
                    <a href="{{ route('admin.prospects.index', ['channel' => 'phone']) }}" class="{{ $isContact && $curCh === 'phone' ? 'active' : '' }}">Phone Leads in Contact</a>
                    <a href="{{ route('admin.prospects.index', ['channel' => 'instagram']) }}" class="{{ $isContact && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads in Contact</a>

                    <span class="nav-label">Pipeline</span>
                    <a href="{{ route('admin.prospects.interested') }}" class="{{ request()->routeIs('admin.prospects.interested') ? 'active' : '' }}">Interested Leads</a>
                    <a href="{{ route('admin.prospects.lost') }}" class="{{ request()->routeIs('admin.prospects.lost') ? 'active' : '' }}">Lost Leads</a>
                @else
                    <a href="{{ route('admin.client-selector.index') }}" class="{{ request()->routeIs('admin.client-selector.*') ? 'active' : '' }}">Business Owners</a>
                    @if ($isSuper)
                        <a href="{{ route('admin.clients.index') }}" class="{{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">Add/Remove Business Owners</a>

                        <span class="nav-label">New Leads</span>
                        <a href="{{ route('admin.prospect-leads.index', ['channel' => 'whatsapp']) }}" class="{{ $isLeads && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads</a>
                        <a href="{{ route('admin.prospect-leads.index', ['channel' => 'phone']) }}" class="{{ $isLeads && $curCh === 'phone' ? 'active' : '' }}">Phone Leads</a>
                        <a href="{{ route('admin.prospect-leads.index', ['channel' => 'instagram']) }}" class="{{ $isLeads && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads</a>

                        <span class="nav-label">In Contact</span>
                        <a href="{{ route('admin.prospects.index', ['channel' => 'whatsapp']) }}" class="{{ $isContact && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads in Contact</a>
                        <a href="{{ route('admin.prospects.index', ['channel' => 'phone']) }}" class="{{ $isContact && $curCh === 'phone' ? 'active' : '' }}">Phone Leads in Contact</a>
                        <a href="{{ route('admin.prospects.index', ['channel' => 'instagram']) }}" class="{{ $isContact && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads in Contact</a>

                        <span class="nav-label">Pipeline</span>
                        <a href="{{ route('admin.prospects.interested') }}" class="{{ request()->routeIs('admin.prospects.interested') ? 'active' : '' }}">Interested Leads</a>
                        <a href="{{ route('admin.prospects.lost') }}" class="{{ request()->routeIs('admin.prospects.lost') ? 'active' : '' }}">Lost Leads</a>

                        <span class="nav-label">Website</span>
                        <a href="{{ route('admin.leads.index') }}" class="{{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">Website Forms Leads</a>

                        <span class="nav-label">Extra Projects</span>
                        <a href="{{ route('admin.extra.index', 'funnel') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'funnel' ? 'active' : '' }}">Funnels</a>
                        <a href="{{ route('admin.extra.index', 'support') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'support' ? 'active' : '' }}">Customer Support</a>
                        <a href="{{ route('admin.extra.index', 'ads') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'ads' ? 'active' : '' }}">Ads</a>

                        <span class="nav-label">Admin</span>
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users &amp; Activity</a>
                    @endif
                @endif
            @endisset
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-logout">
            @csrf
            <button type="submit" class="btn btn-link">Logout</button>
        </form>
    </aside>
    <main class="main">
        <header class="topbar">
            <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open menu" aria-controls="sidebar">
                <span></span>
            </button>
            @hasSection('topbar-content')
                @yield('topbar-content')
            @else
                <h1 class="page-title">@yield('title', 'Dashboard')</h1>
            @endif
            <div class="topbar-right">
                @isset($selectedClient)
                    <span class="working-on">
                        Working on: <strong>{{ $selectedClient->business_name }}</strong>
                        <form method="POST" action="{{ route('admin.client-selector.clear') }}" class="working-on-switch">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm">Switch</button>
                        </form>
                    </span>
                @endisset
            </div>
        </header>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="content">
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('js/admin.js') }}"></script>
<script>
(function () {
    var toggle = document.getElementById('mobileMenuToggle');
    var sidebar = document.getElementById('sidebar');
    var scrim = document.getElementById('sidebarScrim');
    if (!toggle || !sidebar || !scrim) return;
    function open()  { sidebar.classList.add('open'); scrim.classList.add('open'); }
    function close() { sidebar.classList.remove('open'); scrim.classList.remove('open'); }
    toggle.addEventListener('click', function () {
        sidebar.classList.contains('open') ? close() : open();
    });
    scrim.addEventListener('click', close);
    sidebar.addEventListener('click', function (e) {
        if (e.target.closest('a')) close();
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) close();
    });
})();

/* Make any <tr data-href> fully clickable, while leaving real
   interactive controls (links, buttons, forms, inputs) working. */
(function () {
    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row) return;
        if (e.target.closest('a, button, form, input, select, textarea, label')) return;
        var url = row.getAttribute('data-href');
        if (e.metaKey || e.ctrlKey) { window.open(url, '_blank'); }
        else { window.location = url; }
    });
})();

/* 🌌 Galaxy cursor trail — a canvas particle system: a bright comet head, a
   dense stream of star dust that curls into a nebula ribbon, and the odd
   streaking star. Purely decorative: the canvas is pointer-events:none, the
   render loop parks itself when the cursor is still, and the whole thing is
   skipped for prefers-reduced-motion. Coordinates compensate for html{zoom}. */
(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var canvas = document.createElement('canvas');
    canvas.id = 'gxCanvas';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');

    /* deep-space palette: violet → indigo → blue → cyan, with magenta accents */
    var COLORS = [
        [139,  92, 246], [124,  58, 237], [ 99, 102, 241],
        [ 79,  70, 229], [ 59, 130, 246], [ 34, 211, 238],
        [232,  62, 189], [192, 132, 252]
    ];
    var MAX = 1000;
    var parts = [];
    var lx = 0, ly = 0, hx = 0, hy = 0, headA = 0, primed = false;
    var running = false, idle = 0;
    var W = 0, H = 0;

    function rnd(a, b) { return a + Math.random() * (b - a); }

    /* Pre-rendered sprites: drawing a gradient per particle per frame is far too
       slow, so each colour gets one glow sprite and one star sprite, blitted. */
    function sprite(draw) {
        var c = document.createElement('canvas');
        c.width = c.height = 64;
        draw(c.getContext('2d'));
        return c;
    }
    function rgba(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }

    var GLOW = COLORS.map(function (c) {
        return sprite(function (g) {
            var grd = g.createRadialGradient(32, 32, 0, 32, 32, 32);
            grd.addColorStop(0,    rgba(c, 1));
            grd.addColorStop(0.18, rgba(c, 0.72));
            grd.addColorStop(0.45, rgba(c, 0.22));
            grd.addColorStop(1,    rgba(c, 0));
            g.fillStyle = grd;
            g.fillRect(0, 0, 64, 64);
        });
    });
    var STAR = COLORS.map(function (c) {
        return sprite(function (g) {
            var grd = g.createRadialGradient(32, 32, 0, 32, 32, 12);
            grd.addColorStop(0, rgba(c, 1));
            grd.addColorStop(1, rgba(c, 0));
            g.fillStyle = grd;
            g.fillRect(0, 0, 64, 64);
            g.fillStyle = rgba(c, 0.85);          // 4-point flare
            g.fillRect(31.2, 4, 1.6, 56);
            g.fillRect(4, 31.2, 56, 1.6);
        });
    });

    function zoom() {
        var z = parseFloat(getComputedStyle(document.documentElement).zoom);
        return (z && z > 0.1) ? z : 1;
    }
    function resize() {
        var z = zoom(), dpr = Math.min(window.devicePixelRatio || 1, 2), s = dpr * z;
        W = document.documentElement.clientWidth;
        H = document.documentElement.clientHeight;
        canvas.width  = Math.max(1, Math.round(W * s));
        canvas.height = Math.max(1, Math.round(H * s));
        ctx.setTransform(s, 0, 0, s, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize, { passive: true });

    function push(x, y, vx, vy, kind) {
        if (parts.length >= MAX) parts.shift();
        var i = (Math.random() * COLORS.length) | 0;
        var life = kind === 'dust' ? rnd(55, 105) : (kind === 'star' ? rnd(20, 34) : rnd(30, 60));
        parts.push({
            x: x, y: y, px: x, py: y, vx: vx, vy: vy,
            life: life, max: life,
            size: kind === 'dust' ? rnd(0.8, 2.4) : (kind === 'star' ? rnd(2.2, 4) : rnd(3, 8)),
            w: rnd(-0.055, 0.055),                // per-frame curl → spiral arms
            img: kind === 'star' ? STAR[i] : GLOW[i],
            rgb: COLORS[i],
            streak: kind === 'star'
        });
    }

    /* Emit along the segment the cursor travelled, so fast moves stay continuous
       instead of leaving gaps between frames. */
    function emit(x0, y0, x1, y1) {
        var dx = x1 - x0, dy = y1 - y0;
        var dist = Math.sqrt(dx * dx + dy * dy) || 1;
        var ux = dx / dist, uy = dy / dist;      // direction of travel
        var nx = -uy, ny = ux;                   // perpendicular
        var steps = Math.min(6, Math.ceil(dist / 8));
        var speed = Math.min(dist, 40);

        for (var i = 0; i < steps; i++) {
            var t = i / steps;
            var x = x0 + dx * t, y = y0 + dy * t;
            var back = 0.02 + speed * 0.012;     // trail streams away from the cursor

            push(x + rnd(-2, 2), y + rnd(-2, 2),
                 -ux * rnd(0.3, 1.5) * back * 8 + nx * rnd(-1.1, 1.1),
                 -uy * rnd(0.3, 1.5) * back * 8 + ny * rnd(-1.1, 1.1), 'core');

            var dustN = Math.random() < 0.5 ? 2 : 1;
            for (var d = 0; d < dustN; d++) {
                push(x + rnd(-7, 7), y + rnd(-7, 7),
                     -ux * rnd(0, 0.7) * back * 6 + nx * rnd(-2.4, 2.4) * 0.7,
                     -uy * rnd(0, 0.7) * back * 6 + ny * rnd(-2.4, 2.4) * 0.7, 'dust');
            }
            if (Math.random() < 0.09) {
                push(x, y, -ux * rnd(1.5, 3.6), -uy * rnd(1.5, 3.6), 'star');
            }
        }
    }

    function frame() {
        ctx.clearRect(0, 0, W, H);

        for (var i = parts.length - 1; i >= 0; i--) {
            var p = parts[i];
            p.life--;
            if (p.life <= 0) { parts.splice(i, 1); continue; }

            p.px = p.x; p.py = p.y;
            var cs = Math.cos(p.w), sn = Math.sin(p.w);   // rotate velocity → curl
            var vx = p.vx * cs - p.vy * sn;
            p.vy = p.vx * sn + p.vy * cs;
            p.vx = vx;
            p.vx *= 0.968; p.vy *= 0.968;
            p.vy -= 0.014;                                 // slow drift upward
            p.x += p.vx; p.y += p.vy;

            var t = p.life / p.max;                        // 1 → 0
            var a = Math.min(1, (1 - t) * 7) * t;          // quick fade in, long fade out
            var s = p.size * (0.55 + 0.45 * t);

            if (p.streak) {
                ctx.strokeStyle = rgba(p.rgb, a * 0.5);
                ctx.lineWidth = s * 0.4;
                ctx.beginPath();
                ctx.moveTo(p.px, p.py);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            }
            ctx.globalAlpha = a;
            ctx.drawImage(p.img, p.x - s * 2, p.y - s * 2, s * 4, s * 4);
        }

        /* comet head: a hot core riding the cursor, fading out when it stops */
        if (headA > 0.01) {
            ctx.globalAlpha = headA;
            ctx.drawImage(GLOW[0], hx - 26, hy - 26, 52, 52);
            ctx.globalAlpha = headA * 0.9;
            ctx.drawImage(STAR[5], hx - 13, hy - 13, 26, 26);
            headA *= 0.9;
        }
        ctx.globalAlpha = 1;

        if (parts.length === 0 && ++idle > 60) { running = false; return; }
        requestAnimationFrame(frame);
    }

    document.addEventListener('mousemove', function (e) {
        var z = zoom();
        var x = e.clientX / z, y = e.clientY / z;

        hx = x; hy = y; headA = 1; idle = 0;

        if (!primed) { primed = true; lx = x; ly = y; }
        var dx = x - lx, dy = y - ly;
        if (dx * dx + dy * dy >= 4) { emit(lx, ly, x, y); lx = x; ly = y; }

        if (!running) { running = true; requestAnimationFrame(frame); }
    }, { passive: true });
})();
</script>
@stack('scripts')
</body>
</html>
