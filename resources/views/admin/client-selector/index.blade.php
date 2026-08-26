@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Select Business Owner')

{{-- No topbar: the "Select a Business Owner to Work On" card already titles it. --}}
@section('no-topbar', '1')

{{-- This is the VA's home page (they have no dashboard), so greet them by name
     like the super admin's dashboard — opt out of the rotating quote banner. --}}
@unless (Auth::guard('admin')->user()?->isSuper())
@section('own-hero', '1')
@endunless

@section('content')
@php
    $isSuper = Auth::guard('admin')->user()?->isSuper();
    $isVa    = Auth::guard('admin')->user()?->isVa();

    /* A stable accent + glyph per business owner, keyed off the name so a BO
       always looks the same on both cards. */
    $accents = ['#4f46e5','#ec4899','#0ea5e9','#10b981','#f59e0b','#8b5cf6','#f97316','#14b8a6','#f43f5e','#3b82f6'];
    $glyphs = [
        '<circle cx="12" cy="8" r="3.6"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',                 // user
        '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.9"/><path d="M18 20a6 6 0 0 0-2-4.5"/>', // users
        '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>',   // star
        '<path d="M4 8l4 3.5L12 5l4 6.5L20 8l-1.6 10H5.6z"/>',                                     // crown
        '<path d="M12 3l7 3.2v5c0 4.4-3 8-7 9.3-4-1.3-7-4.9-7-9.3v-5z"/>',                          // shield
        '<path d="M7 4h10l4 5-9 11L3 9z"/><path d="M3 9h18"/>',                                     // gem
        '<path d="M13 2 4.5 13.5H11L10 22l9-11.5h-6.5z"/>',                                         // bolt
        '<path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M7 5H4.5v1.5A3.5 3.5 0 0 0 8 10"/><path d="M17 5h2.5v1.5A3.5 3.5 0 0 1 16 10"/><path d="M10 13h4v3h-4z"/><path d="M7.5 20h9"/>', // trophy
        '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/>', // briefcase
        '<path d="M12 20s-7-4.4-7-9.4A3.9 3.9 0 0 1 12 7.6 3.9 3.9 0 0 1 19 10.6c0 5-7 9.4-7 9.4z"/>', // heart
    ];
    $accentOf = fn ($name) => $accents[crc32($name) % count($accents)];
    $glyphOf  = fn ($name) => $glyphs[crc32($name) % count($glyphs)];
    $money    = fn ($v) => '$' . number_format((float) $v, 2);
@endphp

{{-- VA welcome banner (super admin gets this on their dashboard instead). --}}
@unless ($isSuper)
<div class="dash-hero">@include('admin.partials.welcome-hero', ['heroMe' => Auth::guard('admin')->user()])</div>
@endunless

<div class="card sbo-card">
    <div class="sbo-head">
        <div>
            <h2>Select a Business Owner to Work On</h2>
            <p class="sbo-sub">Choose a business owner to view and manage their account.</p>
        </div>
        @if ($isSuper)
            <a href="{{ route('admin.clients.index') }}" class="sbo-manage">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Manage Business Owners
            </a>
        @endif
    </div>

    @if ($clients->isEmpty())
        <div class="empty">
            No business owners yet.
            <a href="{{ route('admin.clients.index') }}">Add one to get started.</a>
        </div>
    @else
        <div class="picker-grid">
            @foreach ($clients as $client)
                @php $accent = $accentOf($client->business_name); @endphp
                <form method="POST" action="{{ route('admin.client-selector.select', $client->id) }}" class="picker-card-form">
                    @csrf
                    <button type="submit" class="picker-card">
                        <span class="pc-ico" style="background:{{ $accent }};">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $glyphOf($client->business_name) !!}</svg>
                        </span>
                        <span class="pc-body">
                            <span class="picker-card-name">{{ $client->business_name }}</span>
                            <span class="picker-card-meta">{{ $client->end_users_count }} clients</span>
                        </span>
                        <span class="pc-pill pc-{{ $client->status }}">{{ ucfirst($client->status) }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>

@if (!empty($attention))
    @php
        $naTotNew  = array_sum(array_column($attention, 'pending'));
        $naTotInc  = array_sum(array_column($attention, 'incomplete'));
        $naTotOver = array_sum(array_column($attention, 'overdue'));
        $naCntNew  = count(array_filter($attention, fn ($a) => $a['pending'] > 0));
        $naCntInc  = count(array_filter($attention, fn ($a) => $a['incomplete'] > 0));
        $naCntOver = count(array_filter($attention, fn ($a) => $a['overdue'] > 0));
        // Priority weight — overdue is worst, then new intake, then incomplete logs.
        $naPriority = function ($a) {
            $w = $a['overdue'] * 3 + $a['pending'] * 2 + $a['incomplete'];
            return $w >= 45 ? ['Critical', 4] : ($w >= 20 ? ['High', 3] : ($w >= 8 ? ['Medium', 2] : ['Low', 1]));
        };
    @endphp
    <div class="card nx-card">
        <div class="nx-head">
            <div class="nx-head-left">
                <span class="nx-head-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
                </span>
                <div>
                    <h2>Needs Attention</h2>
                    <p class="nx-sub">{{ count($attention) }} business owner{{ count($attention) === 1 ? '' : 's' }} require action</p>
                </div>
            </div>
            <label class="nx-sort">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                <select id="nxSort" onchange="nxSort(this.value)">
                    <option value="priority">Highest priority</option>
                    <option value="overdue">Most overdue</option>
                    <option value="incomplete">Most incomplete</option>
                    <option value="new">Most new</option>
                </select>
                <svg class="nx-sort-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6,9 12,15 18,9"/></svg>
            </label>
        </div>

        <div class="nx-tiles">
            <div class="nx-tile nx-t-blue">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                <div><span class="nx-tile-lbl">New Clients</span><span class="nx-tile-val">{{ number_format($naTotNew) }}</span></div>
            </div>
            <div class="nx-tile nx-t-amber">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <div><span class="nx-tile-lbl">Incomplete</span><span class="nx-tile-val">{{ number_format($naTotInc) }}</span></div>
            </div>
            <div class="nx-tile nx-t-red">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16"/></svg></span>
                <div><span class="nx-tile-lbl">Overdue</span><span class="nx-tile-val">{{ number_format($naTotOver) }}</span></div>
            </div>
        </div>

        <div class="nx-tabs">
            <button type="button" class="nx-tab is-active" data-tab="all">All <span>{{ count($attention) }}</span></button>
            <button type="button" class="nx-tab" data-tab="new">New <span>{{ $naCntNew }}</span></button>
            <button type="button" class="nx-tab" data-tab="incomplete">Incomplete <span>{{ $naCntInc }}</span></button>
            <button type="button" class="nx-tab" data-tab="overdue">Overdue <span>{{ $naCntOver }}</span></button>
        </div>

        <div class="nx-table">
            <div class="nx-thead">
                <span>Business Owner</span><span>New</span><span>Incomplete</span><span>Overdue</span><span>Priority</span><span class="nx-act-h">Action</span>
            </div>
            <div class="nx-tbody" id="nxBody">
                @foreach ($attention as $a)
                    @php
                        $bo = $a['client'];
                        [$plabel, $plevel] = $naPriority($a);
                        $accent = $accentOf($bo->business_name);
                        $init = mb_strtoupper(mb_substr($bo->business_name, 0, 1));
                    @endphp
                    <div class="nx-row" data-new="{{ $a['pending'] }}" data-inc="{{ $a['incomplete'] }}" data-over="{{ $a['overdue'] }}" data-prio="{{ $plevel }}" data-score="{{ $a['score'] }}">
                        <span class="nx-bo">
                            <span class="nx-avatar" style="background:{{ $accent }}22; color:{{ $accent }};">{{ $init }}</span>
                            <span class="nx-name">{{ $bo->business_name }}</span>
                        </span>
                        <span class="nx-cell" data-h="New">@if ($a['pending'])<span class="nx-num nx-n-blue">{{ $a['pending'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell" data-h="Incomplete">@if ($a['incomplete'])<span class="nx-num nx-n-amber">{{ $a['incomplete'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell" data-h="Overdue">@if ($a['overdue'])<span class="nx-num nx-n-red">{{ $a['overdue'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell nx-prio nx-prio-{{ $plevel }}" data-h="Priority"><span class="nx-dot"></span>{{ $plabel }}</span>
                        <span class="nx-cell nx-act-cell">
                            <form method="POST" action="{{ route('admin.client-selector.select', $bo->id) }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ $a['pending'] ? route('admin.new-clients') : route('admin.end-users.index') }}">
                                <button type="submit" class="{{ $a['pending'] ? 'nx-btn-primary' : 'nx-btn-soft' }}">{{ $a['pending'] ? 'Review new client →' : 'View clients →' }}</button>
                            </form>
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="nx-empty" id="nxEmpty" style="display:none;">No business owners match this filter.</div>
        </div>
    </div>
@endif

@if (!empty($owes))
    @php
        // Outstanding ignores inactive owners ($owes is active-only); Collected
        // includes money already taken from inactive owners too ($collectedAll).
        $totOwed = array_sum(array_column($owes, 'pending'));
        $totColl = $collectedAll ?? array_sum(array_column($owes, 'done'));
    @endphp
    <div class="card owes-card">
        <div class="sbo-head">
            <div>
                <h2>Business Owner Balances</h2>
                <p class="sbo-sub">Overview of amounts owed and collected across all business owners.</p>
            </div>
            <label class="owes-filter">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22,3 2,3 10,12.5 10,19 14,21 14,12.5"/></svg>
                <select id="owesFilter" onchange="filterOwes(this.value)">
                    <option value="all">All Status</option>
                    <option value="owed">Owing</option>
                    <option value="clear">All Paid</option>
                </select>
                <svg class="owes-chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6,9 12,15 18,9"/></svg>
            </label>
        </div>

        <div class="owes-totals">
            <div class="owes-total">
                <span class="ot-ico ot-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/><path d="M16 15h2"/></svg>
                </span>
                <span class="ot-body">
                    <span class="ot-lbl">Total Outstanding</span>
                    <span class="ot-val ot-val-blue">{{ $money($totOwed) }}</span>
                </span>
            </div>
            <div class="owes-total">
                <span class="ot-ico ot-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8"/><path d="M3 20.5h18"/></svg>
                </span>
                <span class="ot-body">
                    <span class="ot-lbl">Total Collected</span>
                    <span class="ot-val ot-val-green">{{ $money($totColl) }}</span>
                </span>
            </div>
        </div>

        <div class="owes-grid" id="owesGrid">
            @foreach ($owes as $o)
                @php
                    $name   = $o['client']->business_name;
                    $isOwed = $o['pending'] > 0;
                    $accent = $isOwed ? $accentOf($name) : '#22c55e';
                @endphp
                <form method="POST" action="{{ route('admin.client-selector.select', $o['client']->id) }}"
                      class="owes-form" data-status="{{ $isOwed ? 'owed' : 'clear' }}">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ route('admin.payments.index') }}">
                    <button type="submit" class="owes-item {{ $isOwed ? 'is-owed' : 'is-clear' }}" style="--accent:{{ $accent }};">
                        <span class="owes-ico" style="background:{{ $accent }};">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $glyphOf($name) !!}</svg>
                        </span>
                        <span class="owes-body">
                            <span class="owes-name">{{ $name }}</span>
                            <span class="owes-amt">{{ $money($o['pending']) }}</span>
                            <span class="owes-sub">{{ $isOwed ? 'owed' : 'all paid' }} · {{ $money($o['done']) }} collected</span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>
        <div class="dempty owes-empty" id="owesEmpty" style="display:none;">No business owners match that filter.</div>
    </div>
@endif

@push('head')
<style>
    /* ---------- shared card header ---------- */
    .sbo-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:18px; flex-wrap:wrap; }
    .sbo-head h2 { margin:0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-.01em; }
    .sbo-sub { margin:4px 0 0; font-size:13px; color:#94a3b8; }

    .sbo-manage {
        display:inline-flex; align-items:center; gap:8px; flex:none;
        padding:11px 18px; border-radius:11px; text-decoration:none; white-space:nowrap;
        background:linear-gradient(135deg,#3b82f6,#4f46e5); color:#fff;
        font-size:13.5px; font-weight:700;
        box-shadow:0 8px 20px rgba(79,70,229,.30);
        transition:filter .13s, transform .13s;
    }
    .sbo-manage:hover { filter:brightness(1.07); transform:translateY(-1px); }

    /* ---------- business-owner picker cards ---------- */
    .picker-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px,1fr)) !important; gap:14px !important; }
    .picker-card-form { margin:0; }
    .picker-card {
        width:100%; cursor:pointer; text-align:left !important;
        display:grid !important; grid-template-columns:auto 1fr; align-items:center; gap:12px;
        padding:14px 15px !important; background:#fff !important;
        border:1px solid #eceff5 !important; border-radius:14px !important;
        box-shadow:0 1px 2px rgba(15,23,42,.04) !important;
        transition:transform .12s, box-shadow .12s, border-color .12s;
    }
    .picker-card:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(15,23,42,.10) !important; border-color:#dbe3ef !important; }
    .pc-ico {
        width:44px; height:44px; flex:none; border-radius:50%;
        display:inline-flex; align-items:center; justify-content:center; color:#fff;
    }
    .pc-ico svg { width:21px; height:21px; }
    .pc-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
    .picker-card-name { font-weight:700 !important; font-size:14px; color:#0f172a; line-height:1.3; }
    .picker-card-meta { color:#94a3b8; font-size:12px; font-weight:500; }
    .pc-pill {
        grid-column:2; justify-self:end; margin-top:-2px;
        display:inline-flex; align-items:center; gap:6px;
        font-size:11px; font-weight:600; padding:3px 10px; border-radius:999px;
    }
    .pc-pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .pc-active    { background:#ecfdf5; color:#059669; }
    .pc-paused    { background:#fef3c7; color:#b45309; }
    .pc-inactive,
    .pc-archived  { background:#f1f5f9; color:#64748b; }

    /* ---------- balances ---------- */
    .owes-card { margin-top:18px; }

    .owes-filter { position:relative; display:inline-flex; align-items:center; flex:none; }
    .owes-filter > svg:first-child { position:absolute; left:14px; color:#94a3b8; pointer-events:none; }
    .owes-filter .owes-chev { position:absolute; right:13px; color:#94a3b8; pointer-events:none; }
    .owes-filter select {
        -webkit-appearance:none; appearance:none; cursor:pointer;
        padding:11px 36px 11px 38px; min-width:172px;
        background:#fff; border:1px solid #e6ebf2; border-radius:11px;
        font:inherit; font-size:13.5px; font-weight:600; color:#334155;
    }
    .owes-filter select:focus { outline:none; border-color:#4f46e5; }

    .owes-totals { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:18px; }
    @media (max-width:760px){ .owes-totals { grid-template-columns:1fr; } }
    .owes-total {
        display:flex; align-items:center; gap:14px;
        padding:16px 18px; border-radius:14px;
        background:linear-gradient(135deg,#fbfcfe,#f6f8fc); border:1px solid #eef1f6;
    }
    .ot-ico { width:44px; height:44px; flex:none; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; color:#fff; }
    .ot-ico svg { width:21px; height:21px; }
    .ot-blue  { background:linear-gradient(135deg,#3b82f6,#4f46e5); box-shadow:0 8px 18px rgba(59,130,246,.28); }
    .ot-green { background:linear-gradient(135deg,#22c55e,#059669); box-shadow:0 8px 18px rgba(34,197,94,.28); }
    .ot-body { display:flex; flex-direction:column; gap:2px; }
    .ot-lbl { font-size:12.5px; color:#94a3b8; font-weight:600; }
    .ot-val { font-size:25px; font-weight:800; letter-spacing:-.6px; line-height:1.05; }
    .ot-val-blue  { color:#2563eb; }
    .ot-val-green { color:#059669; }

    .owes-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(215px,1fr)); gap:14px; }
    .owes-form { margin:0; }
    .owes-item {
        width:100%; text-align:left; cursor:pointer;
        display:flex; align-items:flex-start; gap:12px;
        padding:14px 15px; border-radius:14px;
        background:#fff; border:1px solid #eceff5;
        border-left:4px solid var(--accent, #e2e8f0);
        transition:transform .12s, box-shadow .12s;
    }
    .owes-item:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(15,23,42,.10); }
    .owes-ico { width:34px; height:34px; flex:none; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; }
    .owes-ico svg { width:17px; height:17px; }
    .owes-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
    .owes-name { font-weight:600; font-size:13px; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .owes-amt { font-size:24px; font-weight:800; letter-spacing:-.5px; line-height:1.1; color:var(--accent); }
    .owes-item.is-clear .owes-amt { color:#059669; }
    .owes-sub { font-size:11px; color:#94a3b8; }
    .owes-empty { margin-top:6px; }

    /* ---------- Needs Attention (premium) ---------- */
    .nx-card { margin-top:18px; }
    .nx-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .nx-head-left { display:flex; align-items:center; gap:13px; }
    .nx-head-ico { flex:none; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .nx-head-ico svg { width:22px; height:22px; }
    .nx-head h2 { margin:0; font-size:20px; font-weight:800; letter-spacing:-.01em; color:var(--text); }
    .nx-sub { margin:2px 0 0; font-size:13px; color:var(--muted); }
    .nx-sort { position:relative; display:inline-flex; align-items:center; }
    .nx-sort > svg:first-child { position:absolute; left:12px; width:15px; height:15px; color:var(--muted); pointer-events:none; }
    .nx-sort select { appearance:none; background:var(--surface); border:1px solid var(--border); border-radius:11px; color:var(--text); font:inherit; font-size:13px; font-weight:600; padding:9px 34px 9px 34px; cursor:pointer; }
    .nx-sort-chev { position:absolute; right:12px; width:13px; height:13px; color:var(--muted); pointer-events:none; }

    .nx-tiles { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    .nx-tile { display:flex; align-items:center; gap:13px; padding:15px 17px; border-radius:14px; border:1px solid var(--border); background:var(--surface); }
    .nx-tile-ico { flex:none; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
    .nx-tile-ico svg { width:22px; height:22px; }
    .nx-tile-lbl { display:block; font-size:13px; font-weight:600; color:var(--muted); }
    .nx-tile-val { display:block; font-size:27px; font-weight:800; line-height:1.05; color:var(--text); }
    .nx-t-blue  { border-color:var(--tint-blue-fg);  } .nx-t-blue  .nx-tile-ico { background:var(--tint-blue-bg);  color:var(--tint-blue-fg);  } .nx-t-blue  .nx-tile-val { color:var(--tint-blue-fg); }
    .nx-t-amber { border-color:var(--tint-amber-fg); } .nx-t-amber .nx-tile-ico { background:var(--tint-amber-bg); color:var(--tint-amber-fg); } .nx-t-amber .nx-tile-val { color:var(--tint-amber-fg); }
    .nx-t-red   { border-color:var(--tint-red-fg);   } .nx-t-red   .nx-tile-ico { background:var(--tint-red-bg);   color:var(--tint-red-fg);   } .nx-t-red   .nx-tile-val { color:var(--tint-red-fg); }

    .nx-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
    .nx-tab { display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--muted); background:transparent; border:1px solid transparent; border-radius:10px; padding:7px 13px; cursor:pointer; transition:background .12s, color .12s; }
    .nx-tab span { font-size:11.5px; font-weight:800; padding:1px 8px; border-radius:999px; background:var(--surface-2); color:var(--muted); }
    .nx-tab:hover { background:var(--surface-2); color:var(--text); }
    .nx-tab.is-active { color:var(--tint-indigo-fg); background:var(--tint-indigo-bg); }
    .nx-tab.is-active span { background:var(--tint-indigo-fg); color:var(--on-accent, #fff); }

    .nx-thead, .nx-row { display:grid; grid-template-columns:minmax(150px,2.2fr) .8fr 1.1fr .9fr 1.1fr auto; gap:12px; align-items:center; }
    .nx-thead { padding:12px 10px; font-size:10.5px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--border); }
    .nx-thead span:nth-child(2), .nx-thead span:nth-child(3), .nx-thead span:nth-child(4) { text-align:center; }
    .nx-act-h { text-align:right; }
    .nx-row { padding:11px 10px; border-radius:10px; transition:background .12s; }
    .nx-row + .nx-row { border-top:1px solid var(--border-soft, var(--border)); }
    .nx-row:hover { background:var(--surface-2); }
    .nx-bo { display:flex; align-items:center; gap:11px; min-width:0; }
    .nx-avatar { flex:none; width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; }
    .nx-name { font-weight:700; font-size:14.5px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .nx-cell { display:flex; align-items:center; justify-content:center; }
    .nx-num { min-width:30px; text-align:center; font-size:13px; font-weight:800; padding:3px 10px; border-radius:8px; }
    .nx-n-blue  { background:var(--tint-blue-bg);  color:var(--tint-blue-fg); }
    .nx-n-amber { background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .nx-n-red   { background:var(--tint-red-bg);   color:var(--tint-red-fg); }
    .nx-dash { color:var(--muted); opacity:.55; }
    .nx-prio { justify-content:flex-start; gap:7px; font-size:13px; font-weight:700; }
    .nx-dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex:none; }
    .nx-prio-4 { color:var(--tint-red-fg); }
    .nx-prio-3 { color:var(--tint-amber-fg); }
    .nx-prio-2 { color:var(--tint-blue-fg); }
    .nx-prio-1 { color:var(--muted); }
    .nx-act-cell { justify-content:flex-end; }
    .nx-btn-primary { font-size:12.5px; font-weight:700; color:var(--on-accent, #fff); background:linear-gradient(135deg,#2563eb,#1d4ed8); border:0; border-radius:10px; padding:8px 15px; cursor:pointer; white-space:nowrap; box-shadow:0 6px 16px rgba(37,99,235,.28); transition:filter .12s; }
    .nx-btn-primary:hover { filter:brightness(1.06); }
    .nx-btn-soft { font-size:12.5px; font-weight:700; color:var(--text-soft, var(--text)); background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:8px 15px; cursor:pointer; white-space:nowrap; transition:background .12s; }
    .nx-btn-soft:hover { background:var(--surface-2); }
    .nx-empty { padding:26px; text-align:center; font-size:13px; color:var(--muted); }
    @media (max-width:720px){
        .nx-tiles { grid-template-columns:1fr; }
        .nx-thead { display:none; }
        .nx-row { grid-template-columns:1fr auto; gap:8px 10px; }
        .nx-bo { grid-column:1; } .nx-act-cell { grid-column:2; grid-row:1; }
        .nx-cell:not(.nx-act-cell) { justify-content:flex-start; }
        .nx-cell[data-h]::before { content:attr(data-h) ": "; font-size:11px; font-weight:700; color:var(--muted); margin-right:6px; text-transform:uppercase; letter-spacing:.04em; }
    }

    .dempty { padding:16px; text-align:center; font-size:13px; color:#94a3b8; }

    /* Subtle page tint */
    .content {
        background:
            radial-gradient(1000px circle at 100% -12%, #e9f1ff 0%, transparent 40%),
            radial-gradient(900px circle at 0% 120%, #e9faf2 0%, transparent 42%);
    }

    /* ===== Dark mode ===== (only overrides — light theme above is untouched) */
    :root[data-theme="dark"] .content { background: var(--pro-bg); }
    :root[data-theme="dark"] .sbo-head h2,
    :root[data-theme="dark"] .na-head h2,
    :root[data-theme="dark"] .picker-card-name,
    :root[data-theme="dark"] .na-bo { color: var(--pro-text); }
    :root[data-theme="dark"] .sbo-sub,
    :root[data-theme="dark"] .na-sub,
    :root[data-theme="dark"] .picker-card-meta,
    :root[data-theme="dark"] .na-colhead,
    :root[data-theme="dark"] .owes-name,
    :root[data-theme="dark"] .owes-sub,
    :root[data-theme="dark"] .dempty,
    :root[data-theme="dark"] .ot-lbl { color: var(--pro-muted); }
    :root[data-theme="dark"] .ot-val { color: var(--pro-text); }

    /* card-like surfaces */
    :root[data-theme="dark"] .picker-card,
    :root[data-theme="dark"] .owes-item,
    :root[data-theme="dark"] .owes-total,
    :root[data-theme="dark"] .na-btn-soft {
        background: var(--pro-card) !important; border-color: var(--pro-line) !important;
    }
    :root[data-theme="dark"] .na-btn-soft { color: var(--pro-text); }
    :root[data-theme="dark"] .na-btn-soft:hover { background: rgba(255,255,255,.05); }
    :root[data-theme="dark"] .picker-card:hover,
    :root[data-theme="dark"] .owes-item:hover { border-color: var(--pro-indigo) !important; }
    :root[data-theme="dark"] .na-colhead { border-bottom-color: var(--pro-line); }
    :root[data-theme="dark"] .na-row { border-bottom-color: var(--pro-line); }
    :root[data-theme="dark"] .owes-filter select { background: var(--pro-card); color: var(--pro-text); border-color: var(--pro-line); }

    /* status pills + badges: translucent tints instead of bright light fills */
    :root[data-theme="dark"] .pc-active { background: rgba(16,185,129,.18); color:#6ee7b7; }
    :root[data-theme="dark"] .pc-paused { background: rgba(245,158,11,.18); color:#fcd34d; }
    :root[data-theme="dark"] .pc-inactive,
    :root[data-theme="dark"] .pc-archived { background: rgba(148,163,184,.16); color:#cbd5e1; }
    :root[data-theme="dark"] .nab-blue  { background: rgba(59,130,246,.20); color:#93c5fd; }
    :root[data-theme="dark"] .nab-amber { background: rgba(245,158,11,.20); color:#fcd34d; }
    :root[data-theme="dark"] .nab-red   { background: rgba(239,68,68,.20);  color:#fca5a5; }
</style>
@endpush

@push('scripts')
<script>
window.filterOwes = function (value) {
    var shown = 0;
    document.querySelectorAll('#owesGrid .owes-form').forEach(function (f) {
        var match = (value === 'all') || (f.dataset.status === value);
        f.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    var empty = document.getElementById('owesEmpty');
    if (empty) empty.style.display = shown ? 'none' : '';
};

/* Needs Attention — tab filter + sort. */
(function () {
    var body = document.getElementById('nxBody');
    if (!body) return;
    var rows = Array.prototype.slice.call(body.querySelectorAll('.nx-row'));
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.nx-tab'));
    var empty = document.getElementById('nxEmpty');
    var activeTab = 'all';

    function num(el, k) { return parseInt(el.dataset[k] || '0', 10); }

    function applyFilter() {
        var shown = 0;
        rows.forEach(function (r) {
            var ok = activeTab === 'all'
                || (activeTab === 'new' && num(r, 'new') > 0)
                || (activeTab === 'incomplete' && num(r, 'inc') > 0)
                || (activeTab === 'overdue' && num(r, 'over') > 0);
            r.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (empty) empty.style.display = shown ? 'none' : '';
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
            activeTab = t.dataset.tab;
            applyFilter();
        });
    });

    window.nxSort = function (key) {
        var map = { priority: 'prio', overdue: 'over', incomplete: 'inc', new: 'new' };
        var k = map[key] || 'prio';
        var sorted = rows.slice().sort(function (a, b) {
            var d = num(b, k) - num(a, k);
            return d !== 0 ? d : num(b, 'score') - num(a, 'score');
        });
        sorted.forEach(function (r) { body.appendChild(r); });
    };
})();
</script>
@endpush
@endsection
