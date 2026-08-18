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
            <button type="button" class="dt-btn dt-btn-primary" data-copy-el="dtCopyAll">Copy All</button>
        @endif
    </div>
    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        Clients with a process step logged, or newly added to the Clients list, in the last {{ $windowHours }} hours.
        Generated {{ $generatedAt->timezone('America/New_York')->format('M j, Y g:i A') }} ET.
    </p>
</div>

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
                    <span class="dt-name">{{ $c['name'] }}</span>
                    @if ($c['listed'])<span class="dt-tag dt-tag-new">New to Clients</span>@endif
                    @if (!empty($c['vas']))<span class="dt-tag dt-tag-va">{{ implode(', ', array_keys($c['vas'])) }}</span>@endif
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
    .dt-btn {
        border:1px solid var(--pro-line); background:#fff; color:var(--pro-text);
        border-radius:8px; padding:6px 14px; font-size:12.5px; font-weight:600; cursor:pointer;
    }
    .dt-btn:hover { border-color:#94a3b8; }
    .dt-btn-primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
    .dt-btn-primary:hover { background:#4338ca; border-color:#4338ca; }
    .dt-clients { list-style:none; margin:0; padding:6px 22px 16px; }
    .dt-clients li {
        display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        padding:8px 0; border-bottom:1px solid var(--pro-line-soft);
    }
    .dt-clients li:last-child { border-bottom:0; }
    .dt-name { font-size:14px; font-weight:600; color:var(--pro-text); }
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
