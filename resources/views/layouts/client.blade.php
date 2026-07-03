<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') - Apex Growth Solutions</title>
    <link rel="stylesheet" href="{{ asset('css/client.css') }}">
    <style>
        :root { --agp-accent:#2563eb; --agp-accent2:#38bdf8; }
        .client-body {
            background:
                radial-gradient(1100px circle at 100% -5%, #e2ecff 0%, transparent 42%),
                radial-gradient(950px circle at -5% 108%, #e6fbf4 0%, transparent 44%),
                #eef2f7 !important;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #0b1f3a 0%, #103063 55%, #0b1f3a 100%) !important;
            border-right: 1px solid rgba(255,255,255,.06) !important;
        }
        .sidebar-brand strong { color:#fff !important; letter-spacing:.2px; }
        .sidebar-brand .badge-portal {
            background: linear-gradient(135deg, var(--agp-accent), var(--agp-accent2)) !important;
            color:#fff !important; border:0 !important;
        }
        .sidebar-nav a {
            color:#c7d2e2 !important; border-radius:11px; margin:3px 10px; padding:11px 14px;
            font-weight:600; position:relative; transition: background .15s, color .15s, box-shadow .15s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,.08) !important; color:#fff !important; }
        .sidebar-nav a.active {
            background: linear-gradient(135deg, rgba(37,99,235,.95), rgba(56,189,248,.8)) !important;
            color:#fff !important; box-shadow:0 8px 20px rgba(37,99,235,.35);
        }
        .sidebar-nav a.active::before {
            content:''; position:absolute; left:-10px; top:9px; bottom:9px; width:4px; border-radius:4px; background:#38bdf8;
        }

        /* Logout — proper button, clean red hover */
        .sidebar-logout { padding:14px 16px !important; }
        .sidebar-logout .btn-link {
            display:block; width:100%; text-align:center; text-decoration:none;
            padding:11px 14px; border-radius:11px; font-weight:700; font-size:14px; cursor:pointer;
            color:#fecaca !important; background: rgba(239,68,68,.12) !important; border:1px solid rgba(239,68,68,.4) !important;
            transition: background .15s, color .15s, border-color .15s;
        }
        .sidebar-logout .btn-link:hover { background:#dc2626 !important; color:#fff !important; border-color:#dc2626 !important; }

        /* Topbar */
        .topbar { background:#fff !important; border-bottom:1px solid #e6ebf2 !important; }
        .page-title { color:#0f172a !important; }
        .user-chip {
            background: linear-gradient(135deg, var(--agp-accent), var(--agp-accent2)) !important;
            color:#fff !important; border:0 !important; font-weight:700;
        }

        /* Stat cards */
        .stats-grid { gap:16px; }
        .stat-card {
            background:#fff; border:1px solid #e6ebf2; border-radius:16px; padding:20px 22px;
            box-shadow:0 6px 18px rgba(15,23,42,.05); position:relative; overflow:hidden;
            transition: transform .12s, box-shadow .12s;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px;
            background:linear-gradient(90deg, var(--agp-accent), var(--agp-accent2)); }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 12px 26px rgba(15,23,42,.10); }
        .stat-label { color:#64748b !important; font-size:12px; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
        .stat-value { color:#0f172a !important; font-size:30px; font-weight:800; margin-top:6px; }

        /* Cards */
        .card { background:#fff; border:1px solid #e6ebf2 !important; border-radius:16px !important; box-shadow:0 6px 18px rgba(15,23,42,.05); }

        /* Engaging accents — colours only */
        .welcome {
            background: linear-gradient(120deg, #1d4ed8, #0ea5e9 60%, #06b6d4);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, #2563eb, #38bdf8); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #059669, #34d399); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
        .stat-card:nth-child(1) .stat-value { color:#1d4ed8 !important; }
        .stat-card:nth-child(2) .stat-value { color:#047857 !important; }
        .stat-card:nth-child(3) .stat-value { color:#b45309 !important; }
        .stat-card:nth-child(4) .stat-value { color:#6d28d9 !important; }

        .data-table thead th { background:#f1f5f9 !important; color:#475569 !important; letter-spacing:.03em; }
        .pill-active { background:#d1fae5 !important; color:#065f46 !important; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; border:0 !important; }
        .btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af) !important; }
    </style>
    @stack('head')
</head>
<body class="client-body">
<div class="layout">
    <div class="sidebar-scrim" id="sidebarScrim"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <strong>Apex Growth Solutions</strong>
            <span class="badge-portal">Client Portal</span>
        </div>
        @php
            $unread = Auth::guard('client')->user()?->unreadCountForClient() ?? 0;
        @endphp
        <nav class="sidebar-nav">
            <a href="{{ route('client.dashboard') }}" class="{{ request()->routeIs('client.dashboard') ? 'active' : '' }}">Dashboard</a>
            @php $bo = Auth::guard('client')->user(); @endphp
            @if ($bo?->intake_enabled)
                @php $pendingIntake = \App\Models\EndUser::forClient($bo->id)->where('intake_status', 'pending_review')->count(); @endphp
                <a href="{{ route('client.new-clients') }}" class="{{ request()->routeIs('client.new-clients*') ? 'active' : '' }}">
                    New Clients @if ($pendingIntake > 0)<span class="badge-portal" style="background:#dc2626;">{{ $pendingIntake }}</span>@endif
                </a>
            @endif
            <a href="{{ route('client.end-users.index') }}" class="{{ request()->routeIs('client.end-users.*') ? 'active' : '' }}">My Clients</a>
            <a href="{{ route('client.messages.index') }}" class="{{ request()->routeIs('client.messages.*') ? 'active' : '' }}">
                Messages @if ($unread > 0)<span class="badge-portal" style="background:#dc2626;">{{ $unread }}</span>@endif
            </a>
            <a href="{{ route('client.billing.index') }}" class="{{ request()->routeIs('client.billing.*') ? 'active' : '' }}">Billing</a>
        </nav>
        <form method="POST" action="{{ route('client.logout') }}" class="sidebar-logout">
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
            <div class="user-chip">{{ Auth::guard('client')->user()?->business_name }}</div>
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
<script src="{{ asset('js/client.js') }}"></script>
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
</script>
@stack('scripts')
</body>
</html>
