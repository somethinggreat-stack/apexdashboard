@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'EOD Report')
@section('subtitle', "End-of-day report for {$ownerName} — copy and send.")

@php
    $dateLabel = $generatedAt->timezone('America/New_York')->format('M j, Y');
    $clientsWorked = count($worked);

    // Build the copy-paste EOD text in the SOP format.
    $L = [];
    $L[] = strtoupper($ownerName) . " — EOD Report ({$dateLabel})";
    $L[] = "Total clients worked: {$clientsWorked}";
    $L[] = "New clients set up: {$newClientsCount}";
    $L[] = "Rounds sent: {$roundsSent}";
    $L[] = "";
    $L[] = "Clients worked:";
    if ($clientsWorked) {
        foreach ($worked as $w) { $L[] = "- {$w['name']} — " . implode('; ', $w['tasks']); }
    } else { $L[] = "- None"; }
    $L[] = "";
    $L[] = "Waiting for approval: " . ($waitingApproval->count() ?: 'None');
    foreach ($waitingApproval as $w) { $L[] = "- {$w['name']} (Round {$w['round']})"; }
    $L[] = "Nearing completion (1–2 left): " . ($nearing->count() ?: 'None');
    foreach ($nearing as $n) { $L[] = "- {$n['name']} ({$n['left']} left)"; }
    $L[] = "On hold: " . ($onHold->count() ?: 'None');
    foreach ($onHold as $h) { $L[] = "- {$h['name']}" . ($h['reason'] ? " — {$h['reason']}" : ''); }
    $L[] = "Issues/Errors: " . ($issues->count() ?: 'None');
    foreach ($issues as $i) { $L[] = "- {$i['name']} ({$i['type']})"; }
    $eodText = implode("\n", $L);
@endphp

@section('content')
@if (!$enabled)
    <div class="pro-panel" style="padding:40px 22px; text-align:center; color:var(--pro-muted);">
        Results tracking isn't enabled for any business owner yet.
    </div>
@else
<div class="pro-panel" style="margin-bottom:16px;">
    <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#34d399,#059669);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </span>
            <h2>EOD Report — {{ $ownerName }}</h2>
        </div>
        <button type="button" class="dt-btn dt-btn-primary" data-copy-el="eodText">📋 Copy Report</button>
    </div>
    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        {{ $dateLabel }} · generated {{ $generatedAt->timezone('America/New_York')->format('g:i A') }} ET.
    </p>
</div>

<div class="pro-panel" style="margin-bottom:16px; padding:16px 18px;">
    <textarea id="eodText" class="dt-wa-text" readonly onclick="this.select()">{{ $eodText }}</textarea>
</div>

<div class="dt-stats">
    <div class="dt-stat dt-accent-indigo">
        <div class="dt-stat-top"><span class="dt-stat-label">Clients Worked</span></div>
        <div class="dt-stat-val">{{ $clientsWorked }}</div>
        <div class="dt-stat-sub">Today</div>
    </div>
    <div class="dt-stat dt-accent-green">
        <div class="dt-stat-top"><span class="dt-stat-label">New Clients Set Up</span></div>
        <div class="dt-stat-val">{{ $newClientsCount }}</div>
        <div class="dt-stat-sub">Today</div>
    </div>
    <div class="dt-stat dt-accent-amber">
        <div class="dt-stat-top"><span class="dt-stat-label">Rounds Sent</span></div>
        <div class="dt-stat-val">{{ $roundsSent }}</div>
        <div class="dt-stat-sub">Today</div>
    </div>
</div>

<div class="pro-panel" style="padding:16px 18px; margin-bottom:16px;">
    <h3 style="margin:0 0 10px; font-size:15px;">Clients worked today</h3>
    @forelse ($worked as $w)
        <div class="eod-row"><strong>{{ $w['name'] }}</strong><span>{{ implode('  ·  ', $w['tasks']) }}</span></div>
    @empty
        <p class="muted">No client work logged today.</p>
    @endforelse
</div>

<div class="eod-grid">
    <div class="pro-panel eod-list">
        <h3>Waiting for approval</h3>
        @forelse ($waitingApproval as $w)<div class="eod-li">{{ $w['name'] }} <span class="muted">· Round {{ $w['round'] }}</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="pro-panel eod-list">
        <h3>Nearing completion (1–2 left)</h3>
        @forelse ($nearing as $n)<div class="eod-li">{{ $n['name'] }} <span class="muted">· {{ $n['left'] }} left</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="pro-panel eod-list">
        <h3>On hold</h3>
        @forelse ($onHold as $h)<div class="eod-li">{{ $h['name'] }}@if ($h['reason']) <span class="muted">· {{ $h['reason'] }}</span>@endif</div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="pro-panel eod-list">
        <h3>Issues / Errors</h3>
        @forelse ($issues as $i)<div class="eod-li">{{ $i['name'] }} <span class="muted">· {{ $i['type'] }}</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
</div>
@endif

@push('head')
    @include('admin.partials.daily-report-styles')
    <style>
        .eod-row { display:flex; justify-content:space-between; gap:14px; padding:8px 0; border-bottom:1px solid var(--pro-line,#eef2f7); flex-wrap:wrap; }
        .eod-row span { color:var(--pro-muted,#64748b); font-size:13px; }
        .eod-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
        .eod-list { padding:14px 16px; }
        .eod-list h3 { margin:0 0 8px; font-size:14px; }
        .eod-li { padding:5px 0; border-bottom:1px solid var(--pro-line,#eef2f7); font-size:13.5px; }
        .eod-li:last-child { border-bottom:none; }
    </style>
@endpush
@push('scripts')
    @include('admin.partials.daily-report-scripts')
@endpush
@endsection
