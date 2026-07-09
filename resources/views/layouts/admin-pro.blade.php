{{--
    Super-admin console layout. Used only when the logged-in admin is a super
    admin (see Controller::adminView + the $adminLayout composer). VAs and leads
    agents keep layouts/admin.blade.php untouched.
--}}
@php
    $me  = Auth::guard('admin')->user();
    $bo  = $selectedClient ?? null;
    $ini = collect(explode(' ', trim($me?->full_name ?: 'Admin')))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Apex Growth Solutions</title>
    <link rel="stylesheet" href="{{ asset('css/admin-pro.css') }}">
    @stack('head')
</head>
<body class="pro-body admin-body">
<div class="pro-layout">

    <aside class="pro-sidebar" id="proSidebar">
        <div class="pro-brand">
            <img src="{{ asset('Images/whitelogo.png') }}" alt="Apex Growth Solutions">
        </div>

        @isset($selectedClient)
            <div class="pro-working">
                <svg class="pro-crown" width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M3 7l4.5 3.5L12 4l4.5 6.5L21 7l-1.8 11H4.8L3 7z"/>
                </svg>
                <div class="pro-working-label">Working On</div>
                <div class="pro-working-name">{{ $selectedClient->business_name }}</div>
                <form method="POST" action="{{ route('admin.client-selector.clear') }}">
                    @csrf
                    <button type="submit" class="pro-switch">
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
            <div class="pro-working">
                <div class="pro-working-label">No Business Owner</div>
                <div class="pro-working-name none">Nothing selected</div>
                <a href="{{ route('admin.client-selector.index') }}" class="pro-switch">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                    <span>Select Business Owner</span>
                </a>
            </div>
        @endisset

        <nav class="pro-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5z"/></svg>
                Dashboard
            </a>

            @isset($selectedClient)
                {{-- Separates the global Dashboard from the pages scoped to the
                     selected business owner, so the two aren't clicked by mistake. --}}
                <span class="pro-nav-label">Business Owner</span>

                @if ($selectedClient->intake_enabled)
                    @php $pendingIntake = \App\Models\EndUser::forClient($selectedClient->id)->where('intake_status', 'pending_review')->count(); @endphp
                    <a href="{{ route('admin.new-clients') }}" class="{{ request()->routeIs('admin.new-clients*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        New Clients
                        @if ($pendingIntake > 0)<span class="pro-count">{{ $pendingIntake }}</span>@endif
                    </a>
                @endif

                @php $errorCount = \App\Models\EndUser::forClient($selectedClient->id)->where('intake_status', 'error')->count(); @endphp
                <a href="{{ route('admin.errors') }}" class="{{ request()->routeIs('admin.errors') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Errors
                    @if ($errorCount > 0)<span class="pro-count">{{ $errorCount }}</span>@endif
                </a>

                <a href="{{ route('admin.end-users.index') }}" class="{{ request()->routeIs('admin.end-users.index') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    In Progress
                </a>

                <a href="{{ route('admin.client-list') }}" class="{{ request()->routeIs('admin.client-list') || request()->routeIs('admin.end-users.show') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Clients
                </a>

                @php $adminUnread = $selectedClient->unreadCountForAdmin(); @endphp
                <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Messages
                    @if ($adminUnread > 0)<span class="pro-count">{{ $adminUnread }}</span>@endif
                </a>

                <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Payments
                </a>
            @else
                @php
                    $isLeads   = request()->routeIs('admin.prospect-leads.index');
                    $isContact = request()->routeIs('admin.prospects.index');
                    $curCh     = request('channel', 'whatsapp');
                    $curType   = request()->route('type');
                    $dot = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3.2"/></svg>';
                @endphp

                <span class="pro-nav-label">Business Owners</span>
                <a href="{{ route('admin.client-selector.index') }}" class="{{ request()->routeIs('admin.client-selector.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    Business Owners
                </a>
                <a href="{{ route('admin.clients.index') }}" class="{{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Add/Remove Business Owners
                </a>

                <span class="pro-nav-label">New Leads</span>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'whatsapp']) }}" class="{{ $isLeads && $curCh === 'whatsapp' ? 'active' : '' }}">{!! $dot !!} WhatsApp Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'phone']) }}" class="{{ $isLeads && $curCh === 'phone' ? 'active' : '' }}">{!! $dot !!} Phone Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'instagram']) }}" class="{{ $isLeads && $curCh === 'instagram' ? 'active' : '' }}">{!! $dot !!} Instagram Leads</a>

                <span class="pro-nav-label">In Contact</span>
                <a href="{{ route('admin.prospects.index', ['channel' => 'whatsapp']) }}" class="{{ $isContact && $curCh === 'whatsapp' ? 'active' : '' }}">{!! $dot !!} WhatsApp Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'phone']) }}" class="{{ $isContact && $curCh === 'phone' ? 'active' : '' }}">{!! $dot !!} Phone Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'instagram']) }}" class="{{ $isContact && $curCh === 'instagram' ? 'active' : '' }}">{!! $dot !!} Instagram Leads in Contact</a>

                <span class="pro-nav-label">Pipeline</span>
                <a href="{{ route('admin.prospects.interested') }}" class="{{ request()->routeIs('admin.prospects.interested') ? 'active' : '' }}">{!! $dot !!} Interested Leads</a>
                <a href="{{ route('admin.prospects.lost') }}" class="{{ request()->routeIs('admin.prospects.lost') ? 'active' : '' }}">{!! $dot !!} Lost Leads</a>

                <span class="pro-nav-label">Website</span>
                <a href="{{ route('admin.leads.index') }}" class="{{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">{!! $dot !!} Website Forms Leads</a>

                <span class="pro-nav-label">Extra Projects</span>
                <a href="{{ route('admin.extra.index', 'funnel') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'funnel' ? 'active' : '' }}">{!! $dot !!} Funnels</a>
                <a href="{{ route('admin.extra.index', 'support') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'support' ? 'active' : '' }}">{!! $dot !!} Customer Support</a>
                <a href="{{ route('admin.extra.index', 'ads') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'ads' ? 'active' : '' }}">{!! $dot !!} Ads</a>

                <span class="pro-nav-label">Admin</span>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-4z"/></svg>
                    Users &amp; Activity
                </a>
            @endisset
        </nav>

        <div class="pro-user">
            <span class="pro-avatar" style="background:linear-gradient(140deg,#6366f1,#4f46e5); color:#fff;">{{ $ini }}</span>
            <div class="pro-user-text">
                <strong>{{ $me?->full_name ?: 'Admin' }}</strong>
                <span>Admin</span>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="pro-logout" title="Log out" aria-label="Log out">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </button>
            </form>
        </div>
    </aside>

    <main class="pro-main">
        <header class="pro-topbar">
            <div class="pro-heading">
                <h1>@yield('title', 'Dashboard')</h1>
                <p>@yield('subtitle', $bo ? 'Working on ' . $bo->business_name : 'Apex Growth Solutions')</p>
            </div>

            @isset($selectedClient)
                <form method="GET" action="{{ route('admin.client-list') }}" class="pro-topbar-search">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search clients by name...">
                </form>
            @endisset

            {{-- pages that ship their own topbar controls (e.g. the client
                 detail view's back / report buttons) --}}
            @hasSection('topbar-content')
                @yield('topbar-content')
            @endif

            @yield('topbar-action')
        </header>

        <div class="pro-content">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

<script src="{{ asset('js/admin.js') }}"></script>
<script src="{{ asset('js/galaxy-trail.js') }}" defer></script>
<script>
/* Rows with data-href are clickable, minus the real controls inside them. */
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
