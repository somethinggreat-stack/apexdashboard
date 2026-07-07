@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $dTotal   = $payment['total'];
    $doneFrac = $dTotal > 0 ? $payment['done'] / $dTotal : 0;
    $pendFrac = $dTotal > 0 ? $payment['pending'] / $dTotal : 0;
    $C        = 2 * pi() * 54;
    $doneLen  = $doneFrac * $C;
    $pendLen  = $pendFrac * $C;
    $money    = fn ($v) => '$' . number_format((float) $v, 2);

    // Sparkline points from $activityVals (14 days)
    $vals = $activityVals ?: [0];
    $mx   = max(1, max($vals));
    $n    = count($vals);
    $w = 600; $h = 90; $pad = 6;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? $pad + $i * (($w - 2 * $pad) / ($n - 1)) : $w / 2;
        $y = $h - $pad - ($v / $mx) * ($h - 2 * $pad);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $poly = implode(' ', $pts);
    $area = $poly . ' ' . ($w - $pad) . ',' . ($h - $pad) . ' ' . $pad . ',' . ($h - $pad);
@endphp

{{-- ===================== Top stat cards ===================== --}}
<div class="dash-stats">
    <div class="dstat ds-blue">
        <div class="dstat-top"><span class="dstat-label">New Intake Clients</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg></span></div>
        <div class="dstat-val">{{ $sumPending }}</div>
        <div class="dstat-sub">Awaiting review, all owners</div>
    </div>
    <div class="dstat ds-amber">
        <div class="dstat-top"><span class="dstat-label">Incomplete Logs</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span></div>
        <div class="dstat-val">{{ $sumIncomplete }}</div>
        <div class="dstat-sub">Weekly logs to finish</div>
    </div>
    <div class="dstat ds-red">
        <div class="dstat-top"><span class="dstat-label">Overdue Rounds</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span></div>
        <div class="dstat-val">{{ $sumOverdue }}</div>
        <div class="dstat-sub">Past their round date</div>
    </div>
    <div class="dstat ds-green">
        <div class="dstat-top"><span class="dstat-label">Total Collected</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span></div>
        <div class="dstat-val">{{ $money($payment['done']) }}</div>
        <div class="dstat-sub">Received to date</div>
    </div>
    <div class="dstat ds-violet">
        <div class="dstat-top"><span class="dstat-label">Total Pending</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M17 22v-4.17a2 2 0 0 0-.59-1.42L12 12l-4.41 4.41A2 2 0 0 0 7 17.83V22M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2"/></svg></span></div>
        <div class="dstat-val">{{ $money($payment['pending']) }}</div>
        <div class="dstat-sub">Still to collect</div>
    </div>
    <div class="dstat ds-indigo">
        <div class="dstat-top"><span class="dstat-label">Lifetime Billed</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span></div>
        <div class="dstat-val">{{ $money($payment['total']) }}</div>
        <div class="dstat-sub">Collected + pending</div>
    </div>
</div>

{{-- ===================== BO Overview + Needs Attention ===================== --}}
<div class="dash-grid">
    <div class="card dash-panel">
        <div class="card-header">
            <div>
                <h2 style="margin:0;">Business Owners Overview</h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">{{ $activeOwners }} business owner{{ $activeOwners === 1 ? '' : 's' }}</p>
            </div>
            <a href="{{ route('admin.client-selector.index') }}" class="btn btn-secondary btn-sm">View All →</a>
        </div>

        <input type="text" id="boSearch" class="dash-search" placeholder="Search business owners…" onkeyup="filterBOs(this.value)">

        <div class="bo-grid" id="boGrid">
            @foreach ($clients as $client)
                <form method="POST" action="{{ route('admin.client-selector.select', $client->id) }}" class="bo-card-form" data-name="{{ strtolower($client->business_name) }}">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ route('admin.end-users.index') }}">
                    <button type="submit" class="bo-card">
                        <span class="bo-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>
                        <span class="bo-body">
                            <span class="bo-name">{{ $client->business_name }}</span>
                            <span class="bo-meta">{{ $client->end_users_count }} clients</span>
                        </span>
                        <span class="pill pill-{{ $client->status }} bo-pill">{{ $client->status }}</span>
                    </button>
                </form>
            @endforeach
        </div>
        <div id="boEmpty" class="muted" style="display:none; padding:16px; text-align:center; font-size:13px;">No business owners match.</div>
    </div>

    <div class="card dash-panel">
        <div class="card-header">
            <div><h2 style="margin:0;">Needs Attention</h2></div>
            @if (!empty($attention))<span class="att-chip">{{ count($attention) }}</span>@endif
        </div>
        @forelse ($attention as $a)
            @php
                $bo = $a['client'];
                $reviewUrl = route('admin.client-selector.select', $bo->id);
            @endphp
            <div class="att-item">
                <div class="att-l">
                    <span class="att-bo">{{ $bo->business_name }}</span>
                    <span class="att-badges">
                        @if ($a['pending'])<span class="ab ab-blue">{{ $a['pending'] }} new</span>@endif
                        @if ($a['incomplete'])<span class="ab ab-amber">{{ $a['incomplete'] }} incomplete</span>@endif
                        @if ($a['overdue'])<span class="ab ab-red">{{ $a['overdue'] }} overdue</span>@endif
                    </span>
                </div>
                <form method="POST" action="{{ $reviewUrl }}">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ $a['pending'] ? route('admin.new-clients') : route('admin.end-users.index') }}">
                    <button type="submit" class="btn btn-sm {{ $a['pending'] ? 'btn-primary' : 'btn-secondary' }}">{{ $a['pending'] ? 'Review New Clients →' : 'Open Clients →' }}</button>
                </form>
            </div>
        @empty
            <div class="muted" style="padding:16px; text-align:center; font-size:13px;">Everything looks good — nothing needs attention.</div>
        @endforelse
    </div>
</div>

{{-- ===================== Analytics + Payments ===================== --}}
<div class="dash-grid">
    <div class="card dash-panel">
        <div class="card-header"><div><h2 style="margin:0;">Analytics Overview</h2></div></div>
        <div class="an-tiles">
            <div class="an-tile"><span class="an-lbl">Total Clients</span><span class="an-val">{{ number_format($totalClients) }}</span></div>
            <div class="an-tile"><span class="an-lbl">Active Owners</span><span class="an-val">{{ $activeOwners }}</span></div>
            <div class="an-tile"><span class="an-lbl">New This Month</span><span class="an-val">{{ $newThisMonth }}</span></div>
            <div class="an-tile"><span class="an-lbl">On-Track Rate</span><span class="an-val">{{ $onTrackRate }}%</span></div>
        </div>
        <div class="an-chart-head"><strong>Client Activity</strong><span class="muted" style="font-size:12px;">New clients · last 14 days</span></div>
        <div class="an-chart">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" width="100%" height="90">
                <defs><linearGradient id="actFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3b82f6" stop-opacity="0.28"/><stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/></linearGradient></defs>
                <polygon points="{{ $area }}" fill="url(#actFill)"/>
                <polyline points="{{ $poly }}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
        </div>
    </div>

    <div class="card dash-panel">
        <div class="card-header">
            <div><h2 style="margin:0;">Payments Overview</h2></div>
            <a href="{{ route('admin.client-selector.index') }}" class="btn btn-secondary btn-sm">Details →</a>
        </div>
        <div class="pay-wrap">
            <div class="donut">
                <svg viewBox="0 0 140 140" width="150" height="150">
                    <circle cx="70" cy="70" r="54" fill="none" stroke="#eef2f7" stroke-width="16"/>
                    <circle cx="70" cy="70" r="54" fill="none" stroke="#22c55e" stroke-width="16" stroke-linecap="round"
                            stroke-dasharray="{{ round($doneLen, 2) }} {{ round($C, 2) }}" transform="rotate(-90 70 70)"/>
                    <circle cx="70" cy="70" r="54" fill="none" stroke="#f59e0b" stroke-width="16" stroke-linecap="round"
                            stroke-dasharray="{{ round($pendLen, 2) }} {{ round($C, 2) }}" stroke-dashoffset="{{ round(-$doneLen, 2) }}" transform="rotate(-90 70 70)"/>
                </svg>
                <div class="donut-center"><span class="donut-total">{{ $money($dTotal) }}</span><span class="donut-cap">Total</span></div>
            </div>
            <div class="pay-legend">
                <div class="pl-row"><span class="pl-dot" style="background:#22c55e"></span><span class="pl-name">Total Collected</span><span class="pl-val">{{ $money($payment['done']) }}</span><span class="pl-pct">{{ round($doneFrac * 100) }}%</span></div>
                <div class="pl-row"><span class="pl-dot" style="background:#f59e0b"></span><span class="pl-name">Total Pending</span><span class="pl-val">{{ $money($payment['pending']) }}</span><span class="pl-pct">{{ round($pendFrac * 100) }}%</span></div>
                <div class="pl-row"><span class="pl-dot" style="background:#3b82f6"></span><span class="pl-name">Lifetime Billed</span><span class="pl-val">{{ $money($dTotal) }}</span><span class="pl-pct">100%</span></div>
            </div>
        </div>

        <div class="rp-head"><strong>Recent Payments</strong></div>
        @forelse ($recent as $p)
            <div class="rp-item">
                <span class="rp-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span>
                <span class="rp-body">
                    <span class="rp-name">{{ $p->endUser?->client?->business_name ?? '—' }}</span>
                    <span class="rp-sub muted">Payment received{{ $p->endUser ? ' · '.$p->endUser->full_name : '' }}</span>
                </span>
                <span class="rp-amt">{{ $money($p->amount) }}</span>
                <span class="rp-date muted">{{ $p->paid_at?->format('M j, Y') }}</span>
                <span class="pill pill-active rp-badge">Completed</span>
            </div>
        @empty
            <div class="muted" style="padding:14px; text-align:center; font-size:13px;">No payments recorded yet.</div>
        @endforelse
    </div>
</div>

@push('head')
<style>
    .content { background: radial-gradient(1100px circle at 100% -10%, #eef4ff 0%, transparent 42%), radial-gradient(900px circle at -5% 120%, #eefaf3 0%, transparent 44%); }

    /* Stat cards */
    .dash-stats { display:grid; grid-template-columns:repeat(6, 1fr); gap:14px; margin-bottom:18px; }
    @media (max-width:1200px){ .dash-stats { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:640px){ .dash-stats { grid-template-columns:repeat(2,1fr); } }
    .dstat { background:#fff; border:1px solid #eef1f6; border-left:4px solid var(--c,#3b82f6); border-radius:14px; padding:16px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .dstat-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .dstat-label { font-size:12px; font-weight:700; color:#64748b; }
    .dstat-ico { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--cbg,#eff6ff); color:var(--c,#3b82f6); flex:none; }
    .dstat-ico svg { width:18px; height:18px; }
    .dstat-val { font-size:26px; font-weight:800; color:var(--c,#0f172a); margin:8px 0 2px; line-height:1.1; }
    .dstat-sub { font-size:11.5px; color:#94a3b8; }
    .ds-blue{ --c:#2563eb; --cbg:#eff6ff; } .ds-amber{ --c:#d97706; --cbg:#fff7ed; }
    .ds-red{ --c:#dc2626; --cbg:#fef2f2; } .ds-green{ --c:#059669; --cbg:#ecfdf5; }
    .ds-violet{ --c:#7c3aed; --cbg:#f5f3ff; } .ds-indigo{ --c:#2563eb; --cbg:#eef2ff; }

    /* Two-column layout */
    .dash-grid { display:grid; grid-template-columns:1.35fr 1fr; gap:18px; margin-bottom:18px; align-items:start; }
    @media (max-width:1024px){ .dash-grid { grid-template-columns:1fr; } }
    .dash-panel { margin-bottom:0; }

    /* BO overview */
    .dash-search { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px; margin-bottom:14px; background:#f8fafc; }
    .bo-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
    @media (max-width:560px){ .bo-grid { grid-template-columns:1fr; } }
    .bo-card-form { margin:0; }
    .bo-card { width:100%; display:flex; align-items:center; gap:10px; text-align:left; background:#fff; border:1px solid #eef1f6; border-radius:12px; padding:11px 12px; cursor:pointer; transition:transform .12s, box-shadow .12s, border-color .12s; }
    .bo-card:hover { transform:translateY(-2px); box-shadow:0 8px 18px rgba(15,23,42,.08); border-color:#bfdbfe; }
    .bo-ico { width:34px; height:34px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; flex:none; }
    .bo-ico svg { width:17px; height:17px; }
    .bo-body { display:flex; flex-direction:column; min-width:0; flex:1; }
    .bo-name { font-weight:700; font-size:13.5px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .bo-meta { font-size:11.5px; color:#64748b; }
    .bo-pill { flex:none; font-size:9px; }

    /* Needs attention */
    .att-chip { background:#fee2e2; color:#b91c1c; font-weight:700; font-size:12px; border-radius:999px; padding:2px 10px; }
    .att-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 0; border-top:1px solid #f1f5f9; }
    .att-item:first-of-type { border-top:0; }
    .att-l { display:flex; flex-direction:column; gap:5px; min-width:0; }
    .att-bo { font-weight:700; font-size:13.5px; color:#0f172a; }
    .att-badges { display:flex; flex-wrap:wrap; gap:5px; }
    .ab { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:2px 9px; border-radius:999px; white-space:nowrap; }
    .ab::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .ab-blue{ background:#e0f2fe; color:#0369a1; } .ab-amber{ background:#fef3c7; color:#b45309; } .ab-red{ background:#fee2e2; color:#dc2626; }
    .att-item .btn { white-space:nowrap; }

    /* Analytics */
    .an-tiles { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px; }
    @media (max-width:560px){ .an-tiles { grid-template-columns:repeat(2,1fr); } }
    .an-tile { background:#f8fafc; border:1px solid #eef1f6; border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:3px; }
    .an-lbl { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; }
    .an-val { font-size:20px; font-weight:800; color:#0f172a; }
    .an-chart-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
    .an-chart { width:100%; }

    /* Payments */
    .pay-wrap { display:flex; align-items:center; gap:18px; flex-wrap:wrap; margin-bottom:8px; }
    .donut { position:relative; flex:none; }
    .donut-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .donut-total { font-size:16px; font-weight:800; color:#0f172a; }
    .donut-cap { font-size:11px; color:#94a3b8; }
    .pay-legend { flex:1; min-width:180px; display:flex; flex-direction:column; gap:10px; }
    .pl-row { display:flex; align-items:center; gap:8px; font-size:13px; }
    .pl-dot { width:10px; height:10px; border-radius:50%; flex:none; }
    .pl-name { color:#475569; flex:1; }
    .pl-val { font-weight:800; color:#0f172a; }
    .pl-pct { color:#94a3b8; font-size:12px; width:42px; text-align:right; }

    .rp-head { margin:14px 0 4px; padding-top:14px; border-top:1px solid #f1f5f9; }
    .rp-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-top:1px solid #f6f8fb; }
    .rp-item:first-of-type { border-top:0; }
    .rp-ico { width:32px; height:32px; border-radius:9px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; flex:none; }
    .rp-ico svg { width:16px; height:16px; }
    .rp-body { display:flex; flex-direction:column; min-width:0; flex:1; }
    .rp-name { font-weight:700; font-size:13px; color:#0f172a; }
    .rp-sub { font-size:11.5px; }
    .rp-amt { font-weight:800; color:#0f172a; font-size:13px; white-space:nowrap; }
    .rp-date { font-size:11.5px; white-space:nowrap; }
    .rp-badge { flex:none; }
    @media (max-width:520px){ .rp-date { display:none; } }
</style>
@endpush

@push('scripts')
<script>
window.filterBOs = function (q) {
    q = (q || '').trim().toLowerCase();
    var any = false;
    document.querySelectorAll('#boGrid .bo-card-form').forEach(function (el) {
        var show = !q || (el.getAttribute('data-name') || '').indexOf(q) !== -1;
        el.style.display = show ? '' : 'none';
        if (show) any = true;
    });
    document.getElementById('boEmpty').style.display = any ? 'none' : 'block';
};
</script>
@endpush
@endsection
