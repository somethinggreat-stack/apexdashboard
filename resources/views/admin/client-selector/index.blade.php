@extends('layouts.admin')

@section('title', 'Select Business Owner')

@section('topbar-content')
    <div class="sbo-topbar-spacer"></div>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Select a Business Owner to Work On</h2>
        @if (Auth::guard('admin')->user()?->isSuper())
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Manage Business Owners</a>
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
                <form method="POST" action="{{ route('admin.client-selector.select', $client->id) }}" class="picker-card-form">
                    @csrf
                    <button type="submit" class="picker-card">
                        <div class="pc-top">
                            <span class="picker-card-name">{{ $client->business_name }}</span>
                            <span class="pill pill-{{ $client->status }}">{{ $client->status }}</span>
                        </div>
                        <div class="picker-card-meta">{{ $client->end_users_count }} clients</div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>

@if (!empty($attention))
    <div class="card na-card">
        <div class="na-head">
            <div>
                <h2>Needs Attention</h2>
                <p class="na-sub">{{ count($attention) }} business owner{{ count($attention) === 1 ? '' : 's' }} need a look right now.</p>
            </div>
            <span class="na-chip">{{ count($attention) }}</span>
        </div>
        <div class="na-colhead"><span>Business Owner</span><span>Status</span><span class="na-act-h">Action</span></div>
        <div class="na-list">
            @foreach ($attention as $a)
                @php $bo = $a['client']; @endphp
                <div class="na-row">
                    <span class="na-bo">{{ $bo->business_name }}</span>
                    <span class="na-badges">
                        @if ($a['pending'])<span class="nab nab-blue">{{ $a['pending'] }} new</span>@endif
                        @if ($a['incomplete'])<span class="nab nab-amber">{{ $a['incomplete'] }} incomplete</span>@endif
                        @if ($a['overdue'])<span class="nab nab-red">{{ $a['overdue'] }} overdue</span>@endif
                    </span>
                    <form method="POST" action="{{ route('admin.client-selector.select', $bo->id) }}" class="na-act">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $a['pending'] ? route('admin.new-clients') : route('admin.end-users.index') }}">
                        <button type="submit" class="{{ $a['pending'] ? 'na-btn-primary' : 'na-btn-soft' }}">{{ $a['pending'] ? 'Review New Clients →' : 'Open Clients →' }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if (!empty($owes))
    @php
        $totOwed = array_sum(array_column($owes, 'pending'));
        $totColl = array_sum(array_column($owes, 'done'));
    @endphp
    <div class="card owes-card">
        <div class="na-head">
            <div>
                <h2>Business Owner Balances</h2>
                <p class="na-sub">How much each business owner owes right now — {{ '$'.number_format($totOwed, 2) }} outstanding · {{ '$'.number_format($totColl, 2) }} collected.</p>
            </div>
        </div>
        <div class="owes-grid">
            @foreach ($owes as $o)
                <form method="POST" action="{{ route('admin.client-selector.select', $o['client']->id) }}" class="owes-form">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ route('admin.payments.index') }}">
                    <button type="submit" class="owes-item {{ $o['pending'] > 0 ? 'is-owed' : 'is-clear' }}">
                        <span class="owes-name">{{ $o['client']->business_name }}</span>
                        <span class="owes-amt">${{ number_format($o['pending'], 2) }}</span>
                        <span class="owes-sub">{{ $o['pending'] > 0 ? 'owed' : 'all paid' }} · ${{ number_format($o['done'], 2) }} collected</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif

@push('head')
<style>
    /* Payments overview tiles (super admin) */
    .pay-card { margin-top:18px; }
    .pay-tiles { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
    @media (max-width:820px) { .pay-tiles { grid-template-columns:1fr; } }
    .pay-tile { position:relative; overflow:hidden; border-radius:16px; padding:18px 20px; color:#fff; display:flex; flex-direction:column; gap:3px; box-shadow:0 12px 26px rgba(15,23,42,.14); }
    .pay-tile .pt-num { font-size:30px; font-weight:800; line-height:1.05; }
    .pay-tile .pt-lbl { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.97; }
    .pay-tile .pt-sub { font-size:11.5px; opacity:.85; }
    .pt-green { background:linear-gradient(135deg,#059669,#34d399); }
    .pt-amber { background:linear-gradient(135deg,#d97706,#fbbf24); }
    .pt-blue  { background:linear-gradient(135deg,#2563eb,#38bdf8); }

    /* Needs Attention — full-width list (shown to all VAs) */
    .na-card { margin-top:18px; }
    .na-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px; }
    .na-head h2 { margin:0; font-size:19px; font-weight:800; color:#0f172a; }
    .na-sub { margin:4px 0 0; font-size:13px; color:#94a3b8; }
    .na-chip { background:#fee2e2; color:#b91c1c; font-weight:800; font-size:13px; border-radius:999px; padding:4px 13px; flex:none; }
    .na-colhead { display:grid; grid-template-columns:1.1fr 1.7fr auto; gap:14px; font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; padding:0 0 10px; border-bottom:1px solid #eef1f6; }
    .na-act-h { text-align:right; }
    .na-row { display:grid; grid-template-columns:1.1fr 1.7fr auto; gap:14px; align-items:center; padding:14px 0; border-bottom:1px solid #f4f6fa; }
    .na-row:last-child { border-bottom:0; }
    .na-bo { font-weight:700; font-size:14.5px; color:#0f172a; }
    .na-badges { display:flex; flex-wrap:wrap; gap:6px; }
    .nab { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:3px 11px; border-radius:999px; white-space:nowrap; }
    .nab::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .nab-blue{ background:#e0f2fe; color:#0369a1; } .nab-amber{ background:#fef3c7; color:#b45309; } .nab-red{ background:#fee2e2; color:#dc2626; }
    .na-act { margin:0; justify-self:end; }
    .na-btn-primary { font-size:12.5px; font-weight:700; color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8); border:0; border-radius:10px; padding:9px 15px; cursor:pointer; white-space:nowrap; box-shadow:0 6px 16px rgba(37,99,235,.28); transition:filter .12s; }
    .na-btn-primary:hover { filter:brightness(1.06); }
    .na-btn-soft { font-size:12.5px; font-weight:700; color:#334155; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:9px 15px; cursor:pointer; white-space:nowrap; transition:background .12s; }
    .na-btn-soft:hover { background:#f8fafc; }
    @media (max-width:640px){ .na-colhead { display:none; } .na-row { grid-template-columns:1fr; gap:9px; } .na-act { justify-self:start; } }

    /* Business Owner Balances — per-BO owed cards (super admin) */
    .owes-card { margin-top:18px; }
    .owes-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px,1fr)); gap:12px; }
    .owes-form { margin:0; }
    .owes-item { width:100%; text-align:left; cursor:pointer; display:flex; flex-direction:column; gap:3px; padding:14px 15px; border-radius:14px; border:1px solid #eef1f6; background:#fff; transition:transform .12s, box-shadow .12s, border-color .12s; border-left:4px solid #e2e8f0; }
    .owes-item:hover { transform:translateY(-2px); box-shadow:0 10px 22px rgba(15,23,42,.10); }
    .owes-item.is-owed { border-left-color:#f59e0b; }
    .owes-item.is-clear { border-left-color:#22c55e; }
    .owes-name { font-weight:700; font-size:13.5px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .owes-amt { font-size:24px; font-weight:800; letter-spacing:-.5px; line-height:1.05; }
    .owes-item.is-owed .owes-amt { color:#b45309; }
    .owes-item.is-clear .owes-amt { color:#059669; }
    .owes-sub { font-size:11px; color:#94a3b8; }

    /* Business-owner picker cards — subtle lift + accent */
    .picker-card { transition: transform .12s, box-shadow .12s, border-color .12s; border:1px solid #e6ebf2 !important; border-radius:10px !important; box-shadow:0 1px 2px rgba(15,23,42,.05) !important; }
    .picker-card:hover { transform:translateY(-2px); box-shadow:0 8px 18px rgba(15,23,42,.10) !important; border-color:#bfdbfe !important; }
    .picker-card-name { font-weight:700; }

    .att-card { margin-top:18px; }
    .att-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }

    /* Summary tiles */
    .att-tiles { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:22px; }
    @media (max-width:820px) { .att-tiles { grid-template-columns:1fr; } }
    .att-tile {
        position:relative; overflow:hidden; border-radius:16px; padding:18px 20px; color:#fff;
        display:flex; flex-direction:column; gap:2px; box-shadow:0 12px 26px rgba(15,23,42,.14);
    }
    .att-tile .tile-num { font-size:36px; font-weight:800; line-height:1.05; }
    .att-tile .tile-lbl { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.95; }
    .att-tile .tile-ico { position:absolute; right:14px; top:14px; opacity:.28; }
    .att-tile .tile-ico svg { width:46px; height:46px; }
    .tile-blue  { background:linear-gradient(135deg,#2563eb,#38bdf8); }
    .tile-amber { background:linear-gradient(135deg,#d97706,#fbbf24); }
    .tile-red   { background:linear-gradient(135deg,#dc2626,#f87171); }

    /* Attention table */
    .att-table thead th { background:transparent !important; color:#94a3b8; font-size:11px; letter-spacing:.07em; text-transform:uppercase; }
    .att-table td { vertical-align:middle; }
    .att-table td:first-child { padding-left:16px; }
    .att-table tbody tr { transition: background .12s; }
    .att-table tbody tr:hover { background:#f8fafc; }
    .att-row.sev-red   td:first-child { box-shadow: inset 4px 0 0 #ef4444; }
    .att-row.sev-amber td:first-child { box-shadow: inset 4px 0 0 #f59e0b; }
    .att-row.sev-blue  td:first-child { box-shadow: inset 4px 0 0 #3b82f6; }
    .att-name { font-weight:700; color:#0f172a; }
    .att-dash { color:#cbd5e1; }

    .att-badge { display:inline-flex; align-items:center; gap:7px; padding:4px 12px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .att-badge::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; }
    .att-blue  { background:#e0f2fe; color:#0369a1; }
    .att-amber { background:#fef3c7; color:#b45309; }
    .att-red   { background:#fee2e2; color:#dc2626; }

    .att-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .att-actions form { display:inline; margin:0; }
    .att-actions .btn { white-space:nowrap; }
    .att-actions .btn-primary { background:linear-gradient(135deg,#2563eb,#1d4ed8) !important; border:0 !important; box-shadow:0 6px 16px rgba(37,99,235,.30); }
    .att-actions .btn-primary:hover { background:linear-gradient(135deg,#1d4ed8,#1e40af) !important; }
    .att-open { background:#fff !important; border:1px solid #cbd5e1 !important; color:#334155 !important; }
    .att-open:hover { background:#f1f5f9 !important; }

    /* Subtle page tint */
    .content {
        background:
            radial-gradient(1000px circle at 100% -12%, #e9f1ff 0%, transparent 40%),
            radial-gradient(900px circle at 0% 120%, #e9faf2 0%, transparent 42%);
    }

    /* Business-owner cards — compact, professional clickable chips */
    .picker-grid { grid-template-columns:repeat(auto-fill, minmax(206px,1fr)) !important; gap:12px !important; }
    .picker-card {
        display:flex !important; flex-direction:column; align-items:flex-start !important; text-align:left !important;
        gap:6px; padding:12px 14px !important; background:#fff !important;
    }
    .pc-top { display:flex; align-items:center; justify-content:space-between; width:100%; gap:8px; }
    .picker-card-name { font-weight:700 !important; font-size:13.5px; color:#0f172a; line-height:1.25; }
    .picker-card-meta { color:#64748b; font-size:11.5px; font-weight:600; }
    .picker-card .pill { font-size:9px; padding:2px 7px; letter-spacing:.02em; }
</style>
@endpush
@endsection
