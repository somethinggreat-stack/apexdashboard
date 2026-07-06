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
    @php
        $totNew = array_sum(array_column($attention, 'pending'));
        $totInc = array_sum(array_column($attention, 'incomplete'));
        $totOver = array_sum(array_column($attention, 'overdue'));
    @endphp
    <div class="card att-card">
        <div class="att-head">
            <div>
                <h2 style="margin:0;">Needs Attention</h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">
                    {{ count($attention) }} business owner{{ count($attention) === 1 ? '' : 's' }} need a look right now.
                </p>
            </div>
        </div>

        <div class="att-tiles">
            <div class="att-tile tile-blue">
                <span class="tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="tile-num">{{ $totNew }}</span>
                <span class="tile-lbl">New Intake Clients</span>
            </div>
            <div class="att-tile tile-amber">
                <span class="tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <span class="tile-num">{{ $totInc }}</span>
                <span class="tile-lbl">Incomplete Logs</span>
            </div>
            <div class="att-tile tile-red">
                <span class="tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <span class="tile-num">{{ $totOver }}</span>
                <span class="tile-lbl">Overdue Rounds</span>
            </div>
        </div>

        <table class="data-table att-table">
            <thead>
                <tr>
                    <th>Business Owner</th>
                    <th>New Intake</th>
                    <th>Incomplete Logs</th>
                    <th>Overdue Rounds</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attention as $a)
                    @php $sev = $a['overdue'] ? 'sev-red' : ($a['pending'] ? 'sev-blue' : 'sev-amber'); @endphp
                    <tr class="att-row {{ $sev }}">
                        <td><span class="att-name">{{ $a['client']->business_name }}</span></td>
                        <td>@if ($a['pending'])<span class="att-badge att-blue">{{ $a['pending'] }} new</span>@else <span class="att-dash">—</span>@endif</td>
                        <td>@if ($a['incomplete'])<span class="att-badge att-amber">{{ $a['incomplete'] }} incomplete</span>@else <span class="att-dash">—</span>@endif</td>
                        <td>@if ($a['overdue'])<span class="att-badge att-red">{{ $a['overdue'] }} overdue</span>@else <span class="att-dash">—</span>@endif</td>
                        <td class="no-link">
                            <div class="att-actions">
                                @if ($a['pending'])
                                    <form method="POST" action="{{ route('admin.client-selector.select', $a['client']->id) }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ route('admin.new-clients') }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Review New Clients &rarr;</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.client-selector.select', $a['client']->id) }}">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ route('admin.end-users.index') }}">
                                    <button type="submit" class="btn btn-sm att-open">Open Clients &rarr;</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@push('head')
<style>
    /* Business-owner picker cards — subtle lift + accent */
    .picker-card { transition: transform .12s, box-shadow .12s, border-color .12s; border:1px solid #e6ebf2 !important; border-radius:14px !important; }
    .picker-card:hover { transform:translateY(-3px); box-shadow:0 16px 32px rgba(15,23,42,.12) !important; border-color:#bfdbfe !important; }
    .picker-card-name { font-weight:800; }

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

    /* Business-owner cards */
    .picker-card {
        display:flex !important; flex-direction:column; align-items:flex-start !important; text-align:left !important;
        gap:10px; padding:18px !important; background:#fff !important;
    }
    .pc-top { display:flex; align-items:center; justify-content:space-between; width:100%; gap:10px; }
    .picker-card-name { font-weight:800 !important; font-size:16.5px; color:#0f172a; line-height:1.2; }
    .picker-card-meta { color:#64748b; font-size:12.5px; font-weight:600; }
</style>
@endpush
@endsection
