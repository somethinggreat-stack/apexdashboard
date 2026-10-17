@extends('layouts.admin-pro')

@section('title', 'Session Errors')

@section('content')
@php
    $areaLabels = ['admin' => 'Admin / VA dashboard', 'portal' => 'Business owner portal', 'chat' => 'Team Chat'];
    $link = fn (array $q) => route('admin.session-errors.index', array_merge(['days' => $days, 'area' => $area], $q));
@endphp

<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#f59e0b,#d97706);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
            </span>
            <h2>Session Errors (419 Page Expired)</h2>
        </div>
    </div>
    <p style="margin:0; padding:0 22px 14px; font-size:13px; color:var(--pro-muted);">
        Every time a page or form was rejected because its security token had expired. Nobody sees the raw
        “419 Page Expired” screen any more — they're sent back to sign in or to the same page — but each one is
        recorded here so we can see who hits it, on which device, and why.
    </p>
</div>

<div class="se-cards">
    <div class="se-card"><span class="se-num">{{ $stats['last_24h'] }}</span><span class="se-lbl">Last 24 hours</span></div>
    <div class="se-card"><span class="se-num">{{ $stats['last_7d'] }}</span><span class="se-lbl">Last 7 days</span></div>
    <div class="se-card"><span class="se-num">{{ $stats['last_30d'] }}</span><span class="se-lbl">Last 30 days</span></div>
</div>

<div class="se-filters">
    <span>Period:</span>
    @foreach ([1 => '24 hours', 7 => '7 days', 30 => '30 days', 60 => '60 days'] as $d => $lbl)
        <a href="{{ $link(['days' => $d]) }}" class="{{ $days === $d ? 'on' : '' }}">{{ $lbl }}</a>
    @endforeach
    <span style="margin-left:14px;">Area:</span>
    <a href="{{ $link(['area' => null]) }}" class="{{ ! $area ? 'on' : '' }}">All</a>
    @foreach ($areaLabels as $k => $lbl)
        <a href="{{ $link(['area' => $k]) }}" class="{{ $area === $k ? 'on' : '' }}">{{ $lbl }}</a>
    @endforeach
</div>

<div class="se-grid">
    <div class="pro-panel se-box">
        <h3>Why</h3>
        @forelse ($byReason as $reason => $n)
            <div class="se-row"><span>{{ \App\Models\SessionError::REASONS[$reason] ?? $reason }}</span><b>{{ $n }}</b></div>
        @empty <div class="se-empty">None</div> @endforelse
    </div>
    <div class="pro-panel se-box">
        <h3>Where</h3>
        @forelse ($byArea as $a => $n)
            <div class="se-row"><span>{{ $areaLabels[$a] ?? $a }}</span><b>{{ $n }}</b></div>
        @empty <div class="se-empty">None</div> @endforelse
    </div>
    <div class="pro-panel se-box">
        <h3>Device</h3>
        @forelse ($byDevice as $d => $n)
            <div class="se-row"><span>{{ $d }}</span><b>{{ $n }}</b></div>
        @empty <div class="se-empty">None</div> @endforelse
    </div>
    <div class="pro-panel se-box">
        <h3>Page</h3>
        @forelse ($byPath as $p => $n)
            <div class="se-row"><span class="se-mono">{{ $p }}</span><b>{{ $n }}</b></div>
        @empty <div class="se-empty">None</div> @endforelse
    </div>
</div>

<div class="pro-panel">
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Where</th>
                    <th>Page</th>
                    <th>Why</th>
                    <th>Device</th>
                    <th>Address</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $e)
                    <tr>
                        <td title="{{ $e->created_at }}">{{ $e->created_at->timezone('Asia/Karachi')->format('M j, g:ia') }}<br>
                            <span class="se-sub">{{ $e->created_at->diffForHumans() }}</span></td>
                        <td>{{ $areaLabels[$e->area] ?? $e->area }}</td>
                        <td><span class="se-mono">{{ $e->method }} {{ $e->path }}</span>@if ($e->was_ajax)<br><span class="se-sub">background request</span>@endif</td>
                        <td><span class="se-reason se-{{ $e->reason }}">{{ $e->reasonLabel() }}</span></td>
                        <td title="{{ $e->user_agent }}">{{ $e->device }}</td>
                        <td>{{ $e->secure ? 'https' : 'http' }}://{{ $e->host }}</td>
                        <td>{{ $e->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No session errors in this period. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    .se-cards { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:16px; }
    .se-card { background:var(--pro-card, #fff); border:1px solid var(--pro-line, #e5e7eb); border-radius:14px; padding:16px 18px; display:flex; flex-direction:column; }
    .se-num { font-size:28px; font-weight:800; color:var(--pro-ink, #0f172a); }
    .se-lbl { font-size:12.5px; color:var(--pro-muted, #64748b); }
    .se-filters { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:16px; font-size:13px; color:var(--pro-muted, #64748b); }
    .se-filters a { padding:5px 11px; border-radius:999px; border:1px solid var(--pro-line, #e5e7eb); color:inherit; text-decoration:none; }
    .se-filters a.on { background:#4f46e5; border-color:#4f46e5; color:#fff; }
    .se-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:16px; }
    .se-box { padding:14px 16px; margin:0; }
    .se-box h3 { margin:0 0 8px; font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted, #64748b); }
    .se-row { display:flex; justify-content:space-between; gap:10px; padding:5px 0; font-size:13px; border-bottom:1px dashed var(--pro-line, #e5e7eb); }
    .se-row:last-child { border-bottom:0; }
    .se-empty { font-size:13px; color:var(--pro-muted, #64748b); }
    .se-mono { font-family:ui-monospace, Consolas, monospace; font-size:12px; word-break:break-all; }
    .se-sub { font-size:11.5px; color:var(--pro-muted, #64748b); }
    .se-reason { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:700; }
    .se-session_expired { background:#fef3c7; color:#92400e; }
    .se-cookie_missing  { background:#fee2e2; color:#991b1b; }
    .se-not_https       { background:#ede9fe; color:#5b21b6; }
    @media (max-width:900px) { .se-grid { grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:560px) { .se-grid, .se-cards { grid-template-columns:1fr; } }
</style>
@endsection
