<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') - Credit Repair</title>
    <link rel="stylesheet" href="{{ asset('css/client.css') }}">
    @stack('head')
</head>
<body class="client-body">
<div class="layout">
    <div class="sidebar-scrim" id="sidebarScrim"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <strong>Credit Repair</strong>
            <span class="badge-portal">Client Portal</span>
        </div>
        @php
            $unread = Auth::guard('client')->user()?->unreadCountForClient() ?? 0;
        @endphp
        <nav class="sidebar-nav">
            <a href="{{ route('client.dashboard') }}" class="{{ request()->routeIs('client.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('client.end-users.index') }}" class="{{ request()->routeIs('client.end-users.*') ? 'active' : '' }}">My Clients</a>
            <a href="{{ route('client.messages.index') }}" class="{{ request()->routeIs('client.messages.*') ? 'active' : '' }}">
                Messages @if ($unread > 0)<span class="badge-portal" style="background:#dc2626;">{{ $unread }}</span>@endif
            </a>
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
