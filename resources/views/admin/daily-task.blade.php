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
    $newToClients = collect($groups)->sum(fn ($g) => collect($g['clients'])->where('listed', true)->count());
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
            <span class="dt-stat-label">Total Clients Done Today</span>
        </div>
        <div class="dt-stat-val">{{ number_format($clientCount) }}</div>
        <div class="dt-stat-sub">Across all owners · last {{ $windowHours }}h</div>
    </div>
    <div class="dt-stat dt-accent-green">
        <div class="dt-stat-top">
            <span class="dt-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg></span>
            <span class="dt-stat-label">Business Owners Worked</span>
        </div>
        <div class="dt-stat-val">{{ number_format($ownersWorked) }}</div>
        <div class="dt-stat-sub">Owners with activity today</div>
    </div>
    <div class="dt-stat dt-accent-amber">
        <div class="dt-stat-top">
            <span class="dt-stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
            <span class="dt-stat-label">New to Clients List</span>
        </div>
        <div class="dt-stat-val">{{ number_format($newToClients) }}</div>
        <div class="dt-stat-sub">Newly added in the window</div>
    </div>
</div>

{{-- Per-owner breakdown: who got how many clients done --}}
@if ($ownerCounts->isNotEmpty())
    <div class="pro-panel" style="margin-bottom:16px; padding:14px 18px;">
        <div class="dt-strip-label">Clients done per business owner</div>
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
                    <span class="dt-check {{ $c['listed'] ? 'is-new' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div class="dt-client-main">
                        <div class="dt-row-top">
                            <span class="dt-name">{{ $c['name'] }}</span>
                            @if ($c['listed'])<span class="dt-tag dt-tag-new">New to Clients</span>@endif
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
        No client work logged in the last {{ $windowHours }} hours.
    </div>
@endforelse

@push('head')
<style>
    .dt-hidden { position:absolute; left:-9999px; width:1px; height:1px; opacity:0; }

    /* Summary stat boxes */
    .dt-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    .dt-stat { position:relative; background:var(--pro-card); border:1px solid var(--pro-line); border-radius:16px; padding:17px 18px 15px; box-shadow:var(--pro-shadow-sm); overflow:hidden; transition:box-shadow .18s, transform .18s; }
    .dt-stat::before { content:""; position:absolute; top:0; left:0; right:0; height:4px; background:var(--dt-accent,#4f46e5); }
    .dt-stat:hover { box-shadow:0 10px 26px rgba(15,23,42,.10); transform:translateY(-2px); }
    .dt-stat-top { display:flex; align-items:center; gap:11px; margin-bottom:9px; }
    .dt-stat-ico { width:36px; height:36px; border-radius:11px; flex:none; display:inline-flex; align-items:center; justify-content:center; background:var(--dt-accent-soft,#eef2ff); color:var(--dt-accent,#4f46e5); }
    .dt-stat-ico svg { width:18px; height:18px; }
    .dt-stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); }
    .dt-stat-val { font-size:36px; font-weight:800; color:var(--pro-text); line-height:1; margin-top:2px; letter-spacing:-1.2px; }
    .dt-stat-sub { font-size:11.5px; color:var(--pro-muted); margin-top:6px; }
    .dt-accent-indigo { --dt-accent:#4f46e5; --dt-accent-soft:#eef2ff; }
    .dt-accent-green  { --dt-accent:#059669; --dt-accent-soft:#dcfce7; }
    .dt-accent-amber  { --dt-accent:#d97706; --dt-accent-soft:#fef3c7; }
    @media (max-width:900px){ .dt-stats { grid-template-columns:1fr; } }

    /* Per-owner breakdown strip */
    .dt-strip-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pro-muted); margin-bottom:10px; }
    .dt-owner-strip { display:flex; flex-wrap:wrap; gap:8px; }
    .dt-owner-pill { display:inline-flex; align-items:center; gap:8px; background:var(--pro-line-soft); border:1px solid var(--pro-line); border-radius:999px; padding:5px 6px 5px 13px; font-size:13px; transition:transform .12s, box-shadow .12s; }
    .dt-owner-pill:hover { transform:translateY(-1px); box-shadow:var(--pro-shadow-sm); }
    .dt-owner-pill.is-top { background:linear-gradient(135deg,#fffbeb,#fef3c7); border-color:#fcd34d; }
    .dt-crown { font-size:13px; margin-right:-2px; }
    .dt-owner-pill-name { font-weight:600; color:var(--pro-text); }
    .dt-owner-pill-count { background:#4f46e5; color:#fff; font-weight:700; font-size:12px; min-width:22px; text-align:center; border-radius:999px; padding:1px 7px; }
    .dt-owner-pill.is-top .dt-owner-pill-count { background:#d97706; }

    /* Buttons */
    .dt-btn { border:1px solid var(--pro-line); background:var(--pro-card); color:var(--pro-text); border-radius:9px; padding:6px 14px; font-size:12.5px; font-weight:600; cursor:pointer; transition:border-color .15s, background .15s, transform .1s; }
    .dt-btn:hover { border-color:#94a3b8; }
    .dt-btn:active { transform:scale(.97); }
    .dt-btn-primary { background:#4f46e5; border-color:#4f46e5; color:#fff; box-shadow:0 5px 14px rgba(79,70,229,.28); }
    .dt-btn-primary:hover { background:#4338ca; border-color:#4338ca; }
    .dt-btn-wa { background:#25d366; border-color:#25d366; color:#0b2e13; font-weight:700; box-shadow:0 5px 14px rgba(37,211,102,.3); }
    .dt-btn-wa:hover { background:#1eb457; border-color:#1eb457; }

    /* Visible WhatsApp message box */
    .dt-wa-text {
        width:100%; min-height:220px; max-height:460px; box-sizing:border-box;
        padding:14px 16px; border:1px solid var(--pro-line); border-radius:12px;
        background:var(--pro-line-soft); color:var(--pro-text);
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        font-size:13.5px; line-height:1.6; white-space:pre; overflow:auto; resize:vertical;
    }
    .dt-wa-text:focus { outline:none; border-color:#25d366; box-shadow:0 0 0 2px rgba(37,211,102,.2); }

    /* Owner cards */
    .dt-owner { margin-bottom:14px; overflow:hidden; transition:box-shadow .18s; }
    .dt-owner:hover { box-shadow:0 10px 26px rgba(15,23,42,.08); }
    .dt-owner-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--pro-line-soft); }
    .dt-owner-id { display:flex; align-items:center; gap:12px; }
    .dt-avatar { width:44px; height:44px; border-radius:13px; flex:none; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; letter-spacing:-.5px; box-shadow:0 4px 12px rgba(15,23,42,.18); }
    .dt-owner-name { font-size:15px; font-weight:800; color:var(--pro-text); text-transform:uppercase; letter-spacing:.02em; }
    .dt-owner-sub { font-size:11.5px; color:var(--pro-muted); margin-top:1px; }

    .dt-clients { list-style:none; margin:0; padding:8px 14px 12px; }
    .dt-clients li { display:flex; align-items:flex-start; gap:12px; padding:10px 8px; border-radius:11px; border-bottom:1px solid var(--pro-line-soft); transition:background .12s; }
    .dt-clients li:hover { background:var(--pro-line-soft); }
    .dt-clients li:last-child { border-bottom:0; }
    .dt-check { flex:none; width:24px; height:24px; margin-top:1px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#eef2ff; color:#4f46e5; }
    .dt-check svg { width:13px; height:13px; }
    .dt-check.is-new { background:#dcfce7; color:#16a34a; }
    .dt-client-main { flex:1; min-width:0; }
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
            navigator.clipboard.writeText(text).then(done, function () { el.focus(); el.select(); document.execCommand('copy'); done(); });
        } else {
            el.focus(); el.select(); document.execCommand('copy'); done();
        }
    });
});
</script>
@endpush
@endsection
