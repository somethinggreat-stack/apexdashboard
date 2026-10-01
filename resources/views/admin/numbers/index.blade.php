@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Phone Numbers')
@section('subtitle', 'Take a number, use it, and the one-time code lands here. While you hold it nobody else can use it.')

@php
    $initials = function (?string $name) {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0] ?? '', 1, 1);
        return mb_strtoupper($a . $b) ?: '—';
    };
    $tints = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#f43f5e'];
    $tintFor = function (?string $s) use ($tints) {
        $h = 0;
        foreach (str_split((string) $s) as $ch) { $h = ($h * 31 + ord($ch)) % 4294967296; }
        return $tints[$h % count($tints)];
    };
    $pretty = function (string $e164) {
        return preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m)
            ? "+1 {$m[1]}-{$m[2]}-{$m[3]}" : $e164;
    };
    $available = $numbers->filter->isAvailable()->count();
@endphp

@section('content')

<style>
    .pn-wrap {
        --ink: var(--pro-text); --muted: var(--pro-muted); --line: var(--pro-line);
        --card: var(--pro-card); --well:#f8fafc; --soft:#eef2ff; --softi:#3730a3;
        --brand: var(--pro-indigo); --edge:#dfe4ee;
    }
    :root[data-theme="dark"] .pn-wrap {
        --well: rgba(255,255,255,.045); --soft: rgba(99,102,241,.18); --softi:#c7d2fe; --edge: var(--pro-line);
    }

    .pn-head { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding:16px 18px; }
    .pn-head-l { display:flex; align-items:center; gap:12px; min-width:0; }
    .pn-ico { width:38px; height:38px; border-radius:11px; display:grid; place-items:center; flex:none;
              background:linear-gradient(140deg,#34d399,#059669); color:#fff; box-shadow:0 6px 16px rgba(5,150,105,.28); }
    .pn-ico svg { width:19px; height:19px; }
    .pn-head h2 { margin:0; font-size:19px; letter-spacing:-.01em; color:var(--ink); }
    .pn-pills { display:flex; gap:6px; margin-top:3px; flex-wrap:wrap; }
    .pn-pill { font-size:11.5px; font-weight:600; padding:2.5px 9px; border-radius:999px; background:var(--soft); color:var(--softi); }
    .pn-pill.ghost { background:var(--well); color:var(--muted); }

    .pn-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid var(--edge); background:var(--card); color:var(--ink);
              padding:7px 13px; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none;
              transition:background .14s, border-color .14s, transform .08s; white-space:nowrap; }
    .pn-btn:hover { background:var(--well); }
    .pn-btn:active { transform:translateY(1px); }
    .pn-btn-primary { background:var(--brand); border-color:var(--brand); color:#fff; box-shadow:0 4px 14px rgba(79,70,229,.28); }
    .pn-btn-primary:hover { background:#4338ca; border-color:#4338ca; color:#fff; }
    .pn-btn-go { background:#059669; border-color:#059669; color:#fff; box-shadow:0 4px 14px rgba(5,150,105,.28); }
    .pn-btn-go:hover { background:#047857; border-color:#047857; color:#fff; }
    .pn-btn[disabled] { opacity:.45; cursor:not-allowed; }

    .pn-flash { margin:0 18px 14px; padding:11px 14px; border-radius:10px; font-size:13.5px; display:flex; gap:9px; align-items:flex-start; line-height:1.5; }
    .pn-flash.ok  { background:rgba(16,185,129,.12); color:#059669; border:1px solid rgba(16,185,129,.35); }
    .pn-flash.bad { background:rgba(239,68,68,.1);   color:#dc2626; border:1px solid rgba(239,68,68,.32); }
    :root[data-theme="dark"] .pn-flash.ok  { color:#6ee7b7; }
    :root[data-theme="dark"] .pn-flash.bad { color:#fca5a5; }

    /* ---------- the waiting / code strip ---------- */
    .pn-live { padding:0 18px 4px; display:grid; gap:10px; }
    .pn-live:empty { display:none; }
    .pn-slot { border:1px solid rgba(99,102,241,.4); background:var(--soft); border-radius:12px; padding:13px 15px;
               display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
    .pn-slot.has-code { border-color:rgba(16,185,129,.5); background:rgba(16,185,129,.12); }
    .pn-slot .who { font-size:12.5px; color:var(--muted); }
    .pn-slot b { color:var(--ink); font-size:14px; }
    .pn-dots { display:inline-flex; gap:4px; }
    .pn-dots i { width:6px; height:6px; border-radius:50%; background:#818cf8; animation:pnPulse 1.1s infinite; }
    .pn-dots i:nth-child(2) { animation-delay:.15s; }
    .pn-dots i:nth-child(3) { animation-delay:.3s; }
    @keyframes pnPulse { 0%,100% { opacity:.25; } 50% { opacity:1; } }

    .pn-code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:26px; font-weight:700;
               letter-spacing:.12em; color:#047857; }
    :root[data-theme="dark"] .pn-code { color:#6ee7b7; }
    .pn-body { font-size:12.5px; color:var(--muted); width:100%; margin-top:2px; word-break:break-word; }

    /* ---------- table ---------- */
    .pn-table { width:100%; border-collapse:collapse; }
    .pn-table th { text-align:left; font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:var(--muted);
                   font-weight:700; padding:11px 16px; border-bottom:1px solid var(--line); background:var(--well); }
    .pn-table td { padding:12px 16px; border-bottom:1px solid var(--pro-line-soft); vertical-align:middle; font-size:13.5px; color:var(--ink); }
    .pn-table tbody tr:last-child td { border-bottom:0; }
    .pn-table tbody tr:hover { background:var(--well); }
    .pn-table tr.mine { background:rgba(99,102,241,.07); }

    .pn-num { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; }
    .pn-label { font-weight:600; }
    .pn-av { width:26px; height:26px; border-radius:50%; display:inline-grid; place-items:center; color:#fff;
             font-size:10px; font-weight:700; vertical-align:middle; margin-right:7px; }
    .pn-tag { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:999px; }
    .pn-tag.free { background:rgba(16,185,129,.14); color:#047857; }
    .pn-tag.busy { background:rgba(245,158,11,.16); color:#b45309; }
    .pn-tag.mine { background:var(--soft); color:var(--softi); }
    :root[data-theme="dark"] .pn-tag.free { color:#6ee7b7; }
    :root[data-theme="dark"] .pn-tag.busy { color:#fcd34d; }

    .pn-empty { padding:44px 20px; text-align:center; color:var(--muted); }
    .pn-empty b { display:block; color:var(--ink); font-size:14.5px; margin-bottom:4px; }
</style>

<div class="pn-wrap">

@if (! $configured)
    <div class="pro-panel" style="margin-bottom:16px; padding-top:16px;">
        <div class="pn-flash bad">
            <span><strong>The number pool is not configured on this server yet.</strong><br>
            <code>GHL_RMS_TOKEN</code> and <code>GHL_RMS_LOCATION_ID</code> are missing from this server's <code>.env</code>.</span>
        </div>
    </div>
@endif

<div class="pro-panel" style="overflow:hidden;">

    <div class="pn-head">
        <div class="pn-head-l">
            <span class="pn-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
            </span>
            <div>
                <h2>Phone Numbers</h2>
                <div class="pn-pills">
                    <span class="pn-pill">{{ $available }} available</span>
                    <span class="pn-pill ghost">{{ $numbers->count() }} in pool</span>
                    <span class="pn-pill ghost">held for {{ $claimMinutes }} min</span>
                </div>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="pn-flash ok"><span>{{ session('status') }}</span></div>
    @endif
    @error('number')
        <div class="pn-flash bad"><span>{{ $message }}</span></div>
    @enderror

    {{-- Codes for the numbers THIS VA is holding. Filled in by the poll. --}}
    <div class="pn-live" id="pnLive"></div>

    <div class="table-scroll">
    <table class="pn-table">
        <thead>
            <tr>
                <th>Number</th>
                <th>Phone</th>
                <th>Status</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($numbers as $n)
            @php
                $free    = $n->isAvailable();
                $isMine  = ! $free && $n->claimed_by_admin_id === $me;
                $holder  = $n->holder?->full_name;
            @endphp
            <tr class="{{ $isMine ? 'mine' : '' }}" data-pn-row="{{ $n->id }}">
                <td><span class="pn-label">{{ $n->label }}</span></td>
                <td><span class="pn-num">{{ $pretty($n->phone) }}</span></td>
                <td>
                    @if ($free)
                        <span class="pn-tag free">Available</span>
                    @elseif ($isMine)
                        <span class="pn-tag mine">Yours · {{ $n->claimed_at?->diffForHumans(null, true) }}</span>
                    @else
                        {{-- VAs see who is holding a number, so they know who to chase
                             rather than waiting on a number that is not coming back. --}}
                        <span class="pn-av" style="background:{{ $tintFor($holder) }};">{{ $initials($holder) }}</span>
                        <span class="pn-tag busy">{{ $holder ?? 'In use' }} · {{ $n->claimed_at?->diffForHumans(null, true) }}</span>
                    @endif
                </td>
                <td style="text-align:right;" class="no-link">
                    @if ($free)
                        <form method="POST" action="{{ route('admin.numbers.claim', $n->id) }}" style="display:inline;">
                            @csrf
                            <button class="pn-btn pn-btn-go" @disabled(! $configured)>Use this number</button>
                        </form>
                    @elseif ($isMine)
                        <form method="POST" action="{{ route('admin.numbers.release', $n->id) }}" style="display:inline;">
                            @csrf
                            <button class="pn-btn">Release</button>
                        </form>
                    @else
                        <button class="pn-btn" disabled>In use</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    <div class="pn-empty">
                        <b>No numbers in the pool yet</b>
                        They are read from GoHighLevel. Check the connection settings on this server.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

</div>{{-- /.pn-wrap --}}

<script>
(function () {
    var POLL_URL   = @json(route('admin.numbers.poll'));
    var COPIED_URL = @json(route('admin.numbers.copied', ['id' => 0]));
    var EVERY      = {{ max(2, (int) $pollSeconds) }} * 1000;
    var CSRF       = (document.querySelector('meta[name=csrf-token]') || {}).content || '';

    var live = document.getElementById('pnLive');

    // The numbers this VA is holding, straight from the rendered table — no
    // second source of truth to drift from it.
    var mine = Array.prototype.map.call(document.querySelectorAll('tr.mine'), function (tr) {
        return {
            id:    tr.getAttribute('data-pn-row'),
            label: tr.querySelector('.pn-label').textContent,
            phone: tr.querySelector('.pn-num').textContent
        };
    });

    if (!mine.length) return;   // holding nothing: never calls the server

    // A waiting slot per held number, replaced in place when a code lands.
    var slots = {};
    mine.forEach(function (n) {
        var el = document.createElement('div');
        el.className = 'pn-slot';
        el.innerHTML = '<span class="pn-dots"><i></i><i></i><i></i></span>'
            + '<b></b><span class="who"></span>';
        el.querySelector('b').textContent = 'Waiting for a code…';
        el.querySelector('.who').textContent = n.label + ' · ' + n.phone;
        live.appendChild(el);
        slots[n.id] = el;
    });

    function flash(btn, word) {
        var was = btn.textContent;
        btn.textContent = word;
        setTimeout(function () { btn.textContent = was; }, 1200);
    }
    function copy(text, btn, done) {
        function ok() { flash(btn, 'Copied'); if (done) done(); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(ok, function () { fallback(text, btn, ok); });
        } else { fallback(text, btn, ok); }
    }
    function fallback(text, btn, ok) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); ok(); } catch (e) { flash(btn, 'Ctrl+C'); }
        ta.remove();
    }

    function paint(code) {
        var el = slots[code.number_id];
        if (!el || el.dataset.filled) return;
        el.dataset.filled = '1';
        el.className = 'pn-slot has-code';
        el.innerHTML = '';

        var left = document.createElement('div');
        left.style.cssText = 'display:flex; align-items:center; gap:14px; flex-wrap:wrap; flex:1;';

        var big = document.createElement('span');
        big.className = 'pn-code';
        // The extracted digits may be missing; the full text below is always shown.
        big.textContent = code.code || '—';

        var who = document.createElement('span');
        who.className = 'who';
        who.textContent = code.label + ' · from ' + (code.from || 'unknown');

        left.appendChild(big);
        left.appendChild(who);

        var btn = document.createElement('button');
        btn.className = 'pn-btn pn-btn-primary';
        btn.type = 'button';
        btn.textContent = code.code ? 'Copy code' : 'Copy message';
        btn.addEventListener('click', function () {
            copy(code.code || code.body, btn, function () { consume(code.otp_id, el); });
        });

        var body = document.createElement('div');
        body.className = 'pn-body';
        // textContent: whatever arrived by SMS is never treated as markup.
        body.textContent = code.body;

        el.appendChild(left);
        el.appendChild(btn);
        el.appendChild(body);
    }

    // Copied: tell the server, which records it, frees the number and removes
    // the thread from GoHighLevel. Then reload, so the table is authoritative
    // rather than something this script guessed at.
    function consume(otpId, el) {
        fetch(COPIED_URL.replace(/0$/, otpId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function () {
                el.querySelector('.who').textContent = 'Copied — number released';
                setTimeout(function () { window.location.reload(); }, 1100);
            })
            .catch(function () { /* the code is already on the clipboard */ });
    }

    var timer = null;
    function poll() {
        fetch(POLL_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                          credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { codes: [] }; })
            .then(function (d) { (d.codes || []).forEach(paint); })
            .catch(function () {})
            .then(function () { timer = setTimeout(poll, EVERY); });
    }

    // Stop while the tab is in the background: a VA with ten tabs open should
    // not have ten pollers hitting GoHighLevel.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); }
        else if (!document.hidden) { clearTimeout(timer); poll(); }
    });

    poll();
})();
</script>
@endsection
