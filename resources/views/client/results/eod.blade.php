@extends('layouts.client')

@section('title', 'EOD Report')

@php
    $dateLabel = \Illuminate\Support\Carbon::parse($workDate, \App\Support\WorkDay::TZ)->format('M j, Y');
    $clientsWorked = count($worked);

    // Same copy-paste EOD text your team sends, in the SOP format.
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
<div class="card" style="margin-bottom:16px;">
    <div class="card-header" style="align-items:center; gap:12px; flex-wrap:wrap;">
        <h2 style="margin:0;">EOD Report — {{ $ownerName }}</h2>
        <div style="display:flex; align-items:center; gap:10px;">
            <form method="GET" class="eod-dayform">
                <select name="date" onchange="this.form.submit()">
                    @foreach ($recentDays as $d)
                        <option value="{{ $d['date'] }}" @selected($d['date'] === $workDate)>{{ $d['label'] }}{{ $loop->first ? ' · this shift' : '' }}</option>
                    @endforeach
                </select>
            </form>
            <button type="button" class="btn btn-sm btn-primary" id="eodCopyBtn">📋 Copy</button>
        </div>
    </div>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">
        {{ $isCurrent ? 'Current shift' : 'Shift of' }} <strong>{{ $workLabel }}</strong> (4 PM → 10 AM PKT) · generated {{ $generatedAt->format('g:i A') }} PKT.
    </p>
    <textarea id="eodText" class="eod-text" readonly onclick="this.select()">{{ $eodText }}</textarea>
</div>

<div class="eod-stats">
    <div class="card eod-stat"><span class="eod-stat-label">Clients Worked</span><span class="eod-stat-val">{{ $clientsWorked }}</span><span class="muted">This shift</span></div>
    <div class="card eod-stat"><span class="eod-stat-label">New Clients Set Up</span><span class="eod-stat-val">{{ $newClientsCount }}</span><span class="muted">This shift</span></div>
    <div class="card eod-stat"><span class="eod-stat-label">Rounds Sent</span><span class="eod-stat-val">{{ $roundsSent }}</span><span class="muted">This shift</span></div>
</div>

<div class="card" style="margin:16px 0;">
    <div class="card-header"><h3 style="margin:0; font-size:15px;">Clients worked this shift</h3></div>
    @forelse ($worked as $w)
        <div class="eod-row"><strong>{{ $w['name'] }}</strong><span class="muted">{{ implode('  ·  ', $w['tasks']) }}</span></div>
    @empty
        <p class="muted" style="margin:10px 0 0;">No client work logged this shift.</p>
    @endforelse
</div>

<div class="eod-grid">
    <div class="card eod-list">
        <h3>Waiting for approval</h3>
        @forelse ($waitingApproval as $w)<div class="eod-li">{{ $w['name'] }} <span class="muted">· Round {{ $w['round'] }}</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="card eod-list">
        <h3>Nearing completion (1–2 left)</h3>
        @forelse ($nearing as $n)<div class="eod-li">{{ $n['name'] }} <span class="muted">· {{ $n['left'] }} left</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="card eod-list">
        <h3>On hold</h3>
        @forelse ($onHold as $h)<div class="eod-li">{{ $h['name'] }}@if ($h['reason']) <span class="muted">· {{ $h['reason'] }}</span>@endif</div>@empty<p class="muted">None.</p>@endforelse
    </div>
    <div class="card eod-list">
        <h3>Issues / Errors</h3>
        @forelse ($issues as $i)<div class="eod-li">{{ $i['name'] }} <span class="muted">· {{ $i['type'] }}</span></div>@empty<p class="muted">None.</p>@endforelse
    </div>
</div>

@push('head')
<style>
    .eod-dayform select { appearance:none; background:var(--surface,#fff); border:1px solid var(--border,#e6ebf2); border-radius:10px; color:var(--text,#334155); font:inherit; font-size:13px; font-weight:600; padding:8px 14px; cursor:pointer; }
    .eod-text { width:100%; min-height:180px; margin-top:12px; padding:12px 14px; border:1px solid var(--border,#e6ebf2); border-radius:10px; background:var(--surface-2,#f8fafc); color:var(--text,#334155); font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace; resize:vertical; }
    .eod-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; }
    .eod-stat { display:flex; flex-direction:column; gap:2px; padding:16px 18px; }
    .eod-stat-label { font-size:12px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; color:var(--muted,#64748b); }
    .eod-stat-val { font-size:30px; font-weight:800; color:var(--text,#0f172a); line-height:1.1; }
    .eod-row { display:flex; justify-content:space-between; gap:14px; padding:9px 0; border-bottom:1px solid var(--border,#eef2f7); flex-wrap:wrap; }
    .eod-row span { font-size:13px; }
    .eod-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
    .eod-list { padding:14px 16px; }
    .eod-list h3 { margin:0 0 8px; font-size:14px; }
    .eod-li { padding:6px 0; border-bottom:1px solid var(--border,#eef2f7); font-size:13.5px; }
    .eod-li:last-child { border-bottom:none; }
</style>
@endpush
@push('scripts')
<script>
    document.getElementById('eodCopyBtn')?.addEventListener('click', function () {
        var t = document.getElementById('eodText');
        t.select();
        navigator.clipboard?.writeText(t.value).then(() => {
            this.textContent = '✓ Copied';
            setTimeout(() => { this.textContent = '📋 Copy'; }, 1500);
        }).catch(() => { document.execCommand('copy'); });
    });
</script>
@endpush
@endsection
