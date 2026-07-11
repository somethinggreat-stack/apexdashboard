@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Dashboard')

{{-- No topbar on this page: the "Welcome …" header below already titles it.
     (Only honoured by layouts/admin-pro; the VA layout is unaffected.) --}}
@section('no-topbar', '1')

@section('content')
@php
    $me = Auth::guard('admin')->user();

    $money = fn ($v) => '$' . number_format((float) $v, 2);

@endphp

<div class="dash-hero">
    <div class="dash-hero-body">
        <div class="dash-hero-text">
            <span class="dash-greet">“He who cannot be a good follower cannot be a good leader.”</span>
            <h1 class="dash-name">Welcome {{ $me?->full_name ?: 'Admin' }}.</h1>
            <div class="dash-date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="3" y1="9.5" x2="21" y2="9.5"/><line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/></svg>
                <span id="dashDate">{{ now()->format('l, j F Y') }}</span>
            </div>
        </div>
        <div class="dash-hero-hype" aria-hidden="true">
            <div id="dashHero3d" class="dash-hero-anim"></div>
            <div class="dash-hype-tag">Lead by example.</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Show today's date in the viewer's local time (server runs on UTC).
    var del = document.getElementById('dashDate');
    if (del) { try { del.textContent = new Date().toLocaleDateString(undefined, { weekday:'long', day:'numeric', month:'long', year:'numeric' }); } catch (e) {} }
})();
</script>
<script src="{{ asset('js/lottie-light.min.js') }}"></script>
<script>
(function () {
    var host = document.getElementById('dashHero3d');
    if (!host || !window.lottie) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    try {
        window.lottie.loadAnimation({
            container: host,
            renderer: 'svg',
            loop: true,
            autoplay: !reduce,
            path: '{{ asset('lottie/superfatmanwalk.json') }}'
        });
    } catch (e) {}
})();
</script>
@endpush

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

    {{-- Moved up from the old Analytics Overview card, as plain numbers --}}
    <div class="dstat ds-blue">
        <div class="dstat-top"><span class="dstat-label">Total Clients</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span></div>
        <div class="dstat-val">{{ number_format($totalClients) }}</div>
        <div class="dstat-sub">Across all business owners</div>
    </div>
    <div class="dstat ds-amber">
        <div class="dstat-top"><span class="dstat-label">Active Owners</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg></span></div>
        <div class="dstat-val">{{ $activeOwners }}</div>
        <div class="dstat-sub">Business owners on the books</div>
    </div>
    <div class="dstat ds-green">
        <div class="dstat-top"><span class="dstat-label">New This Month</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="3" y1="9.5" x2="21" y2="9.5"/><line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/></svg></span></div>
        <div class="dstat-val">{{ number_format($newThisMonth) }}</div>
        <div class="dstat-sub">Clients added this month</div>
    </div>
    <div class="dstat ds-violet">
        <div class="dstat-top"><span class="dstat-label">On-Track Rate</span><span class="dstat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8.5,12.5 11,15 16,9.5"/></svg></span></div>
        <div class="dstat-val">{{ $onTrackRate }}%</div>
        <div class="dstat-sub">Clients not overdue</div>
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
@push('head')
<style>
    .content { background:#f6f8fc; }
    body.admin-body .main { background:#f6f8fc; }

    /* Hero header — dark indigo (sidebar palette) with the hero image behind,
       overlaid so the brand colours stay dominant and the text stays legible. */
    /* Full-bleed banner: break out of .pro-content's 24px/26px padding so it
       touches the sidebar, the right edge and the top. Flush top/sides,
       rounded bottom. */
    .dash-hero {
        position:relative; overflow:hidden;
        margin:-24px -26px 22px; border-radius:0 0 22px 0;   /* bottom-left sharp → merges with sidebar */
        background:
            linear-gradient(115deg, rgba(12,17,48,.94) 0%, rgba(20,26,62,.84) 45%, rgba(27,19,80,.70) 100%),
            #12163a url("{{ asset('Images/heroimage.png') }}") center/cover no-repeat;
        box-shadow:0 12px 30px rgba(15,23,42,.18);
    }
    .dash-hero-body {
        position:relative; z-index:1;
        display:flex; align-items:center; justify-content:space-between; gap:20px;
        padding:36px 34px 32px;
    }
    .dash-hero-text { min-width:0; }
    .dash-greet { display:block; max-width:620px; font-size:14.5px; font-style:italic; font-weight:500; color:#b4c0ec; letter-spacing:.01em; line-height:1.4; }
    .dash-name {
        margin:2px 0 0; font-size:34px; line-height:1.1; font-weight:800; letter-spacing:-.02em;
        color:#fff; text-shadow:0 2px 14px rgba(0,0,0,.25); word-break:break-word;
    }
    .dash-date {
        display:inline-flex; align-items:center; gap:8px; margin-top:12px;
        color:#c3cdf2; font-size:13px; font-weight:500;
    }
    .dash-date svg { color:#8ea0dc; }
    /* Motivational animation where the logo used to be */
    .dash-hero-hype { flex:none; display:flex; flex-direction:column; align-items:center; gap:4px; }
    .dash-hero-anim { width:170px; height:170px; filter:drop-shadow(0 8px 22px rgba(0,0,0,.35)); }
    .dash-hero-anim svg { width:100% !important; height:100% !important; }
    .dash-hype-tag {
        font-size:12.5px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;
        color:#c7d0f5; text-shadow:0 2px 10px rgba(0,0,0,.35);
    }
    @media (max-width:1200px) { .dash-hero-anim { width:140px; height:140px; } }
    @media (max-width:900px) {
        /* content padding drops to 16px here — match the breakout */
        .dash-hero { margin:-16px -16px 16px; }
        .dash-hero-body { padding:24px 18px; }
        .dash-name { font-size:26px; }
        .dash-hero-hype { display:none; }
    }

    /* Stat cards — top accent, compact */
    /* 3 per row → two rows of stat cards */
    /* 10 number cards: two rows of five */
    .dash-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:16px; }
    @media (max-width:1500px){ .dash-stats { grid-template-columns:repeat(4,1fr); } }
    @media (max-width:1200px){ .dash-stats { grid-template-columns:repeat(3,1fr); } }
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
