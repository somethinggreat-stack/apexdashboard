<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Set the theme before first paint so there's no flash of the wrong colour. --}}
    <script>(function(){try{var t=localStorage.getItem('apex-theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
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

        /* Sidebar — same hero image + indigo wash as the dashboard, so the VA
           console matches the super-admin one. Heavy overlay keeps nav legible. */
        .sidebar {
            background:
                linear-gradient(180deg, rgba(12,17,48,.95) 0%, rgba(20,26,62,.92) 55%, rgba(27,19,80,.91) 100%),
                #12163a url("{{ asset('Images/heroimage.png') }}") center/cover no-repeat;
        }
        /* WebP where supported (13x smaller); PNG above stays the fallback. */
        .sidebar {
            background:
                linear-gradient(180deg, rgba(12,17,48,.95) 0%, rgba(20,26,62,.92) 55%, rgba(27,19,80,.91) 100%),
                #12163a image-set(url("{{ asset('Images/heroimage.webp') }}") type("image/webp"), url("{{ asset('Images/heroimage.png') }}") type("image/png")) center/cover no-repeat;
        }

        /* Big logo at the top, with room beneath so the picker box starts lower
           — matches the super-admin sidebar. */
        .va-brand {
            flex-shrink:0; display:flex; align-items:center; justify-content:center;
            padding:20px 16px 26px; border-bottom:0;
        }
        .va-brand picture { display:contents; }   /* no extra box around the logo */
        .va-brand img {
            width:100%; max-width:216px; height:auto; max-height:68px;
            object-fit:contain; display:block;
        }

        /* Compact sidebar so every item fits on one screen (no inner scroll) */
        .sidebar { padding-top:10px; padding-bottom:8px; }
        .sidebar-working { margin:6px 12px 4px !important; padding:9px 11px !important; }
        .sw-label { margin-bottom:3px !important; font-size:9px !important; }
        .sw-name { font-size:13px !important; margin-bottom:8px !important; }
        .sidebar-working .sw-switch { padding:9px 12px !important; font-size:12.5px !important; }
        .sidebar-nav { padding:6px 0 !important; }
        .sidebar-nav a { padding:7.5px 20px !important; font-size:14.5px !important; line-height:1.25; }

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

        <div class="va-brand">
            <picture>
                <source srcset="{{ asset('Images/whitelogo.webp') }}" type="image/webp">
                <img src="{{ asset('Images/whitelogo.png') }}" alt="Apex Growth Solutions" decoding="async">
            </picture>
        </div>

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
        <div class="sidebar-logout" style="padding-bottom:2px;">
            <button type="button" class="theme-toggle-btn" data-theme-toggle aria-pressed="false" title="Toggle light / dark theme">
                <svg class="ico-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>
                <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v2M12 20.5v2M4.2 4.2l1.5 1.5M18.3 18.3l1.5 1.5M1.5 12h2M20.5 12h2M4.2 19.8l1.5-1.5M18.3 5.7l1.5-1.5"/></svg>
                <span class="theme-toggle-label">Dark mode</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-logout">
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

{{-- Walk-on animation: shown once, on the first page after login. pull() clears
     the flag so a refresh doesn't replay it. --}}
@if (session()->pull('walker_once', false))
    <div id="fatmanWalker" data-src="{{ asset('lottie/superfatmanwalk.json') }}" aria-hidden="true">
        <div class="fw-art"></div>
    </div>
    <script src="{{ asset('js/lottie-light.min.js') }}"></script>
    <script src="{{ asset('js/walker.js') }}" defer></script>
@endif

<script src="{{ asset('js/responsive-nav.js') }}"></script>
<script src="{{ asset('js/theme-toggle.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
<script>
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

</script>
@stack('scripts')
</body>
</html>
