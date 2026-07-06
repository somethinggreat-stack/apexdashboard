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
        .sidebar-logout { padding:14px 16px !important; }
        .sidebar-logout .btn-link {
            display:block; width:100%; text-align:center; text-decoration:none;
            padding:11px 14px; border-radius:11px; font-weight:700; font-size:14px; cursor:pointer;
            color:#fecaca !important; background: rgba(239,68,68,.12) !important; border:1px solid rgba(239,68,68,.4) !important;
            transition: background .15s, color .15s, border-color .15s;
        }
        .sidebar-logout .btn-link:hover { background:#dc2626 !important; color:#fff !important; border-color:#dc2626 !important; }
    </style>
    @stack('head')
</head>
<body class="admin-body">
<div class="layout">
    <div class="sidebar-scrim" id="sidebarScrim"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <strong>Apex Growth Solutions</strong>
            <span class="badge-portal">VA Admin</span>
        </div>

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

        <nav class="sidebar-nav">
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
                <a href="{{ route('admin.end-users.index') }}" class="{{ request()->routeIs('admin.end-users.*') ? 'active' : '' }}">Clients</a>
                @php $adminUnread = $selectedClient->unreadCountForAdmin(); @endphp
                <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                    Messages @if ($adminUnread > 0)<span class="badge-portal" style="background:#dc2626;">{{ $adminUnread }}</span>@endif
                </a>
                <a href="{{ route('admin.today-queue') }}" class="{{ request()->routeIs('admin.today-queue') ? 'active' : '' }}">Today's Queue</a>
                <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">Payments</a>
            @else
                @php
                    $isLeads   = request()->routeIs('admin.prospect-leads.index');
                    $isContact = request()->routeIs('admin.prospects.index');
                    $curCh     = request('channel', 'whatsapp');
                @endphp
                <a href="{{ route('admin.client-selector.index') }}" class="{{ request()->routeIs('admin.client-selector.*') ? 'active' : '' }}">Business Owners</a>
                <a href="{{ route('admin.clients.index') }}" class="{{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">Add/Remove Business Owners</a>

                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'whatsapp']) }}" class="{{ $isLeads && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'phone']) }}" class="{{ $isLeads && $curCh === 'phone' ? 'active' : '' }}">Phone Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'instagram']) }}" class="{{ $isLeads && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads</a>

                <a href="{{ route('admin.prospects.index', ['channel' => 'whatsapp']) }}" class="{{ $isContact && $curCh === 'whatsapp' ? 'active' : '' }}">WhatsApp Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'phone']) }}" class="{{ $isContact && $curCh === 'phone' ? 'active' : '' }}">Phone Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'instagram']) }}" class="{{ $isContact && $curCh === 'instagram' ? 'active' : '' }}">Instagram Leads in Contact</a>

                <a href="{{ route('admin.prospects.interested') }}" class="{{ request()->routeIs('admin.prospects.interested') ? 'active' : '' }}">Interested Leads</a>
                <a href="{{ route('admin.prospects.lost') }}" class="{{ request()->routeIs('admin.prospects.lost') ? 'active' : '' }}">Lost Leads</a>
                <a href="{{ route('admin.leads.index') }}" class="{{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">Website Forms Leads</a>
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
                <div class="user-chip">{{ Auth::guard('admin')->user()?->full_name }}</div>
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
</script>
@stack('scripts')
</body>
</html>
