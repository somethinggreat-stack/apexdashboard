@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Tasks View')
@section('subtitle', "Rounds started per day for this business owner — last {$windowDays} days.")

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h2 style="margin:0;">Tasks View</h2>
        <span class="tv-count">{{ number_format($clientsWorked) }} {{ $clientsWorked === 1 ? 'client' : 'clients' }} · last {{ $windowDays }} days</span>
    </div>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">
        A day-by-day record of the rounds our team started for this owner's clients over the last {{ $windowDays }} days,
        with the VA who logged each one and the exact time (ET). Same signal as the Daily Task page.
        Generated {{ $generatedAt->timezone('America/New_York')->format('M j, Y g:i A') }} ET.
    </p>
</div>

<div class="tv-stats">
    <div class="tv-stat tv-indigo">
        <div class="tv-stat-top">
            <span class="tv-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="tv-stat-label">Clients Worked</span>
        </div>
        <div class="tv-stat-val">{{ number_format($clientsWorked) }}</div>
        <div class="tv-stat-sub">In the last {{ $windowDays }} days</div>
    </div>
    <div class="tv-stat tv-green">
        <div class="tv-stat-top">
            <span class="tv-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg></span>
            <span class="tv-stat-label">Rounds Started</span>
        </div>
        <div class="tv-stat-val">{{ number_format($roundsStarted) }}</div>
        <div class="tv-stat-sub">New rounds kicked off</div>
    </div>
    <div class="tv-stat tv-amber">
        <div class="tv-stat-top">
            <span class="tv-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
            <span class="tv-stat-label">Process Steps Logged</span>
        </div>
        <div class="tv-stat-val">{{ number_format($stepsLogged) }}</div>
        <div class="tv-stat-sub">Week-1 steps in the window</div>
    </div>
</div>

@forelse ($days as $day)
    <div class="card tv-day">
        <div class="tv-day-head">
            <div class="tv-day-date">
                <span class="tv-day-dot"></span>
                <span>{{ $day['date']->format('l, M j, Y') }}</span>
            </div>
            <span class="tv-day-count">{{ count($day['entries']) }} {{ count($day['entries']) === 1 ? 'client' : 'clients' }} worked</span>
        </div>
        <ul class="tv-list">
            @foreach ($day['entries'] as $e)
                <li class="tv-item">
                    <span class="tv-check">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div class="tv-item-main">
                        <div class="tv-item-top">
                            <span class="tv-name">{{ $e['name'] }}</span>
                            <span class="tv-round">{{ $e['round'] }} Round started</span>
                            @if (!empty($e['vas']))<span class="tv-va">{{ implode(', ', array_keys($e['vas'])) }}</span>@endif
                        </div>
                        @if (!empty($e['tasks']))
                            <div class="tv-tasks">{{ implode('  ·  ', array_keys($e['tasks'])) }}</div>
                        @endif
                    </div>
                    <span class="tv-time">{{ $e['at']->format('g:i A') }} ET</span>
                </li>
            @endforeach
        </ul>
    </div>
@empty
    <div class="card" style="padding:40px 22px; text-align:center;">
        <p class="muted" style="margin:0;">No rounds have been started for this owner's clients in the last {{ $windowDays }} days.</p>
    </div>
@endforelse

@push('head')
<style>
    /* Theme-aware: use the pro console's own tokens (--pro-card/-line/-text/
       -muted/-bg) so the whole page follows the light/dark toggle. Colored
       chips get an explicit dark override below. */
    .tv-count { font-size:12.5px; font-weight:700; color:var(--tv-accent-fg); background:var(--tv-accent-bg); padding:3px 11px; border-radius:999px; }
    .tv-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
    @media (max-width:820px){ .tv-stats{ grid-template-columns:1fr; } }
    .tv-stat { background:var(--pro-card,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:14px; padding:16px 18px; position:relative; overflow:hidden; }
    .tv-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
    .tv-indigo::before { background:linear-gradient(90deg,#6366f1,#4338ca); }
    .tv-green::before  { background:linear-gradient(90deg,#34d399,#059669); }
    .tv-amber::before  { background:linear-gradient(90deg,#f59e0b,#d97706); }
    .tv-stat-top { display:flex; align-items:center; gap:9px; margin-bottom:8px; }
    .tv-stat-ico { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:var(--tv-accent-bg); color:var(--tv-accent-fg); }
    .tv-green .tv-stat-ico { background:var(--tv-green-bg); color:var(--tv-green-fg); }
    .tv-amber .tv-stat-ico { background:var(--tv-amber-bg); color:var(--tv-amber-fg); }
    .tv-stat-ico svg { width:18px; height:18px; }
    .tv-stat-label { font-size:11.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--pro-muted,#64748b); }
    .tv-stat-val { font-size:30px; font-weight:800; line-height:1; color:var(--pro-text,#0f172a); }
    .tv-stat-sub { font-size:12px; color:var(--pro-muted,#64748b); margin-top:5px; }

    .tv-day { margin-bottom:14px; padding:0; overflow:hidden; }
    .tv-day-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:13px 18px; border-bottom:1px solid var(--pro-line,#e6ebf2); background:var(--pro-line-soft,#f8fafc); }
    .tv-day-date { display:flex; align-items:center; gap:9px; font-size:14px; font-weight:800; color:var(--pro-text,#0f172a); }
    .tv-day-dot { width:9px; height:9px; border-radius:50%; background:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.18); }
    .tv-day-count { font-size:12px; font-weight:700; color:var(--pro-muted,#64748b); }
    .tv-list { list-style:none; margin:0; padding:4px 0; }
    .tv-item { display:flex; align-items:flex-start; gap:12px; padding:11px 18px; }
    .tv-item + .tv-item { border-top:1px solid var(--pro-line-soft,#eef2f7); }
    .tv-check { flex:0 0 auto; width:24px; height:24px; border-radius:50%; background:var(--tv-green-bg); color:var(--tv-green-fg); display:flex; align-items:center; justify-content:center; margin-top:1px; }
    .tv-check svg { width:14px; height:14px; }
    .tv-item-main { flex:1 1 auto; min-width:0; }
    .tv-item-top { display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
    .tv-name { font-size:14px; font-weight:700; color:var(--pro-text,#0f172a); }
    .tv-round { font-size:11px; font-weight:700; color:var(--tv-accent-fg); background:var(--tv-accent-bg); padding:2px 9px; border-radius:999px; }
    .tv-va { font-size:11px; font-weight:700; color:var(--tv-cyan-fg); background:var(--tv-cyan-bg); padding:2px 9px; border-radius:999px; }
    .tv-tasks { font-size:12.5px; color:var(--pro-muted,#64748b); margin-top:3px; }
    .tv-time { flex:0 0 auto; font-size:12px; font-weight:700; color:var(--pro-muted,#64748b); white-space:nowrap; margin-top:2px; }

    /* Chip tints — light defaults, dark overrides (translucent so they sit on
       the dark panel without glowing). */
    :root {
        --tv-accent-bg:#e0e7ff; --tv-accent-fg:#4338ca;
        --tv-green-bg:#ecfdf5;  --tv-green-fg:#059669;
        --tv-amber-bg:#fffbeb;  --tv-amber-fg:#d97706;
        --tv-cyan-bg:#cffafe;   --tv-cyan-fg:#0e7490;
    }
    :root[data-theme="dark"] {
        --tv-accent-bg:rgba(99,102,241,.20);  --tv-accent-fg:#c7d2fe;
        --tv-green-bg:rgba(16,185,129,.18);   --tv-green-fg:#6ee7b7;
        --tv-amber-bg:rgba(245,158,11,.18);   --tv-amber-fg:#fcd34d;
        --tv-cyan-bg:rgba(6,182,212,.18);     --tv-cyan-fg:#a5f3fc;
    }
</style>
@endpush
@endsection
