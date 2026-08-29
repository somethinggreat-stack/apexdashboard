{{--
    Super-admin console layout. Used only when the logged-in admin is a super
    admin (see Controller::adminView + the $adminLayout composer). VAs and leads
    agents keep layouts/admin.blade.php untouched.
--}}
@php
    $me      = Auth::guard('admin')->user();
    $isSuper = $me?->isSuper();
    $bo      = $selectedClient ?? null;
    $ini = collect(explode(' ', trim($me?->full_name ?: 'Admin')))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/Images/logo.png">
    <link rel="apple-touch-icon" href="/Images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Set the theme before first paint so there's no flash of the wrong colour. --}}
    <script>(function(){try{var t=localStorage.getItem('apex-theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <title>@yield('title', 'Admin') - Apex Growth Solutions</title>
    {{-- admin.css first (shared base + tokens), then the pro skin. Both are
         version-stamped so a deploy always busts the browser cache — the pro
         sheet used to @import admin.css unversioned, which cached it stale. --}}
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) ?: '1' }}">
    <link rel="stylesheet" href="{{ asset('css/admin-pro.css') }}?v={{ @filemtime(public_path('css/admin-pro.css')) ?: '1' }}">
    <link rel="stylesheet" href="{{ asset('css/galaxy.css') }}?v={{ @filemtime(public_path('css/galaxy.css')) ?: '1' }}">
    @include('partials.pwa-head')
    @stack('head')
</head>
<body class="pro-body admin-body">
<div class="sky" aria-hidden="true"></div>
<div class="pro-layout">

    {{-- Backdrop behind the off-canvas sidebar on tablet/mobile. --}}
    <div class="pro-scrim" id="proScrim" aria-hidden="true"></div>

    <aside class="pro-sidebar" id="proSidebar">
        <div class="pro-brand">
            <picture>
                <source srcset="{{ asset('Images/whitelogo.webp') }}" type="image/webp">
                <img src="{{ asset('Images/whitelogo.png') }}" alt="Apex Growth Solutions" decoding="async">
            </picture>
        </div>

        @isset($selectedClient)
            <div class="pro-working">
                <svg class="pro-crown" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M3 7l4.5 3.5L12 4l4.5 6.5L21 7l-1.8 11H4.8L3 7z"/>
                </svg>
                <div class="pro-working-label">Working On</div>
                <div class="pro-working-name">{{ $selectedClient->business_name }}</div>
                <form method="POST" action="{{ route('admin.client-selector.clear') }}">
                    @csrf
                    <button type="submit" class="pro-switch">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                    </svg>
                    <span>Select Business Owner</span>
                </a>
            </div>
        @endisset

        <nav class="pro-nav">
            @if ($isSuper)
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="i-dash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5z"/></svg>
                Dashboard
            </a>

            <span class="pro-rule" aria-hidden="true"></span>
            @endif

            @isset($selectedClient)
                @php $nav = $selectedClient->navCounts(); @endphp
                <a href="{{ route('admin.tasks') }}" class="{{ request()->routeIs('admin.tasks') ? 'active' : '' }}">
                    <svg class="i-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Tasks View
                </a>
                @if ($selectedClient->intake_enabled)
                    <a href="{{ route('admin.new-clients') }}" class="{{ request()->routeIs('admin.new-clients*') ? 'active' : '' }}">
                        <svg class="i-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        New Clients
                        @if ($nav['pending'] > 0)<span class="pro-count">{{ $nav['pending'] }}</span>@endif
                    </a>
                @endif

                <a href="{{ route('admin.errors') }}" class="{{ request()->routeIs('admin.errors') ? 'active' : '' }}">
                    <svg class="i-lost" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
                    New Client Errors
                    @if ($nav['errors'] > 0)<span class="pro-count">{{ $nav['errors'] }}</span>@endif
                </a>

                <a href="{{ route('admin.errors-resolved-new') }}" class="{{ request()->routeIs('admin.errors-resolved-new') ? 'active' : '' }}">
                    <svg class="i-sup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span class="pro-nav-label">Resolved by BO — New Clients</span>
                    @if ($nav['new_errors_resolved'] > 0)<span class="pro-count">{{ $nav['new_errors_resolved'] }}</span>@endif
                </a>

                <a href="{{ route('admin.end-users.index') }}" class="{{ request()->routeIs('admin.end-users.index') ? 'active' : '' }}">
                    <svg class="i-sup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    In Progress
                    @if ($nav['in_progress'] > 0)<span class="pro-count pro-count-soft">{{ $nav['in_progress'] }}</span>@endif
                </a>

                <a href="{{ route('admin.client-list') }}" class="{{ request()->routeIs('admin.client-list') || request()->routeIs('admin.end-users.show') ? 'active' : '' }}">
                    <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Clients
                </a>

                <a href="{{ route('admin.round-errors') }}" class="{{ request()->routeIs('admin.round-errors') ? 'active' : '' }}">
                    <svg class="i-lost" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12a9.5 9.5 0 1 0 2.8-6.7"/><polyline points="2.5 4 2.5 8 6.5 8"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="15.5" x2="12" y2="15.5"/></svg>
                    Round Errors
                    @if ($nav['round_errors'] > 0)<span class="pro-count">{{ $nav['round_errors'] }}</span>@endif
                </a>

                <a href="{{ route('admin.errors-resolved') }}" class="{{ request()->routeIs('admin.errors-resolved') ? 'active' : '' }}">
                    <svg class="i-sup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span class="pro-nav-label">Resolved by BO — Next Round</span>
                    @if ($nav['resolved_by_client'] > 0)<span class="pro-count">{{ $nav['resolved_by_client'] }}</span>@endif
                </a>

                <a href="{{ route('admin.hold') }}" class="{{ request()->routeIs('admin.hold') ? 'active' : '' }}">
                    <svg class="i-hold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                    Hold/Pause
                    @if ($nav['hold'] > 0)<span class="pro-count">{{ $nav['hold'] }}</span>@endif
                </a>

                <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                    <svg class="i-web" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Messages
                    @if ($nav['unread'] > 0)<span class="pro-count">{{ $nav['unread'] }}</span>@endif
                </a>

                @if ($isSuper)
                <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                    <svg class="i-wa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Payments
                </a>
                @endif

                {{-- Results reports — only for a results-tracking owner (Clinecea) --}}
                @if ($selectedClient->resultsTrackingEnabled())
                <a href="{{ route('admin.results.eod') }}" class="{{ request()->routeIs('admin.results.eod') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>
                    EOD Report
                </a>
                <a href="{{ route('admin.results.monthly') }}" class="{{ request()->routeIs('admin.results.monthly') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Monthly Results
                </a>
                @endif
            @else
                @php
                    $isLeads   = request()->routeIs('admin.prospect-leads.index');
                    $isContact = request()->routeIs('admin.prospects.index');
                    $curCh     = request('channel', 'whatsapp');
                    $curType   = request()->route('type');

                    $icoWa = '<svg class="i-wa" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.1 14.1c-.2.6-1.2 1.1-1.7 1.2-.5.1-1 .1-1.7-.1a12 12 0 0 1-5.7-4.9c-.4-.6-.9-1.6-.9-2.5s.5-1.4.7-1.6a.9.9 0 0 1 .6-.3h.5c.2 0 .4 0 .6.5l.7 1.7c.1.2 0 .4-.1.5l-.3.4c-.1.2-.3.3-.1.6a8 8 0 0 0 3.5 3c.3.1.4.1.6-.1l.7-.8c.2-.2.3-.2.6-.1l1.6.8c.3.1.4.2.5.3s.1.6-.1 1.2z"/></svg>';
                    $icoPh = '<svg class="i-ph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
                    $icoIg = '<svg class="i-ig" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>';
                @endphp

                <a href="{{ route('admin.client-selector.index') }}" class="{{ request()->routeIs('admin.client-selector.*') ? 'active' : '' }}">
                    <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    Business Owners
                </a>

                @if (Auth::guard('admin')->user()?->isVa())
                <a href="{{ route('admin.universal-search') }}" class="{{ request()->routeIs('admin.universal-search') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                    Universal Search
                </a>
                <a href="{{ route('admin.daily-task') }}" class="{{ request()->routeIs('admin.daily-task') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Daily Task
                </a>
                <a href="{{ route('admin.cfpb-logins') }}" class="{{ request()->routeIs('admin.cfpb-logins') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    CFPB Logins
                </a>
                <a href="{{ route('admin.profile.edit') }}" class="{{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profile
                </a>
                @endif

                @if ($isSuper)
                <a href="{{ route('admin.clients.index') }}" class="{{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
                    <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Add/Remove Business Owners
                </a>

                <a href="{{ route('admin.universal-search') }}" class="{{ request()->routeIs('admin.universal-search') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                    Universal Search
                </a>

                <a href="{{ route('admin.daily-task') }}" class="{{ request()->routeIs('admin.daily-task') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Daily Task
                </a>
                <a href="{{ route('admin.cfpb-logins') }}" class="{{ request()->routeIs('admin.cfpb-logins') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    CFPB Logins
                </a>
                <a href="{{ route('admin.profile.edit') }}" class="{{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profile
                </a>

                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'whatsapp']) }}" class="{{ $isLeads && $curCh === 'whatsapp' ? 'active' : '' }}">{!! $icoWa !!} WhatsApp Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'phone']) }}" class="{{ $isLeads && $curCh === 'phone' ? 'active' : '' }}">{!! $icoPh !!} Phone Leads</a>
                <a href="{{ route('admin.prospect-leads.index', ['channel' => 'instagram']) }}" class="{{ $isLeads && $curCh === 'instagram' ? 'active' : '' }}">{!! $icoIg !!} Instagram Leads</a>

                <a href="{{ route('admin.prospects.index', ['channel' => 'whatsapp']) }}" class="{{ $isContact && $curCh === 'whatsapp' ? 'active' : '' }}">{!! $icoWa !!} WhatsApp Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'phone']) }}" class="{{ $isContact && $curCh === 'phone' ? 'active' : '' }}">{!! $icoPh !!} Phone Leads in Contact</a>
                <a href="{{ route('admin.prospects.index', ['channel' => 'instagram']) }}" class="{{ $isContact && $curCh === 'instagram' ? 'active' : '' }}">{!! $icoIg !!} Instagram Leads in Contact</a>

                <a href="{{ route('admin.prospects.interested') }}" class="{{ request()->routeIs('admin.prospects.interested') ? 'active' : '' }}">
                    <svg class="i-int" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                    Interested Leads
                </a>

                <a href="{{ route('admin.prospects.lost') }}" class="{{ request()->routeIs('admin.prospects.lost') ? 'active' : '' }}">
                    <svg class="i-lost" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
                    Lost Leads
                </a>

                <a href="{{ route('admin.leads.index') }}" class="{{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">
                    <svg class="i-web" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/></svg>
                    Website Form Leads
                </a>

                <a href="{{ route('admin.extra.index', 'funnel') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'funnel' ? 'active' : '' }}">
                    <svg class="i-fun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22,3 2,3 10,12.5 10,19 14,21 14,12.5"/></svg>
                    Funnels
                </a>

                <a href="{{ route('admin.extra.index', 'support') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'support' ? 'active' : '' }}">
                    <svg class="i-sup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2" y="14" width="4" height="6" rx="1.5"/><rect x="18" y="14" width="4" height="6" rx="1.5"/></svg>
                    Customer Support
                </a>

                <a href="{{ route('admin.extra.index', 'ads') }}" class="{{ request()->routeIs('admin.extra.index') && $curType === 'ads' ? 'active' : '' }}">
                    <svg class="i-ads" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l5 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M17 8a5 5 0 0 1 0 8"/></svg>
                    Meta Ads
                </a>

                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg class="i-adm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-4z"/></svg>
                    Users &amp; Activity
                </a>

                <a href="{{ route('admin.commissions.index') }}" class="{{ request()->routeIs('admin.commissions.*') ? 'active' : '' }}">
                    <svg class="i-wa" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Commissions
                </a>

                <a href="{{ route('admin.recycle-bin.index') }}" class="{{ request()->routeIs('admin.recycle-bin.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                    Recycle Bin
                </a>
                @endif
            @endisset
        </nav>

        <div class="pro-logout-wrap" style="padding-bottom:2px;">
            <button type="button" class="theme-toggle-btn" data-theme-toggle aria-pressed="false" title="Toggle light / dark theme">
                <svg class="ico-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>
                <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v2M12 20.5v2M4.2 4.2l1.5 1.5M18.3 18.3l1.5 1.5M1.5 12h2M20.5 12h2M4.2 19.8l1.5-1.5M18.3 5.7l1.5-1.5"/></svg>
                <span class="theme-toggle-label">Dark mode</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}" class="pro-logout-wrap">
            @csrf
            <button type="submit" class="pro-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </button>
        </form>
    </aside>

    <main class="pro-main">
        {{-- Mobile-only bar: the only visible way to open the drawer once the
             sidebar goes off-canvas (≤900px). Hidden on desktop via CSS. --}}
        <div class="pro-mobilebar">
            <button type="button" class="pro-menu-btn" data-drawer-toggle="#proSidebar" data-drawer-scrim="#proScrim" aria-label="Open menu" aria-controls="proSidebar">
                <span></span>
            </button>
            <span class="pro-mobilebar-brand">Apex Growth</span>
        </div>

        {{-- The white title/top bar was removed. The page header is now the
             motivational banner (below), which also hosts any page controls
             (client search, "Add New Client", back / delete) via its actions
             area — see admin/partials/motivation-hero. --}}
        <div class="pro-content">
            {{-- Motivational hero on every page. A page can opt out with
                 @section('own-hero','1') if it ships its own header (dashboard). --}}
            @sectionMissing('own-hero')
                @include('admin.partials.motivation-hero')

                {{-- Full-width client search under the banner — but not on pages
                     that already carry their own controls (In Progress, Clients,
                     client detail), which have their own in-page search. --}}
                @php
                    $proShowSearch = isset($selectedClient)
                        && ! $__env->hasSection('topbar-action')
                        && ! $__env->hasSection('topbar-content');
                @endphp
                @if ($proShowSearch)
                    <form method="GET" action="{{ route('admin.client-list') }}" class="pro-searchbar">
                        <span class="pro-searchbar-field">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search clients by name...">
                        </span>
                        <button type="submit" class="pro-searchbar-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                            <span>Search</span>
                        </button>
                    </form>
                @endif
            @endif

            @if (session('status'))
                <div class="toast-flash" data-toast="success">{{ session('status') }}</div>
            @endif
            @if (session('confirm'))
                <div class="confirm-flash" style="display:none">{{ session('confirm') }}</div>
            @endif
            @if (session('confirm_error'))
                <div class="confirm-error-flash" style="display:none">{{ session('confirm_error') }}</div>
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

{{-- Walk-on animation: shown once, on the first page after login. pull() clears
     the flag so a refresh doesn't replay it. --}}
@if (session()->pull('walker_once', false))
    <div id="fatmanWalker" data-src="{{ asset('lottie/businessman-rocket.json') }}" aria-hidden="true">
        <div class="fw-art"></div>
    </div>
    <script src="{{ asset('js/lottie-light.min.js') }}"></script>
    <script src="{{ asset('js/walker.js') }}" defer></script>
@endif

<script src="{{ asset('js/responsive-nav.js') }}"></script>
<script src="{{ asset('js/theme-toggle.js') }}"></script>
<script src="{{ asset('js/toast.js') }}"></script>
<script src="{{ asset('js/confirm-modal.js') }}"></script>
<script src="{{ asset('js/interactions.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
<script>
/* Rows with data-href are clickable, minus the real controls inside them. */
(function () {
    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row) return;
        if (e.target.closest('a, button, form, input, select, textarea, label, .inline-edit, .no-link')) return;
        var url = row.getAttribute('data-href');
        if (e.metaKey || e.ctrlKey) { window.open(url, '_blank'); }
        else { window.location = url; }
    });
})();

/* Under-banner client search: live-filter the current page's list (Errors,
   New Clients, Today Queue…). Works the same for the super admin and VAs. */
(function () {
    var bar = document.querySelector('.pro-searchbar');
    if (!bar) return;
    var input = bar.querySelector('input[name="search"]');
    if (!input) return;
    // Don't navigate away on Enter — filter in place.
    bar.addEventListener('submit', function (e) { e.preventDefault(); });
    var rows = Array.prototype.slice
        .call(document.querySelectorAll('.pro-content table tbody tr'))
        .filter(function (r) { return !r.querySelector('.empty'); });
    function apply() {
        var q = (input.value || '').trim().toLowerCase();
        rows.forEach(function (r) {
            var t = (r.textContent || '').toLowerCase();
            r.style.display = (!q || t.indexOf(q) !== -1) ? '' : 'none';
        });
    }
    input.addEventListener('input', apply);
    apply();
})();
</script>
@include('partials.pwa-install')
@stack('scripts')
</body>
</html>
