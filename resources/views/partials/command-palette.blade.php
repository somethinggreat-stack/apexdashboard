{{--
    Command palette (Ctrl / ⌘-K) — jump to any client or business owner from
    anywhere in the console. Client search reuses the already-scoped
    client-selector.search endpoint; picking a result uses client-selector.select
    (which sets the owner and redirects to a whitelisted /admin URL). Included by
    layouts/admin-pro only, so leads agents never get it.
--}}
@php
    $cmdkMe  = Auth::guard('admin')->user();
    $cmdkBos = ($cmdkMe && ! $cmdkMe->isLeads())
        ? \App\Models\Client::forAdmin($cmdkMe->dataOwnerId())->active()->orderBy('business_name')
            ->get(['id', 'business_name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->business_name])->values()
        : collect();
@endphp
@if ($cmdkMe && ! $cmdkMe->isLeads())
<div id="cmdk" class="cmdk" hidden>
    <div class="cmdk-backdrop" data-cmdk-close></div>
    <div class="cmdk-panel" role="dialog" aria-modal="true" aria-label="Quick search">
        <div class="cmdk-inner">
            <div class="cmdk-head">
                <div class="cmdk-head-l">
                    <span class="cmdk-head-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                    </span>
                    <div>
                        <div class="cmdk-head-title">Quick Search</div>
                        <div class="cmdk-head-sub">Find clients or business owners instantly</div>
                    </div>
                </div>
                <button type="button" class="cmdk-close" data-cmdk-close aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="cmdk-field">
                <span class="cmdk-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg></span>
                <input id="cmdkInput" type="text" placeholder="Search clients or business owners…" autocomplete="off" spellcheck="false" aria-label="Search">
                <kbd class="cmdk-enterhint">↵ enter</kbd>
            </div>

            <div id="cmdkResults" class="cmdk-results" role="listbox"></div>

            <div class="cmdk-foot">
                <div class="cmdk-foot-keys">
                    <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                    <span><kbd>↵</kbd> open</span>
                    <span><kbd>esc</kbd> close</span>
                </div>
                <div class="cmdk-foot-tag">Faster access. More progress.</div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden form that performs the "select owner + go to page" jump. --}}
<form id="cmdkGo" method="POST" action="" style="display:none;">
    @csrf
    <input type="hidden" name="redirect_to" id="cmdkRedirect">
</form>

<style>
    @property --cmdk-ang { syntax:'<angle>'; inherits:false; initial-value:0deg; }

    .cmdk { position:fixed; inset:0; z-index:2000; display:flex; align-items:flex-start; justify-content:center; }
    .cmdk[hidden] { display:none; }
    .cmdk-backdrop { position:absolute; inset:0;
        background:radial-gradient(1200px circle at 50% -10%, rgba(99,102,241,.32), transparent 55%), rgba(3,6,18,.66);
        backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); animation:cmdkFade .22s ease both; }
    @keyframes cmdkFade { from { opacity:0; } to { opacity:1; } }

    /* Panel: animated gradient-ring border + deep glow. */
    .cmdk-panel { position:relative; width:min(760px,94vw); margin-top:8vh; border-radius:24px; overflow:hidden;
        box-shadow:0 46px 140px rgba(3,7,18,.62), 0 0 80px -8px rgba(99,102,241,.55);
        animation:cmdkPop .4s cubic-bezier(.16,1,.3,1) both; }
    @keyframes cmdkPop { 0% { opacity:0; transform:translateY(-18px) scale(.93); } 55% { opacity:1; } 100% { opacity:1; transform:none; } }
    .cmdk-panel::before { content:''; position:absolute; inset:0; border-radius:24px; padding:1.6px; pointer-events:none; z-index:2;
        background:conic-gradient(from var(--cmdk-ang), #22d3ee, #6366f1, #a855f7, #38bdf8, #22d3ee);
        -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite:xor;
        mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); mask-composite:exclude;
        animation:cmdkSpin 7s linear infinite; }
    @keyframes cmdkSpin { to { --cmdk-ang:360deg; } }
    .cmdk-inner { position:relative; z-index:1; border-radius:24px; background:var(--pro-surface,#fff); }

    .cmdk-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px 4px; }
    .cmdk-head-l { display:flex; align-items:center; gap:13px; }
    .cmdk-head-badge { flex:none; width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center;
        color:#fff; background:linear-gradient(135deg,#4f46e5,#22d3ee); box-shadow:0 8px 18px rgba(79,70,229,.36); }
    .cmdk-head-badge svg { width:19px; height:19px; }
    .cmdk-head-title { font-size:16px; font-weight:800; letter-spacing:-.02em; color:var(--pro-text,#0f172a); }
    .cmdk-head-sub { font-size:12px; color:#94a3b8; margin-top:1px; }
    .cmdk-close { flex:none; width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; cursor:pointer;
        background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); color:#64748b; transition:all .16s; }
    .cmdk-close svg { width:17px; height:17px; }
    .cmdk-close:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; transform:rotate(90deg); }

    /* Search field: glows on focus. */
    .cmdk-field { display:flex; align-items:center; gap:14px; margin:12px 22px 4px; padding:12px 14px; border-radius:16px;
        background:var(--pro-soft,#f7f9fc); border:1.6px solid var(--pro-line,#e6ebf2); transition:border-color .16s, box-shadow .2s, background .16s; }
    .cmdk-field:focus-within { border-color:#6366f1; background:var(--pro-surface,#fff);
        box-shadow:0 0 0 4px rgba(99,102,241,.16), 0 0 34px -6px rgba(99,102,241,.55); }
    .cmdk-ico { flex:none; width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center;
        color:#fff; background:linear-gradient(135deg,#4f46e5,#6366f1); box-shadow:0 6px 15px rgba(79,70,229,.34); }
    .cmdk-ico svg { width:20px; height:20px; }
    .cmdk-field input { flex:1; border:0; outline:none; background:transparent; font-size:20px; font-weight:500; letter-spacing:-.01em; color:var(--pro-text,#0f172a); }
    .cmdk-field input::placeholder { color:#9aa7bd; font-weight:400; }
    .cmdk-enterhint { flex:none; font-size:11px; font-weight:700; color:#64748b; background:var(--pro-surface,#fff);
        border:1px solid var(--pro-line,#e2e8f0); border-bottom-width:2px; border-radius:8px; padding:5px 10px; }

    .cmdk-results { max-height:52vh; overflow-y:auto; padding:8px 12px 12px; scrollbar-width:thin; }
    .cmdk-results::-webkit-scrollbar { width:10px; }
    .cmdk-results::-webkit-scrollbar-thumb { background:rgba(100,116,139,.30); border-radius:8px; border:3px solid transparent; background-clip:content-box; }
    .cmdk-group { display:flex; align-items:center; gap:8px; font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#a0abc0; padding:14px 10px 8px; }
    .cmdk-group::after { content:''; flex:1; height:1px; background:var(--pro-line,#eef2f7); }

    .cmdk-item { position:relative; display:flex; align-items:center; gap:14px; padding:11px 13px; border-radius:14px; cursor:pointer;
        animation:cmdkItem .3s cubic-bezier(.2,.7,.2,1) both; }
    @keyframes cmdkItem { from { opacity:0; transform:translateY(9px); } to { opacity:1; transform:none; } }
    .cmdk-item:nth-child(1){animation-delay:.01s}.cmdk-item:nth-child(2){animation-delay:.03s}.cmdk-item:nth-child(3){animation-delay:.05s}.cmdk-item:nth-child(4){animation-delay:.07s}.cmdk-item:nth-child(5){animation-delay:.09s}.cmdk-item:nth-child(6){animation-delay:.11s}.cmdk-item:nth-child(7){animation-delay:.13s}.cmdk-item:nth-child(8){animation-delay:.15s}.cmdk-item:nth-child(9){animation-delay:.17s}.cmdk-item:nth-child(n+10){animation-delay:.19s}
    .cmdk-item.active { background:linear-gradient(90deg, rgba(99,102,241,.14), rgba(56,189,248,.05)); }
    .cmdk-item.active::before { content:''; position:absolute; left:0; top:9px; bottom:9px; width:4px; border-radius:0 4px 4px 0; background:linear-gradient(180deg,#6366f1,#22d3ee); }
    .cmdk-mono { flex:none; width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size:15px; box-shadow:0 6px 15px rgba(15,23,42,.22); transition:transform .16s cubic-bezier(.2,.7,.2,1); }
    .cmdk-item.active .cmdk-mono { transform:scale(1.07); }
    .cmdk-body { min-width:0; flex:1; }
    .cmdk-title { font-size:15.5px; font-weight:700; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cmdk-owner { display:inline-flex; align-items:center; gap:6px; margin-top:2px; font-size:12.5px; color:#64748b; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cmdk-owner svg { flex:none; width:13px; height:13px; color:#818cf8; }
    .cmdk-owner .lbl { color:#a0abc0; font-weight:600; }
    .cmdk-owner b { font-weight:700; color:#475569; }
    .cmdk-pill { flex:none; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px; background:var(--pro-soft,#eef2f7); color:#64748b; }
    .cmdk-pill.ok { background:#ecfdf5; color:#059669; } .cmdk-pill.warn { background:#fef3c7; color:#b45309; } .cmdk-pill.bad { background:#fef2f2; color:#dc2626; }
    .cmdk-enter { flex:none; font-size:11px; font-weight:800; color:#6366f1; opacity:0; transform:translateX(-4px); transition:opacity .14s, transform .14s; }
    .cmdk-item.active .cmdk-enter { opacity:1; transform:none; }

    .cmdk-empty { display:flex; flex-direction:column; align-items:center; gap:12px; padding:48px 16px; color:#94a3b8; font-size:14px; }
    .cmdk-empty .cmdk-empty-ico { width:52px; height:52px; border-radius:16px; display:flex; align-items:center; justify-content:center;
        background:var(--pro-soft,#f1f5f9); color:#c3cad9; }
    .cmdk-empty .cmdk-empty-ico svg { width:24px; height:24px; }

    .cmdk-foot { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:13px 22px; border-top:1px solid var(--pro-line,#eef2f7); color:#94a3b8; font-size:12px; }
    .cmdk-foot-keys { display:flex; gap:16px; }
    .cmdk-foot kbd { font-family:inherit; font-size:11px; background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); border-bottom-width:2px; border-radius:6px; padding:1px 6px; margin-right:3px; }
    .cmdk-foot-tag { font-style:italic; font-weight:600; color:#a0abc0; white-space:nowrap; }
    @media (max-width:560px){ .cmdk-foot-tag { display:none; } }

    /* Dark theme */
    :root[data-theme="dark"] .cmdk-inner { background:#0d1424; }
    :root[data-theme="dark"] .cmdk-field { background:#0a1120; border-color:#233150; }
    :root[data-theme="dark"] .cmdk-field:focus-within { background:#0c1424; }
    :root[data-theme="dark"] .cmdk-group::after, :root[data-theme="dark"] .cmdk-foot { border-color:#1e293b; }
    :root[data-theme="dark"] .cmdk-item.active { background:linear-gradient(90deg, rgba(99,102,241,.22), rgba(56,189,248,.05)); }
    :root[data-theme="dark"] .cmdk-close, :root[data-theme="dark"] .cmdk-enterhint, :root[data-theme="dark"] .cmdk-pill, :root[data-theme="dark"] .cmdk-foot kbd, :root[data-theme="dark"] .cmdk-empty .cmdk-empty-ico { background:#141d33; border-color:#2b3b5e; color:#94a3b8; }
    :root[data-theme="dark"] .cmdk-owner b { color:#cbd5e1; }

    @media (prefers-reduced-motion: reduce) { .cmdk-panel, .cmdk-panel::before, .cmdk-item, .cmdk-backdrop { animation:none !important; } }
</style>

<script>
(function () {
    var el      = document.getElementById('cmdk');
    if (!el) return;
    var input   = document.getElementById('cmdkInput');
    var results = document.getElementById('cmdkResults');
    var goForm  = document.getElementById('cmdkGo');
    var goRedir = document.getElementById('cmdkRedirect');

    var BOS         = @json($cmdkBos);
    var SEARCH_URL  = @js(route('admin.client-selector.search'));
    var SELECT_TPL  = @js(route('admin.client-selector.select', ['id' => '__ID__']));
    var EU_BASE     = @js(url('/admin/end-users'));
    var CLIENTS_URL = @js(route('admin.client-list'));

    var PALETTE = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ec4899','#14b8a6','#f43f5e','#7c3aed','#0891b2'];
    function color(s){ var n=0; s=(s||'?'); for(var i=0;i<s.length;i++) n+=s.charCodeAt(i); return PALETTE[n%PALETTE.length]; }
    function initials(s){ var p=(s||'?').trim().split(/\s+/); return ((p[0]||'?')[0]+(p.length>1?(p[p.length-1]||'')[0]:'')).toUpperCase(); }
    function esc(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function pillClass(s){ s=(s||'').toLowerCase(); if(s==='done') return 'ok'; if(s.indexOf('error')!==-1||s.indexOf('hold')!==-1) return 'bad'; if(s.indexOf('new')!==-1) return 'warn'; return ''; }

    var BLD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg>';

    var items = [];
    var active = 0;
    var timer, token = 0;

    function open() {
        el.hidden = false;
        document.body.style.overflow = 'hidden';
        input.value = '';
        render([]);
        setTimeout(function () { input.focus(); }, 10);
    }
    function close() { el.hidden = true; document.body.style.overflow = ''; }
    function isOpen() { return !el.hidden; }

    function emptyBox(text) {
        return '<div class="cmdk-empty"><div class="cmdk-empty-ico">'
             + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>'
             + '</div>' + text + '</div>';
    }

    function render(list) {
        items = list;
        active = 0;
        if (!input.value.trim()) { results.innerHTML = emptyBox('Start typing a client or business owner name…'); return; }
        if (!list.length)        { results.innerHTML = emptyBox('No matches yet — keep typing…'); return; }

        var html = '', lastGroup = null;
        list.forEach(function (it, i) {
            if (it.group !== lastGroup) { html += '<div class="cmdk-group">' + it.group + '</div>'; lastGroup = it.group; }
            var mono = it.type === 'bo' ? BLD.replace('stroke="currentColor"', 'stroke="#fff"') : esc(initials(it.label));
            var sub  = it.type === 'client'
                ? '<div class="cmdk-owner">' + BLD + '<span class="lbl">Owner:</span> <b>' + esc(it.owner || '—') + '</b></div>'
                : '<div class="cmdk-owner"><span class="lbl">Business owner</span></div>';
            html += '<div class="cmdk-item' + (i === 0 ? ' active' : '') + '" data-i="' + i + '">'
                  + '<div class="cmdk-mono" style="background:' + color(it.label) + '">' + mono + '</div>'
                  + '<div class="cmdk-body"><div class="cmdk-title">' + esc(it.label) + '</div>' + sub + '</div>'
                  + (it.pill ? '<div class="cmdk-pill ' + pillClass(it.pill) + '">' + esc(it.pill) + '</div>' : '')
                  + '<span class="cmdk-enter">↵</span>'
                  + '</div>';
        });
        results.innerHTML = html;
    }

    function setActive(i) {
        var rows = results.querySelectorAll('.cmdk-item');
        if (!rows.length) return;
        active = (i + rows.length) % rows.length;
        rows.forEach(function (r, k) { r.classList.toggle('active', k === active); });
        rows[active].scrollIntoView({ block: 'nearest' });
    }

    function activate(i) {
        var it = items[i];
        if (!it) return;
        goForm.action = SELECT_TPL.replace('__ID__', it.boId);
        goRedir.value = it.type === 'client' ? (EU_BASE + '/' + it.clientId) : CLIENTS_URL;
        goForm.submit();
    }

    function search(q) {
        var list = [];
        var bq = q.toLowerCase();
        BOS.filter(function (b) { return b.name.toLowerCase().indexOf(bq) !== -1; })
           .slice(0, 6)
           .forEach(function (b) { list.push({ type: 'bo', group: 'Business Owners', label: b.name, boId: b.id }); });
        render(list);

        var t = ++token;
        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (t !== token) return;
                var merged = list.slice();
                (res.results || []).forEach(function (c) {
                    merged.push({ type: 'client', group: 'Clients', label: c.name, owner: c.bo_name, pill: c.status, boId: c.bo_id, clientId: c.id });
                });
                render(merged);
            })
            .catch(function () {});
    }

    document.addEventListener('keydown', function (e) {
        var k = (e.key || '').toLowerCase();
        if ((e.metaKey || e.ctrlKey) && k === 'k') { e.preventDefault(); isOpen() ? close() : open(); return; }
        if (!isOpen()) return;
        if (k === 'escape') { e.preventDefault(); close(); }
        else if (k === 'arrowdown') { e.preventDefault(); setActive(active + 1); }
        else if (k === 'arrowup')   { e.preventDefault(); setActive(active - 1); }
        else if (k === 'enter')     { e.preventDefault(); activate(active); }
    });

    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 1) { render([]); return; }
        timer = setTimeout(function () { search(q); }, 90);
    });

    results.addEventListener('mousemove', function (e) { var row = e.target.closest('.cmdk-item'); if (row) setActive(parseInt(row.dataset.i, 10)); });
    results.addEventListener('click', function (e) { var row = e.target.closest('.cmdk-item'); if (row) activate(parseInt(row.dataset.i, 10)); });
    el.addEventListener('click', function (e) { if (e.target.closest('[data-cmdk-close]')) close(); });
})();
</script>
@endif
