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
        /* Match the admin console's flat light background. */
        .client-body { background: #f5f6fb !important; }

        /* Sidebar — same dark-indigo + credit-report image as the admin console. */
        .sidebar {
            background:
                linear-gradient(180deg, rgba(12,17,48,.94) 0%, rgba(20,26,62,.91) 55%, rgba(27,19,80,.90) 100%),
                #12163a url("{{ asset('Images/heroimage.png') }}") center/cover no-repeat !important;
            border-right: 0 !important;
        }
        /* WebP where supported (13x smaller); PNG above stays the fallback. */
        .sidebar {
            background:
                linear-gradient(180deg, rgba(12,17,48,.94) 0%, rgba(20,26,62,.91) 55%, rgba(27,19,80,.90) 100%),
                #12163a image-set(url("{{ asset('Images/heroimage.webp') }}") type("image/webp"), url("{{ asset('Images/heroimage.png') }}") type("image/png")) center/cover no-repeat !important;
        }
        .sidebar-brand { text-align:center; padding-top:6px; }
        .sidebar-brand picture { display:contents; }   /* no extra box around the logo */
        .sidebar-brand .brand-logo { max-width:150px; max-height:50px; width:auto; display:block; margin:0 auto 8px; }
        .sidebar-brand strong { color:#fff !important; letter-spacing:.2px; }
        .sidebar-brand .badge-portal {
            background: linear-gradient(135deg, var(--agp-accent), var(--agp-accent2)) !important;
            color:#fff !important; border:0 !important;
        }

        /* Nav rows — flat pills with icons, indigo-gradient active, like admin. */
        .sidebar-nav a {
            display:flex; align-items:center; gap:12px;
            margin:2px 12px; padding:9px 13px; border-radius:10px;
            color:#bcc5dd !important; font-size:15px; font-weight:500; line-height:1.2;
            text-decoration:none; white-space:nowrap; position:relative;
            transition: background .14s, color .14s;
        }
        .sidebar-nav a svg { width:21px; height:21px; flex-shrink:0; }
        .sidebar-nav a:hover { background: rgba(255,255,255,.055) !important; color:#e6ebf7 !important; }
        .sidebar-nav a.active {
            background: linear-gradient(135deg, #4f46e5, #4c3fd8) !important;
            color:#fff !important; font-weight:600; box-shadow:0 5px 16px rgba(79,70,229,.42);
        }
        .sidebar-nav a.active svg { color:#fff !important; }
        .sidebar-nav a.active::before { display:none !important; }

        /* icon tints, like the admin console */
        .sidebar-nav .i-dash { color:#a5b4fc; }
        .sidebar-nav .i-int  { color:#a78bfa; }
        .sidebar-nav .i-lost { color:#fb923c; }
        .sidebar-nav .i-sup  { color:#2dd4bf; }
        .sidebar-nav .i-adm  { color:#94a3b8; }
        .sidebar-nav .i-hold { color:#93a4c8; }
        .sidebar-nav .i-web  { color:#60a5fa; }
        .sidebar-nav .i-wa   { color:#34d399; }

        /* count pill, pushed to the far right of the row */
        .sidebar-nav .nav-count {
            margin-left:auto; min-width:20px; padding:2px 6px; border-radius:999px;
            background:#ef4444; color:#fff; font-size:10px; font-weight:700; text-align:center; line-height:1.4;
        }
        .sidebar-nav .nav-count-slate { background:#64748b; }
        .sidebar-nav a.active .nav-count { background: rgba(255,255,255,.22); }

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
            <picture>
                <source srcset="{{ asset('Images/whitelogo.webp') }}" type="image/webp">
                <img src="{{ asset('Images/whitelogo.png') }}" alt="Apex Growth Solutions" class="brand-logo" decoding="async" onerror="this.style.display='none'">
            </picture>
            <span class="badge-portal">Client Portal</span>
        </div>
        @php
            $unread = Auth::guard('client')->user()?->unreadCountForClient() ?? 0;
        @endphp
        <nav class="sidebar-nav">
            <a href="{{ route('client.dashboard') }}" class="{{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
                <svg class="i-dash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5z"/></svg>
                <span>Dashboard</span>
            </a>
            @php $bo = Auth::guard('client')->user(); @endphp
            @if ($bo?->intake_enabled)
                @php $pendingIntake = \App\Models\EndUser::forClient($bo->id)->notHeld()->where('intake_status', 'pending_review')->count(); @endphp
                <a href="{{ route('client.new-clients') }}" class="{{ request()->routeIs('client.new-clients*') ? 'active' : '' }}">
                    <svg class="i-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    <span>New Clients</span>
                    @if ($pendingIntake > 0)<span class="nav-count">{{ $pendingIntake }}</span>@endif
                </a>
            @endif
            @php $errorCount = \App\Models\EndUser::forClient($bo->id)->notHeld()->where('intake_status', 'error')->count(); @endphp
            <a href="{{ route('client.errors') }}" class="{{ request()->routeIs('client.errors') ? 'active' : '' }}">
                <svg class="i-lost" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
                <span>New Client Errors</span>
                @if ($errorCount > 0)<span class="nav-count">{{ $errorCount }}</span>@endif
            </a>
            <a href="{{ route('client.end-users.index') }}" class="{{ request()->routeIs('client.end-users.*') ? 'active' : '' }}">
                <svg class="i-sup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>In Progress</span>
            </a>
            <a href="{{ route('client.client-list') }}" class="{{ request()->routeIs('client.client-list') ? 'active' : '' }}">
                <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Done Clients</span>
            </a>
            @php $roundErrCount = \App\Models\EndUser::forClient($bo->id)->notHeld()->where('intake_status', 'round_error')->count(); @endphp
            <a href="{{ route('client.round-errors') }}" class="{{ request()->routeIs('client.round-errors') ? 'active' : '' }}">
                <svg class="i-lost" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12a9.5 9.5 0 1 0 2.8-6.7"/><polyline points="2.5 4 2.5 8 6.5 8"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="15.5" x2="12" y2="15.5"/></svg>
                <span>Round Errors</span>
                @if ($roundErrCount > 0)<span class="nav-count">{{ $roundErrCount }}</span>@endif
            </a>
            @php $holdCount = \App\Models\EndUser::forClient($bo->id)->onHold()->count(); @endphp
            <a href="{{ route('client.hold') }}" class="{{ request()->routeIs('client.hold') ? 'active' : '' }}">
                <svg class="i-hold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                <span>Hold/Pause</span>
                @if ($holdCount > 0)<span class="nav-count nav-count-slate">{{ $holdCount }}</span>@endif
            </a>

            {{-- Custom lists — only for owners with the feature on (Tycon Stan). --}}
            @if ($bo?->custom_lists_enabled)
                @foreach (\App\Models\EndUser::CUSTOM_LISTS as $lk => $ll)
                    @php $lc = \App\Models\EndUser::forClient($bo->id)->customList($lk)->count(); @endphp
                    <a href="{{ route('client.lists.show', $lk) }}" class="{{ request()->routeIs('client.lists.show') && request()->route('list') === $lk ? 'active' : '' }}">
                        <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg>
                        <span>{{ $ll }}</span>
                        @if ($lc > 0)<span class="nav-count nav-count-slate">{{ $lc }}</span>@endif
                    </a>
                @endforeach
            @endif
            <a href="{{ route('client.messages.index') }}" class="{{ request()->routeIs('client.messages.*') ? 'active' : '' }}">
                <svg class="i-web" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span>Messages</span>
                @if ($unread > 0)<span class="nav-count">{{ $unread }}</span>@endif
            </a>
            <a href="{{ route('client.billing.index') }}" class="{{ request()->routeIs('client.billing.*') ? 'active' : '' }}">
                <svg class="i-wa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Billing</span>
            </a>
            @if ($bo?->is_commission_referrer)
                <a href="{{ route('client.commissions.index') }}" class="{{ request()->routeIs('client.commissions.*') ? 'active' : '' }}">
                    <svg class="i-wa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/><path d="M20 9l1.5-1.5M20 15l1.5 1.5"/></svg>
                    <span>Commissions</span>
                </a>
            @endif
        </nav>
        <form method="POST" action="{{ route('client.logout') }}" class="sidebar-logout">
            @csrf
            <button type="submit" class="btn btn-link">Logout</button>
        </form>
    </aside>
    <main class="main">
        <header class="topbar">
            <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" data-drawer-toggle="#sidebar" data-drawer-scrim="#sidebarScrim" aria-label="Open menu" aria-controls="sidebar">
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
            <div class="toast-flash" data-toast="success">{{ session('status') }}</div>
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
<script src="{{ asset('js/responsive-nav.js') }}"></script>
<script src="{{ asset('js/toast.js') }}"></script>
<script src="{{ asset('js/client.js') }}"></script>
@stack('scripts')
</body>
</html>
