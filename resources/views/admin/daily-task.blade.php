@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Daily Task')
@section('subtitle', "Every round started this shift — grouped by business owner.")

@php
    // WhatsApp-style copy text: OWNER (caps) then client names, dashed separator.
    $sep = str_repeat('—', 26);
    $buildText = function ($group) use ($sep) {
        $t = strtoupper($group['name']) . "\n";
        foreach ($group['clients'] as $c) {
            $t .= $c['name'] . "\n";
        }
        return $t . $sep . "\n";
    };
    $copyAll = '';
    foreach ($groups as $g) {
        $copyAll .= $buildText($g) . "\n";
    }
@endphp

@section('content')
<div class="pro-panel" style="margin-bottom:16px;">
    <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#34d399,#059669);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </span>
            <h2>Daily Task</h2>
            <span class="pro-panel-count" style="background:#e0e7ff; color:#4338ca;">{{ count($groups) }} owners · {{ $clientCount }} clients</span>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <form method="GET" class="dt-dayform">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/></svg>
                <select name="date" onchange="this.form.submit()">
                    @foreach ($recentDays as $d)
                        <option value="{{ $d['date'] }}" @selected($d['date'] === $workDate)>{{ $d['label'] }}{{ $loop->first ? ' · this shift' : '' }}</option>
                    @endforeach
                </select>
            </form>
            @if (!empty($groups))
                <button type="button" class="dt-btn dt-btn-primary" data-copy-el="dtCopyAll">📋 Copy All (WhatsApp)</button>
            @endif
        </div>
    </div>
    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        {{ $isCurrent ? 'Current shift' : 'Shift of' }} <strong>{{ $workLabel }}</strong> — the 4 PM → 10 AM (PKT) work-day.
        Clients a VA started a round for (a Round-N Week&nbsp;1 step logged).
        Generated {{ $generatedAt->format('M j, Y g:i A') }} PKT.
    </p>
</div>

<style>
    .dt-dayform { position:relative; display:inline-flex; align-items:center; }
    .dt-dayform svg { position:absolute; left:11px; color:var(--pro-muted); pointer-events:none; }
    .dt-dayform select { appearance:none; background:var(--pro-card); border:1px solid var(--pro-line); border-radius:10px; color:var(--pro-text); font:inherit; font-size:13px; font-weight:600; padding:8px 14px 8px 32px; cursor:pointer; }
</style>

{{-- WhatsApp message — visible, ready to send --}}
@if (!empty($groups))
<div class="pro-panel" style="margin-bottom:16px; padding:16px 18px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <span class="pro-panel-chip" style="width:30px; height:30px; background:linear-gradient(140deg,#25d366,#128c7e);">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.05L2 22l5.1-1.34A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.13l-.29-.17-3.03.79.81-2.95-.19-.3A8 8 0 1 1 12 20zm4.5-5.6c-.25-.12-1.47-.72-1.7-.8-.23-.09-.4-.13-.56.12s-.64.8-.79.97-.29.18-.54.06a6.5 6.5 0 0 1-1.92-1.18 7.16 7.16 0 0 1-1.32-1.65c-.14-.24 0-.37.11-.49s.25-.29.37-.44.17-.25.25-.42a.46.46 0 0 0 0-.43c-.06-.12-.56-1.34-.76-1.84s-.4-.42-.56-.42h-.48a.92.92 0 0 0-.67.31 2.8 2.8 0 0 0-.87 2.08 4.86 4.86 0 0 0 1.02 2.58 11.14 11.14 0 0 0 4.27 3.77c.6.26 1.06.41 1.42.53.6.19 1.14.16 1.57.1.48-.07 1.47-.6 1.68-1.18s.21-1.08.15-1.18-.23-.18-.48-.3z"/></svg>
            </span>
            <div>
                <h2 style="font-size:15px; margin:0;">WhatsApp Message — Ready to Send</h2>
                <div style="font-size:11.5px; color:var(--pro-muted);">Owner names + clients only. Copy and paste straight into WhatsApp.</div>
            </div>
        </div>
        <button type="button" class="dt-btn dt-btn-wa" data-copy-el="dtWaMsg">📋 Copy Message</button>
    </div>
    <textarea id="dtWaMsg" class="dt-wa-text" readonly onclick="this.select()">{{ $copyAll }}</textarea>
</div>
@endif

@php
    $ownersWorked = count($groups);
    $ownerCounts  = collect($groups)
        ->map(fn ($g) => ['name' => $g['name'], 'count' => count($g['clients'])])
        ->sortByDesc('count')->values();

    $grads = [
        'linear-gradient(135deg,#6366f1,#4338ca)','linear-gradient(135deg,#10b981,#059669)',
        'linear-gradient(135deg,#f59e0b,#d97706)','linear-gradient(135deg,#ec4899,#be185d)',
        'linear-gradient(135deg,#06b6d4,#0891b2)','linear-gradient(135deg,#8b5cf6,#6d28d9)',
        'linear-gradient(135deg,#f43f5e,#be123c)','linear-gradient(135deg,#14b8a6,#0f766e)',
    ];
    $gradFor  = fn ($name) => $grads[abs(crc32($name)) % count($grads)];
    $initials = function ($name) {
        $p = preg_split('/\s+/', trim($name));
        $a = mb_substr($p[0] ?? '', 0, 1);
        $b = count($p) > 1 ? mb_substr(end($p), 0, 1) : mb_substr($p[0] ?? '', 1, 1);
        return mb_strtoupper($a . $b);
    };
@endphp

{{-- Summary boxes --}}
<div class="dt-stats">
    <div class="dt-stat dt-accent-indigo">
        <div class="dt-stat-top">
            <span class="dt-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="dt-stat-label">Clients Worked</span>
        </div>
        <div class="dt-stat-val">{{ number_format($clientCount) }}</div>
        <div class="dt-stat-sub">Rounds started · this shift</div>
    </div>
    <div class="dt-stat dt-accent-green">
        <div class="dt-stat-top">
            <span class="dt-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg></span>
            <span class="dt-stat-label">Business Owners Worked</span>
        </div>
        <div class="dt-stat-val">{{ number_format($ownersWorked) }}</div>
        <div class="dt-stat-sub">Owners active this shift</div>
    </div>
    <div class="dt-stat dt-accent-amber">
        <div class="dt-stat-top">
            <span class="dt-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
            <span class="dt-stat-label">Process Steps Logged</span>
        </div>
        <div class="dt-stat-val">{{ number_format($stepCount) }}</div>
        <div class="dt-stat-sub">Week-1 steps in the window</div>
    </div>
</div>

{{-- Per-owner breakdown: who got how many clients done --}}
@if ($ownerCounts->isNotEmpty())
    <div class="pro-panel" style="margin-bottom:16px; padding:14px 18px;">
        <div class="dt-strip-label">Clients worked per business owner</div>
        <div class="dt-owner-strip">
            @foreach ($ownerCounts as $idx => $oc)
                <span class="dt-owner-pill {{ $idx === 0 ? 'is-top' : '' }}">
                    @if ($idx === 0)<span class="dt-crown">👑</span>@endif
                    <span class="dt-owner-pill-name">{{ $oc['name'] }}</span>
                    <span class="dt-owner-pill-count">{{ $oc['count'] }}</span>
                </span>
            @endforeach
        </div>
    </div>
@endif

<textarea id="dtCopyAll" class="dt-hidden">{{ $copyAll }}</textarea>

@forelse ($groups as $boId => $g)
    <div class="pro-panel dt-owner">
        <div class="dt-owner-head">
            <div class="dt-owner-id">
                <span class="dt-avatar" style="background:{{ $gradFor($g['name']) }};">{{ $initials($g['name']) }}</span>
                <div>
                    <div class="dt-owner-name">{{ $g['name'] }}</div>
                    <div class="dt-owner-sub">{{ count($g['clients']) }} {{ count($g['clients']) === 1 ? 'client' : 'clients' }} worked</div>
                </div>
            </div>
            <button type="button" class="dt-btn" data-copy-el="dtBO{{ $boId }}">📋 Copy</button>
        </div>
        <textarea id="dtBO{{ $boId }}" class="dt-hidden">{{ $buildText($g) }}</textarea>

        <ul class="dt-clients">
            @foreach ($g['clients'] as $c)
                <li>
                    <span class="dt-check">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div class="dt-client-main">
                        <div class="dt-row-top">
                            <span class="dt-name">{{ $c['name'] }}</span>
                            @if (!empty($c['vas']))<span class="dt-tag dt-tag-va">{{ implode(', ', array_keys($c['vas'])) }}</span>@endif
                        </div>
                        @if (!empty($c['tasks']))
                            <div class="dt-tasks">{{ implode('  ·  ', array_keys($c['tasks'])) }}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@empty
    <div class="pro-panel" style="padding:40px 22px; text-align:center; color:var(--pro-muted);">
        No rounds were started this shift.
    </div>
@endforelse

@push('head')
    @include('admin.partials.daily-report-styles')
@endpush
@push('scripts')
    @include('admin.partials.daily-report-scripts')
@endpush
@endsection
