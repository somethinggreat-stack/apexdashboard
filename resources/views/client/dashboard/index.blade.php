@extends('layouts.client')

@section('title', 'Dashboard')

{{-- Personal greeting in the banner (in place of the rotating quote) — dashboard only. --}}
@section('hero-welcome')
    <span class="motiv-welcome-greet">Welcome back, {{ $client->business_name }}</span>
    <span class="motiv-welcome-sub">Live monitoring of your VA's credit repair work.</span>
@endsection

@section('content')
<div class="stats-grid dash-tiles">
    <a href="{{ route('client.end-users.index') }}" class="stat-card tile-link tone-indigo">
        <div class="stat-label">In Progress</div>
        <div class="stat-value">{{ $stats['in_progress'] }}</div>
        <div class="stat-sub">Actively worked</div>
    </a>
    <a href="{{ route('client.client-list') }}" class="stat-card tile-link tone-green">
        <div class="stat-label">Done Clients</div>
        <div class="stat-value">{{ $stats['done'] }}</div>
        <div class="stat-sub">Round 1 complete</div>
    </a>
    <a href="{{ route('client.errors') }}" class="stat-card tile-link tone-red">
        <div class="stat-label">Errors</div>
        <div class="stat-value">{{ $stats['errors'] }}</div>
        <div class="stat-sub">Need a fix</div>
    </a>
    <a href="{{ route('client.hold') }}" class="stat-card tile-link tone-slate">
        <div class="stat-label">Hold / Pause</div>
        <div class="stat-value">{{ $stats['hold'] }}</div>
        <div class="stat-sub">Temporarily paused</div>
    </a>

    <div class="stat-card tone-blue">
        <div class="stat-label">My Total Clients</div>
        <div class="stat-value">{{ $stats['total_end_users'] }}</div>
        <div class="stat-sub">Across all statuses</div>
    </div>
    <a href="{{ route('client.messages.index') }}" class="stat-card tile-link tone-violet">
        <div class="stat-label">Unread Messages</div>
        <div class="stat-value">{{ $stats['unread_msgs'] }}</div>
        <div class="stat-sub">From your team</div>
    </a>
    <a href="{{ route('client.billing.index') }}" class="stat-card tile-link tone-green">
        <div class="stat-label">Total Paid</div>
        <div class="stat-value">${{ number_format($stats['total_paid'], 2) }}</div>
        <div class="stat-sub">All-time</div>
    </a>
    <a href="{{ route('client.billing.index') }}" class="stat-card tile-link tone-amber">
        <div class="stat-label">Paid This Month</div>
        <div class="stat-value">${{ number_format($stats['paid_this_month'], 2) }}</div>
        <div class="stat-sub">Since the 1st</div>
    </a>
</div>

@push('head')
<style>
    .dash-tiles { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; }
    @media (max-width:1100px){ .dash-tiles { grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:560px){ .dash-tiles { grid-template-columns:1fr; } }
    .dash-tiles .stat-card { display:block; text-decoration:none; }
    .dash-tiles .stat-sub { margin-top:4px; font-size:12px; color:#94a3b8; font-weight:500; }
    .dash-tiles .tile-link { transition: transform .12s, box-shadow .12s; }
    .dash-tiles .tile-link:hover { transform:translateY(-2px); box-shadow:0 12px 26px rgba(15,23,42,.10); }
    /* per-tile accent colours (top bar + value), overriding the nth-child defaults */
    .dash-tiles .tone-indigo::before { background:linear-gradient(90deg,#4f46e5,#818cf8) !important; }
    .dash-tiles .tone-indigo .stat-value { color:#4338ca !important; }
    .dash-tiles .tone-green::before  { background:linear-gradient(90deg,#059669,#34d399) !important; }
    .dash-tiles .tone-green .stat-value  { color:#047857 !important; }
    .dash-tiles .tone-red::before    { background:linear-gradient(90deg,#dc2626,#f87171) !important; }
    .dash-tiles .tone-red .stat-value    { color:#b91c1c !important; }
    .dash-tiles .tone-slate::before  { background:linear-gradient(90deg,#475569,#94a3b8) !important; }
    .dash-tiles .tone-slate .stat-value  { color:#475569 !important; }
    .dash-tiles .tone-blue::before   { background:linear-gradient(90deg,#2563eb,#38bdf8) !important; }
    .dash-tiles .tone-blue .stat-value   { color:#1d4ed8 !important; }
    .dash-tiles .tone-violet::before { background:linear-gradient(90deg,#7c3aed,#a78bfa) !important; }
    .dash-tiles .tone-violet .stat-value { color:#6d28d9 !important; }
    .dash-tiles .tone-amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24) !important; }
    .dash-tiles .tone-amber .stat-value  { color:#b45309 !important; }
</style>
@endpush

@if ($shift)
<div class="card shift-panel">
    <div class="card-header">
        <h2 style="margin:0;">{{ $shift['is_current'] ? 'This Shift' : $shift['label'] }}</h2>
    </div>
    <div class="shift-grid">
        <div class="shift-box"><div class="shift-val">{{ $shift['worked'] }}</div><div class="shift-label">Clients Worked</div></div>
        <div class="shift-box"><div class="shift-val">{{ $shift['rounds'] }}</div><div class="shift-label">Rounds Sent</div></div>
        <div class="shift-box"><div class="shift-val">{{ $shift['new'] }}</div><div class="shift-label">New Clients</div></div>
        <div class="shift-box shift-wait"><div class="shift-val">{{ $shift['awaiting'] }}</div><div class="shift-label">Waiting Your Approval</div></div>
        <div class="shift-box shift-near"><div class="shift-val">{{ $shift['nearing'] }}</div><div class="shift-label">Nearing Completion</div></div>
        <div class="shift-box shift-issue"><div class="shift-val">{{ $shift['issues'] }}</div><div class="shift-label">Need a Fix</div></div>
    </div>
</div>

@push('head')
<style>
    .shift-panel { margin-bottom:18px; }
    .shift-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-top:6px; }
    @media (max-width:1100px){ .shift-grid { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:560px){ .shift-grid { grid-template-columns:repeat(2,1fr); } }
    .shift-box { padding:22px 18px; border-radius:14px; border:1px solid var(--border,#e6ebf2); background:var(--surface,#fff); text-align:left; }
    .shift-val { font-size:38px; font-weight:800; line-height:1; letter-spacing:-1px; color:var(--text,#0f172a); }
    .shift-label { margin-top:8px; font-size:12.5px; font-weight:700; letter-spacing:.3px; text-transform:uppercase; color:var(--muted,#64748b); }
    .shift-wait  { background:#fffbeb; border-color:#fde68a; } .shift-wait .shift-val  { color:#b45309; }
    .shift-near  { background:#ecfdf5; border-color:#a7f3d0; } .shift-near .shift-val  { color:#047857; }
    .shift-issue { background:#fef2f2; border-color:#fecaca; } .shift-issue .shift-val { color:#b91c1c; }
</style>
@endpush
@endif

<div class="card">
    <div class="card-header">
        <h2>What Your VA Has Been Doing</h2>
        <a href="{{ route('client.client-list') }}" class="btn btn-primary">View All Clients</a>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Date</th><th>Client</th><th>Round</th><th>Week</th><th>Step Type</th></tr></thead>
        <tbody>
            @forelse ($recentSteps as $step)
                <tr>
                    <td>{{ $step->step_date?->format('M d, Y') }}</td>
                    <td><a href="{{ route('client.end-users.show', $step->end_user_id) }}">{{ $step->endUser?->full_name ?? '—' }}</a></td>
                    <td>R{{ $step->round }}</td>
                    <td>W{{ $step->week }}</td>
                    <td>{{ $step->step_type_label }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No activity yet — your VA will start logging work shortly.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
