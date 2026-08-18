@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Daily Task')
@section('subtitle', "Everything worked on in the last {$windowHours} hours — grouped by business owner.")

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
        @if (!empty($groups))
            <button type="button" class="dt-btn dt-btn-primary" data-copy-el="dtCopyAll">📋 Copy All (WhatsApp)</button>
        @endif
    </div>
    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        Clients whose round was started (Week 1 step logged), or newly added to the Clients list, in the last {{ $windowHours }} hours.
        Generated {{ $generatedAt->timezone('America/New_York')->format('M j, Y g:i A') }} ET.
    </p>
</div>

@php
    $ownersWorked = count($groups);
    $newToClients = collect($groups)->sum(fn ($g) => collect($g['clients'])->where('listed', true)->count());
    $ownerCounts  = collect($groups)
        ->map(fn ($g) => ['name' => $g['name'], 'count' => count($g['clients'])])
        ->sortByDesc('count')->values();
@endphp

{{-- Summary boxes --}}
<div class="dt-stats">
    <div class="dt-stat">
        <div class="dt-stat-label">Total Clients Done Today</div>
        <div class="dt-stat-val">{{ number_format($clientCount) }}</div>
        <div class="dt-stat-sub">Across all owners · last {{ $windowHours }}h</div>
    </div>
    <div class="dt-stat">
        <div class="dt-stat-label">Business Owners Worked</div>
        <div class="dt-stat-val">{{ number_format($ownersWorked) }}</div>
        <div class="dt-stat-sub">Owners with activity today</div>
    </div>
    <div class="dt-stat">
        <div class="dt-stat-label">New to Clients List</div>
        <div class="dt-stat-val">{{ number_format($newToClients) }}</div>
        <div class="dt-stat-sub">Newly added in the window</div>
    </div>
</div>

{{-- Per-owner breakdown: who got how many clients done --}}
@if ($ownerCounts->isNotEmpty())
    <div class="pro-panel" style="margin-bottom:16px; padding:14px 18px;">
        <div class="dt-strip-label">Clients done per business owner</div>
        <div class="dt-owner-strip">
            @foreach ($ownerCounts as $oc)
                <span class="dt-owner-pill">
                    <span class="dt-owner-pill-name">{{ $oc['name'] }}</span>
                    <span class="dt-owner-pill-count">{{ $oc['count'] }}</span>
                </span>
            @endforeach
        </div>
    </div>
@endif

<textarea id="dtCopyAll" class="dt-hidden">{{ $copyAll }}</textarea>

@forelse ($groups as $boId => $g)
    <div class="pro-panel dt-owner" style="margin-bottom:14px;">
        <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <div class="pro-panel-title">
                <h2 style="font-size:15px; text-transform:uppercase; letter-spacing:.02em;">{{ $g['name'] }}</h2>
                <span class="pro-panel-count" style="background:#eef2f7; color:#475569;">{{ count($g['clients']) }}</span>
            </div>
            <button type="button" class="dt-btn" data-copy-el="dtBO{{ $boId }}">Copy</button>
        </div>
        <textarea id="dtBO{{ $boId }}" class="dt-hidden">{{ $buildText($g) }}</textarea>

        <ul class="dt-clients">
            @foreach ($g['clients'] as $c)
                <li>
                    <div class="dt-row-top">
                        <span class="dt-name">{{ $c['name'] }}</span>
                        @if ($c['listed'])<span class="dt-tag dt-tag-new">New to Clients</span>@endif
                        @if (!empty($c['vas']))<span class="dt-tag dt-tag-va">{{ implode(', ', array_keys($c['vas'])) }}</span>@endif
                    </div>
                    @if (!empty($c['tasks']))
                        <div class="dt-tasks">{{ implode('  ·  ', array_keys($c['tasks'])) }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@empty
    <div class="pro-panel" style="padding:40px 22px; text-align:center; color:var(--pro-muted);">
        No client work logged in the last {{ $windowHours }} hours.
    </div>
@endforelse

@push('head')
<style>
    .dt-hidden { position:absolute; left:-9999px; width:1px; height:1px; opacity:0; }
    /* Summary stat boxes */
    .dt-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    .dt-stat { background:var(--pro-card); border:1px solid var(--pro-line); border-radius:14px; padding:16px 18px; box-shadow:var(--pro-shadow-sm); }
    .dt-stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); }
    .dt-stat-val { font-size:30px; font-weight:800; color:var(--pro-text); line-height:1.1; margin-top:4px; letter-spacing:-.5px; }
    .dt-stat-sub { font-size:11.5px; color:var(--pro-muted); margin-top:2px; }
    @media (max-width:900px){ .dt-stats { grid-template-columns:1fr; } }
    /* Per-owner breakdown strip */
    .dt-strip-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); margin-bottom:10px; }
    .dt-owner-strip { display:flex; flex-wrap:wrap; gap:8px; }
    .dt-owner-pill { display:inline-flex; align-items:center; gap:8px; background:var(--pro-line-soft); border:1px solid var(--pro-line); border-radius:999px; padding:5px 6px 5px 13px; font-size:13px; }
    .dt-owner-pill-name { font-weight:600; color:var(--pro-text); }
    .dt-owner-pill-count { background:#4f46e5; color:#fff; font-weight:700; font-size:12px; min-width:22px; text-align:center; border-radius:999px; padding:1px 7px; }
    .dt-btn {
        border:1px solid var(--pro-line); background:#fff; color:var(--pro-text);
        border-radius:8px; padding:6px 14px; font-size:12.5px; font-weight:600; cursor:pointer;
    }
    .dt-btn:hover { border-color:#94a3b8; }
    .dt-btn-primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
    .dt-btn-primary:hover { background:#4338ca; border-color:#4338ca; }
    .dt-clients { list-style:none; margin:0; padding:6px 22px 16px; }
    .dt-clients li {
        padding:9px 0; border-bottom:1px solid var(--pro-line-soft);
    }
    .dt-clients li:last-child { border-bottom:0; }
    .dt-row-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .dt-name { font-size:14px; font-weight:600; color:var(--pro-text); }
    .dt-tasks { font-size:12px; color:var(--pro-muted); margin-top:3px; }
    .dt-tag { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; }
    .dt-tag-new { background:#dcfce7; color:#166534; }
    .dt-tag-va  { background:#eef2ff; color:#4338ca; letter-spacing:.02em; }
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('[data-copy-el]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-copy-el'));
        if (!el) return;
        var text = el.value;
        var done = function () {
            var old = btn.textContent; btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = old; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { el.select(); document.execCommand('copy'); done(); });
        } else {
            el.style.position = 'static'; el.select(); document.execCommand('copy'); el.style.position = 'absolute'; done();
        }
    });
});
</script>
@endpush
@endsection
