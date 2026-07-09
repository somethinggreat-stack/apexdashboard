@extends('layouts.admin')

@section('title', 'Dashboard')

@section('topbar-content')
    <div style="flex:1;"></div>
@endsection

@section('content')
@php
    $me = Auth::guard('admin')->user();

    $dTotal   = $payment['total'];
    $doneFrac = $dTotal > 0 ? $payment['done'] / $dTotal : 0;
    $pendFrac = $dTotal > 0 ? $payment['pending'] / $dTotal : 0;
    $C        = 2 * pi() * 52;
    $doneLen  = $doneFrac * $C;
    $pendLen  = $pendFrac * $C;
    $money    = fn ($v) => '$' . number_format((float) $v, 2);

    // Sparkline helpers from $activityVals (14 days)
    $vals = $activityVals ?: [0];
    $mx   = max(1, max($vals));
    $n    = count($vals);
    $spline = function ($w, $h, $pad = 3) use ($vals, $mx, $n) {
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $n > 1 ? $pad + $i * (($w - 2 * $pad) / ($n - 1)) : $w / 2;
            $y = $h - $pad - ($v / $mx) * ($h - 2 * $pad);
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        return implode(' ', $pts);
    };
    $bigPoly = $spline(600, 90);
    $bigArea = $bigPoly . ' 597,84 3,84';
    $miniPoly = $spline(96, 30);
@endphp

<div class="dash-welcome">
    <h1>Welcome back, {{ $me?->full_name ?: 'Admin' }} 👋</h1>
    <p>Here's what's happening with your business owners today.</p>
</div>

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
    <div class="dcard">
        <div class="dcard-head">
            <div>
                <h2>Business Owners Overview</h2>
                <p class="dcard-sub">{{ $activeOwners }} active business owners</p>
            </div>
            <a href="{{ route('admin.client-selector.index') }}" class="dbtn-ghost">View All →</a>
        </div>

        <input type="text" id="boSearch" class="dsearch" placeholder="Search business owners…" onkeyup="filterBOs(this.value)">

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
                        <span class="bo-pill pill-{{ $client->status }}">{{ strtoupper($client->status) }}</span>
                        <span class="bo-menu">⋮</span>
                    </button>
                </form>
            @endforeach
        </div>
        <div id="boEmpty" class="dempty" style="display:none;">No business owners match.</div>
    </div>

    <div class="dcard">
        <div class="dcard-head">
            <div><h2>Needs Attention</h2></div>
            @if (!empty($attention))<span class="att-chip">{{ count($attention) }}</span>@endif
        </div>
        <div class="att-colhead"><span>Business Owner</span><span>Status</span><span class="att-act-h">Action</span></div>
        <div class="att-list">
        @forelse ($attention as $a)
            @php $bo = $a['client']; @endphp
            <div class="att-row">
                <span class="att-bo">{{ $bo->business_name }}</span>
                <span class="att-badges">
                    @if ($a['pending'])<span class="ab ab-blue">{{ $a['pending'] }} new</span>@endif
                    @if ($a['incomplete'])<span class="ab ab-amber">{{ $a['incomplete'] }} incomplete</span>@endif
                    @if ($a['overdue'])<span class="ab ab-red">{{ $a['overdue'] }} overdue</span>@endif
                </span>
                <form method="POST" action="{{ route('admin.client-selector.select', $bo->id) }}" class="att-act">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ $a['pending'] ? route('admin.new-clients') : route('admin.end-users.index') }}">
                    <button type="submit" class="{{ $a['pending'] ? 'dbtn-primary' : 'dbtn-soft' }}">{{ $a['pending'] ? 'Review New Clients →' : 'Open Clients →' }}</button>
                </form>
            </div>
        @empty
            <div class="dempty">Everything looks good — nothing needs attention.</div>
        @endforelse
        </div>
    </div>
</div>

{{-- ===================== Analytics + Payments ===================== --}}
<div class="dash-grid dash-grid-2">
    <div class="dcard">
        <div class="dcard-head"><div><h2>Analytics Overview</h2></div></div>
        <div class="an-tiles">
            <div class="an-tile">
                <span class="an-lbl">Total Clients</span><span class="an-val">{{ number_format($totalClients) }}</span>
                <svg class="an-spark" viewBox="0 0 96 30" preserveAspectRatio="none"><polyline points="{{ $miniPoly }}" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="an-tile">
                <span class="an-lbl">Active Owners</span><span class="an-val">{{ $activeOwners }}</span>
                <svg class="an-spark" viewBox="0 0 96 30" preserveAspectRatio="none"><polyline points="{{ $miniPoly }}" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="an-tile">
                <span class="an-lbl">New This Month</span><span class="an-val">{{ $newThisMonth }}</span>
                <svg class="an-spark" viewBox="0 0 96 30" preserveAspectRatio="none"><polyline points="{{ $miniPoly }}" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="an-tile">
                <span class="an-lbl">On-Track Rate</span><span class="an-val">{{ $onTrackRate }}%</span>
                <svg class="an-spark" viewBox="0 0 96 30" preserveAspectRatio="none"><polyline points="{{ $miniPoly }}" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
        <div class="tb-head">
            <strong>New Clients Over Time</strong>
            <div class="tb-tabs">
                <button type="button" class="tb-tab is-on" onclick="tbShow('month', this)">Month</button>
                <button type="button" class="tb-tab" onclick="tbShow('week', this)">Week</button>
                <button type="button" class="tb-tab" onclick="tbShow('day', this)">Day</button>
            </div>
        </div>
        @foreach (['month' => $byMonth, 'week' => $byWeek, 'day' => $byDay] as $range => $rows)
            @php
                $counts = array_column($rows, 'count');
                $mx  = max(1, (int) max($counts));
                $tot = array_sum($counts);
                $cap = ['month' => 'last 12 months', 'week' => 'last 12 weeks', 'day' => 'last 30 days'][$range];
            @endphp
            <div class="tb-chart" id="tb-{{ $range }}" @if ($range !== 'month') style="display:none;" @endif>
                <div class="tb-total"><strong>{{ number_format($tot) }}</strong> new client{{ $tot === 1 ? '' : 's' }} · {{ $cap }}</div>
                <div class="tb-plot">
                    <div class="tb-grid"></div>
                    <div class="tb-bars">
                        @foreach ($rows as $r)
                            @php $h = $r['count'] > 0 ? max(4, (int) round($r['count'] / $mx * 100)) : 0; @endphp
                            <div class="tb-col" title="{{ $r['label'] }} {{ $r['sub'] ?? '' }} — {{ $r['count'] }} new">
                                <span class="tb-num">{{ $r['count'] ?: '' }}</span>
                                <span class="tb-track"><span class="tb-bar" style="height:{{ $h }}%"></span></span>
                                <span class="tb-labels">
                                    <span class="tb-lbl">{{ $r['label'] }}</span>
                                    <span class="tb-sub">{{ $r['sub'] ?? '' }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dcard">
        <div class="dcard-head"><div><h2>Payments Overview</h2></div><a href="{{ route('admin.client-selector.index') }}" class="dbtn-ghost">View Financial Report →</a></div>
        {{-- Payments received over time --}}
        <div class="tb-head">
            <strong>Payments Received</strong>
            <div class="tb-tabs">
                <button type="button" class="tb-tab is-on" onclick="pbShow('pmonth', this)">This Month</button>
                <button type="button" class="tb-tab" onclick="pbShow('pweek', this)">This Week</button>
                <button type="button" class="tb-tab" onclick="pbShow('pyear', this)">Last 12 Months</button>
            </div>
        </div>
        @foreach (['pmonth' => [$payMonth, 'this month'], 'pweek' => [$payWeek, 'this week'], 'pyear' => [$payYear, 'last 12 months']] as $rk => $rv)
            @php
                [$rows, $cap] = $rv;
                $amts = array_column($rows, 'amount');
                $mxA  = max(0.01, (float) max($amts));
                $totA = array_sum($amts);
                $totC = array_sum(array_column($rows, 'count'));
            @endphp
            <div class="tb-chart" id="tb-{{ $rk }}" @if ($rk !== 'pmonth') style="display:none;" @endif>
                <div class="tb-total"><strong>{{ $money($totA) }}</strong> received · {{ $totC }} payment{{ $totC === 1 ? '' : 's' }} · {{ $cap }}</div>
                <div class="tb-plot">
                    <div class="tb-grid"></div>
                    <div class="tb-bars">
                        @foreach ($rows as $r)
                            @php
                                $h = $r['amount'] > 0 ? max(4, (int) round($r['amount'] / $mxA * 100)) : 0;
                                $short = $r['amount'] >= 1000 ? rtrim(rtrim(number_format($r['amount'] / 1000, 1), '0'), '.') . 'k' : (int) round($r['amount']);
                            @endphp
                            <div class="tb-col" title="{{ $r['label'] }} {{ $r['sub'] }} — {{ $money($r['amount']) }} ({{ $r['count'] }} payment{{ $r['count'] === 1 ? '' : 's' }})">
                                <span class="tb-num">{{ $r['amount'] > 0 ? $short : '' }}</span>
                                <span class="tb-track"><span class="tb-bar tb-bar-green" style="height:{{ $h }}%"></span></span>
                                <span class="tb-labels">
                                    <span class="tb-lbl">{{ $r['label'] }}</span>
                                    <span class="tb-sub">{{ $r['sub'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="pay-2col">
        <div class="pay-col-left">
        <div class="pay-wrap">
            <div class="donut">
                <svg viewBox="0 0 132 132" width="140" height="140">
                    <circle cx="66" cy="66" r="52" fill="none" stroke="#eef2f7" stroke-width="14"/>
                    <circle cx="66" cy="66" r="52" fill="none" stroke="#22c55e" stroke-width="14" stroke-linecap="round"
                            stroke-dasharray="{{ round($doneLen, 2) }} {{ round($C, 2) }}" transform="rotate(-90 66 66)"/>
                    <circle cx="66" cy="66" r="52" fill="none" stroke="#f59e0b" stroke-width="14" stroke-linecap="round"
                            stroke-dasharray="{{ round($pendLen, 2) }} {{ round($C, 2) }}" stroke-dashoffset="{{ round(-$doneLen, 2) }}" transform="rotate(-90 66 66)"/>
                </svg>
                <div class="donut-center"><span class="donut-total">{{ $money($dTotal) }}</span><span class="donut-cap">Total</span></div>
            </div>
            <div class="pay-legend">
                <div class="pl-row"><span class="pl-dot" style="background:#22c55e"></span><span class="pl-name">Total Collected</span><span class="pl-val">{{ $money($payment['done']) }}</span><span class="pl-pct">{{ round($doneFrac * 100, 1) }}%</span></div>
                <div class="pl-row"><span class="pl-dot" style="background:#f59e0b"></span><span class="pl-name">Total Pending</span><span class="pl-val">{{ $money($payment['pending']) }}</span><span class="pl-pct">{{ round($pendFrac * 100, 1) }}%</span></div>
                <div class="pl-row"><span class="pl-dot" style="background:#3b82f6"></span><span class="pl-name">Lifetime Billed</span><span class="pl-val">{{ $money($dTotal) }}</span><span class="pl-pct">100%</span></div>
            </div>
        </div>
        </div>{{-- /pay-col-left --}}

        <div class="pay-col-right">
            <div class="rp-head"><strong>All Payments</strong><span class="dcard-sub">{{ number_format($recent->count()) }} recorded</span></div>
            <div class="rp-list">
                @forelse ($recent as $p)
                    <div class="rp-item">
                        <span class="rp-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span>
                        <span class="rp-body">
                            <span class="rp-name">{{ $p->endUser?->client?->business_name ?? '—' }}</span>
                            <span class="rp-sub">{{ $p->endUser?->full_name ?? 'Payment received' }} · Round {{ $p->round }}</span>
                        </span>
                        <span class="rp-amt">{{ $money($p->amount) }}</span>
                        <span class="rp-date">{{ $p->paid_at?->format('M j, Y') }}</span>
                        <span class="rp-badge">Completed</span>
                    </div>
                @empty
                    <div class="dempty">No payments recorded yet.</div>
                @endforelse
            </div>
        </div>
        </div>{{-- /pay-2col --}}
    </div>
</div>

@push('head')
<style>
    .content { background:#f6f8fc; }
    body.admin-body .main { background:#f6f8fc; }

    .dash-welcome { margin-bottom:16px; }
    .dash-welcome h1 { margin:0; font-size:22px; font-weight:800; color:#0f172a; }
    .dash-welcome p { margin:3px 0 0; font-size:13px; color:#94a3b8; }

    /* Stat cards — top accent, compact */
    /* 3 per row → two rows of stat cards */
    .dash-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    @media (max-width:900px){ .dash-stats { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:560px){ .dash-stats { grid-template-columns:1fr; } }
    .dstat { position:relative; background:#fff; border:1px solid #eef1f6; border-radius:14px; padding:14px 15px; box-shadow:0 1px 2px rgba(15,23,42,.04); overflow:hidden; }
    .dstat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--c,#3b82f6); }
    .dstat-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .dstat-label { font-size:12px; font-weight:600; color:#64748b; }
    .dstat-ico { width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:var(--cbg,#eff6ff); color:var(--c,#3b82f6); flex:none; }
    .dstat-ico svg { width:16px; height:16px; }
    .dstat-val { font-size:23px; font-weight:800; color:var(--c,#0f172a); margin:7px 0 2px; line-height:1.1; letter-spacing:-.5px; }
    .dstat-sub { font-size:11px; color:#94a3b8; }
    .ds-blue{ --c:#2563eb; --cbg:#eff6ff; } .ds-amber{ --c:#d97706; --cbg:#fff7ed; }
    .ds-red{ --c:#dc2626; --cbg:#fef2f2; } .ds-green{ --c:#059669; --cbg:#ecfdf5; }
    .ds-violet{ --c:#7c3aed; --cbg:#f5f3ff; } .ds-indigo{ --c:#2563eb; --cbg:#eef2ff; }

    /* Cards + layout */
    .dcard { background:#fff; border:1px solid #eef1f6; border-radius:16px; padding:18px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .dash-grid { display:grid; grid-template-columns:1.55fr 1fr; gap:16px; margin-bottom:16px; align-items:start; }
    /* Analytics + Payments: each full width, stacked (not split in half) */
    .dash-grid-2 { grid-template-columns:1fr; }
    @media (max-width:1024px){ .dash-grid { grid-template-columns:1fr; } }
    .dcard-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px; }
    .dcard-head h2 { margin:0; font-size:16px; font-weight:800; color:#0f172a; }
    .dcard-sub { margin:3px 0 0; font-size:12px; color:#94a3b8; }

    .dbtn-ghost { font-size:12px; font-weight:700; color:#2563eb; background:#fff; border:1px solid #e2e8f0; border-radius:9px; padding:6px 11px; text-decoration:none; white-space:nowrap; }
    .dbtn-ghost:hover { background:#f8fafc; text-decoration:none; }
    .dchip { font-size:12px; font-weight:600; color:#475569; background:#f1f5f9; border-radius:9px; padding:6px 11px; }
    .dbtn-primary { font-size:12px; font-weight:700; color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8); border:0; border-radius:9px; padding:7px 12px; cursor:pointer; white-space:nowrap; }
    .dbtn-primary:hover { filter:brightness(1.05); }
    .dbtn-soft { font-size:12px; font-weight:700; color:#475569; background:#fff; border:1px solid #e2e8f0; border-radius:9px; padding:7px 12px; cursor:pointer; white-space:nowrap; }
    .dbtn-soft:hover { background:#f8fafc; }
    .dempty { padding:16px; text-align:center; font-size:13px; color:#94a3b8; }

    /* BO grid */
    .dsearch { width:100%; padding:9px 12px; border:1px solid #e6ebf2; border-radius:10px; font-size:13px; margin-bottom:12px; background:#f8fafc; }
    .bo-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
    @media (max-width:600px){ .bo-grid { grid-template-columns:1fr; } }
    .bo-card-form { margin:0; }
    .bo-card { width:100%; display:flex; align-items:center; gap:10px; text-align:left; background:#fff; border:1px solid #eef1f6; border-radius:12px; padding:10px 11px; cursor:pointer; transition:transform .12s, box-shadow .12s, border-color .12s; }
    .bo-card:hover { transform:translateY(-2px); box-shadow:0 8px 16px rgba(15,23,42,.07); border-color:#bfdbfe; }
    .bo-ico { width:30px; height:30px; border-radius:9px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; flex:none; }
    .bo-ico svg { width:15px; height:15px; }
    .bo-body { display:flex; flex-direction:column; min-width:0; flex:1; gap:1px; }
    .bo-name { font-weight:700; font-size:13px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .bo-meta { font-size:11px; color:#64748b; }
    .bo-pill { flex:none; font-size:8.5px; font-weight:800; letter-spacing:.04em; padding:2px 7px; border-radius:999px; background:#dcfce7; color:#166534; }
    .bo-pill.pill-inactive, .bo-pill.pill-paused { background:#e2e8f0; color:#475569; }
    .bo-menu { flex:none; color:#cbd5e1; font-weight:800; font-size:15px; line-height:1; padding:0 2px; }

    /* Needs attention */
    .att-chip { background:#fee2e2; color:#b91c1c; font-weight:700; font-size:12px; border-radius:999px; padding:2px 10px; }
    .att-colhead { display:grid; grid-template-columns:1.1fr 1.3fr auto; gap:10px; font-size:10.5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#94a3b8; padding:0 0 8px; border-bottom:1px solid #f1f5f9; }
    .att-act-h { text-align:right; }
    .att-row { display:grid; grid-template-columns:1.1fr 1.3fr auto; gap:10px; align-items:center; padding:11px 0; border-bottom:1px solid #f4f6fa; }
    .att-row:last-child { border-bottom:0; }
    .att-bo { font-weight:700; font-size:13px; color:#0f172a; }
    .att-badges { display:flex; flex-wrap:wrap; gap:5px; }
    .ab { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:2px 9px; border-radius:999px; white-space:nowrap; }
    .ab::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .ab-blue{ background:#e0f2fe; color:#0369a1; } .ab-amber{ background:#fef3c7; color:#b45309; } .ab-red{ background:#fee2e2; color:#dc2626; }
    .att-act { margin:0; justify-self:end; }

    /* Analytics */
    .an-tiles { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px; }
    @media (max-width:560px){ .an-tiles { grid-template-columns:repeat(2,1fr); } }
    .an-tile { position:relative; background:#f8fafc; border:1px solid #eef1f6; border-radius:12px; padding:11px 12px 6px; display:flex; flex-direction:column; gap:2px; overflow:hidden; }
    .an-lbl { font-size:11px; font-weight:600; color:#64748b; }
    .an-val { font-size:19px; font-weight:800; color:#0f172a; }
    .an-spark { width:100%; height:26px; margin-top:2px; }
    .an-chart-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
    .an-chart-head strong { font-size:13px; color:#0f172a; }
    .an-chart { width:100%; }

    /* New Clients Over Time — month / week / day bars */
    .tb-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin:2px 0 10px; }
    .tb-head strong { font-size:13px; color:#0f172a; }
    .tb-tabs { display:flex; gap:3px; background:#f1f5f9; padding:3px; border-radius:10px; }
    .tb-tab { border:0; background:transparent; font-size:12px; font-weight:700; color:#64748b; padding:6px 13px; border-radius:8px; cursor:pointer; }
    .tb-tab.is-on { background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(15,23,42,.10); }
    .tb-total { font-size:11.5px; color:#94a3b8; margin:2px 0 10px; }
    .tb-total strong { color:#0f172a; font-size:14px; }

    /* Plot: gridlines + baseline + a light track behind every column */
    .tb-plot { position:relative; }
    .tb-grid { position:absolute; left:0; right:0; top:15px; bottom:32px; pointer-events:none;
        background-image:linear-gradient(to bottom, #eef2f7 1px, transparent 1px); background-size:100% 25%; }
    .tb-bars { position:relative; display:flex; align-items:stretch; gap:5px; height:196px; overflow-x:auto; }
    .tb-bars::after { content:''; position:absolute; left:0; right:0; bottom:32px; height:1px; background:#e2e8f0; }
    .tb-col { flex:1 1 0; min-width:18px; display:flex; flex-direction:column; align-items:center; }
    .tb-num { height:15px; font-size:9.5px; font-weight:800; color:#0f172a; letter-spacing:-.2px; white-space:nowrap; }
    .tb-track { flex:1; width:100%; max-width:28px; display:flex; align-items:flex-end;
        background:#f5f7fa; border-radius:7px; overflow:hidden; }
    .tb-bar { width:100%; border-radius:7px 7px 0 0; background:linear-gradient(180deg,#60a5fa,#2563eb); transition:filter .12s; }
    .tb-col:hover .tb-track { background:#e9eef5; }
    .tb-col:hover .tb-bar { filter:brightness(1.08); }
    .tb-labels { height:32px; display:flex; flex-direction:column; align-items:center; padding-top:7px; }
    .tb-lbl { font-size:9.5px; font-weight:700; color:#475569; white-space:nowrap; line-height:1.2; }
    .tb-sub { font-size:8.5px; color:#cbd5e1; line-height:1.2; }

    /* Payments */
    .pay-wrap { display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:6px; }
    .donut { position:relative; flex:none; }
    .donut-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .donut-total { font-size:15px; font-weight:800; color:#0f172a; }
    .donut-cap { font-size:10.5px; color:#94a3b8; }
    .pay-legend { flex:1; min-width:170px; display:flex; flex-direction:column; gap:9px; }
    .pl-row { display:flex; align-items:center; gap:8px; font-size:12.5px; }
    .pl-dot { width:10px; height:10px; border-radius:50%; flex:none; }
    .pl-name { color:#475569; flex:1; }
    .pl-val { font-weight:800; color:#0f172a; }
    .pl-pct { color:#94a3b8; font-size:11.5px; width:44px; text-align:right; }

    .tb-bar-green { background:linear-gradient(180deg,#34d399,#059669); }

    /* Payments: two columns — donut/legend left, full payments list right */
    .pay-2col { display:grid; grid-template-columns:minmax(250px, .85fr) 1.15fr; gap:22px; align-items:start;
        margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9; }
    @media (max-width:900px){ .pay-2col { grid-template-columns:1fr; } }
    .pay-2col .pay-wrap { flex-direction:column; align-items:stretch; gap:14px; margin-bottom:0; }
    .pay-2col .donut { align-self:center; }
    .pay-2col .pay-legend { min-width:0; }
    .rp-list { max-height:430px; overflow-y:auto; padding-right:4px; }
    .pay-col-right { border-left:1px solid #f1f5f9; padding-left:20px; }
    @media (max-width:900px){ .pay-col-right { border-left:0; padding-left:0; border-top:1px solid #f1f5f9; padding-top:14px; } }

    .rp-head { display:flex; align-items:center; justify-content:space-between; margin:0 0 6px; }
    .rp-head strong { font-size:13px; color:#0f172a; }
    .rp-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-top:1px solid #f6f8fb; }
    .rp-item:first-of-type { border-top:0; }
    .rp-ico { width:30px; height:30px; border-radius:9px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; flex:none; }
    .rp-ico svg { width:15px; height:15px; }
    .rp-body { display:flex; flex-direction:column; min-width:0; flex:1; }
    .rp-name { font-weight:700; font-size:12.5px; color:#0f172a; }
    .rp-sub { font-size:11px; color:#94a3b8; }
    .rp-amt { font-weight:800; color:#059669; font-size:12.5px; white-space:nowrap; }
    .rp-date { font-size:11px; color:#94a3b8; white-space:nowrap; }
    .rp-badge { flex:none; font-size:10px; font-weight:700; color:#166534; background:#dcfce7; padding:3px 9px; border-radius:999px; }
    @media (max-width:520px){ .rp-date { display:none; } }
</style>
@endpush

@push('scripts')
<script>
function tbToggle(ids, show, btn) {
    ids.forEach(function (r) {
        var el = document.getElementById('tb-' + r);
        if (el) { el.style.display = (r === show) ? '' : 'none'; }
    });
    if (btn && btn.parentNode) {
        btn.parentNode.querySelectorAll('.tb-tab').forEach(function (b) { b.classList.remove('is-on'); });
        btn.classList.add('is-on');
    }
}
// New Clients chart
window.tbShow = function (range, btn) { tbToggle(['month', 'week', 'day'], range, btn); };
// Payments Received chart
window.pbShow = function (range, btn) { tbToggle(['pmonth', 'pweek', 'pyear'], range, btn); };

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
