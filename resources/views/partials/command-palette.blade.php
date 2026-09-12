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
        <div class="cmdk-head">
            <div class="cmdk-head-l">
                <span class="cmdk-head-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                </span>
                <div>
                    <div class="cmdk-head-title">Quick Jump</div>
                    <div class="cmdk-head-sub">Find any client or business owner, instantly</div>
                </div>
            </div>
            <kbd class="cmdk-head-kbd" id="cmdkHeadKbd">⌘ K</kbd>
        </div>
        <div class="cmdk-input-row">
            <span class="cmdk-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg></span>
            <input id="cmdkInput" type="text" placeholder="Search clients or business owners…" autocomplete="off" spellcheck="false" aria-label="Search">
            <button type="button" class="cmdk-close" data-cmdk-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div id="cmdkResults" class="cmdk-results" role="listbox"></div>
        <div class="cmdk-foot">
            <div class="cmdk-foot-keys">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                <span><kbd>↵</kbd> open</span>
                <span><kbd>esc</kbd> close</span>
            </div>
            <div class="cmdk-foot-tag">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Searches across all your business owners
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
    .cmdk { position:fixed; inset:0; z-index:2000; display:flex; align-items:flex-start; justify-content:center; }
    .cmdk[hidden] { display:none; }
    .cmdk-backdrop { position:absolute; inset:0;
        background:radial-gradient(1200px circle at 50% -10%, rgba(79,70,229,.30), transparent 55%), rgba(4,7,20,.62);
        backdrop-filter:blur(7px); -webkit-backdrop-filter:blur(7px); animation:cmdkFade .2s ease both; }
    @keyframes cmdkFade { from { opacity:0; } to { opacity:1; } }

    .cmdk-panel { position:relative; width:min(760px,94vw); margin-top:8vh; background:var(--pro-surface,#fff);
        border:1px solid var(--pro-line,#e6ebf2); border-radius:24px; overflow:hidden;
        box-shadow:0 44px 130px rgba(3,7,18,.58), 0 0 66px -12px rgba(79,70,229,.48);
        animation:cmdkPop .36s cubic-bezier(.16,1,.3,1) both; }
    @keyframes cmdkPop { 0% { opacity:0; transform:translateY(-16px) scale(.94); } 60% { opacity:1; } 100% { opacity:1; transform:none; } }
    .cmdk-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,#4f46e5,#2563eb,#22d3ee); }

    .cmdk-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 24px 2px; }
    .cmdk-head-l { display:flex; align-items:center; gap:13px; }
    .cmdk-head-badge { flex:none; width:36px; height:36px; border-radius:11px; display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg,#4f46e5,#22d3ee); color:#fff; box-shadow:0 6px 15px rgba(79,70,229,.34); }
    .cmdk-head-badge svg { width:18px; height:18px; }
    .cmdk-head-title { font-size:15.5px; font-weight:800; letter-spacing:-.01em; color:var(--pro-text,#0f172a); }
    .cmdk-head-sub { font-size:12px; color:#94a3b8; margin-top:1px; }
    .cmdk-head-kbd { font-size:11px; font-weight:700; color:#64748b; background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); border-bottom-width:2px; border-radius:8px; padding:5px 11px; }

    .cmdk-input-row { display:flex; align-items:center; gap:15px; padding:16px 22px 18px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .cmdk-ico { flex:none; width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; box-shadow:0 8px 18px rgba(79,70,229,.34); }
    .cmdk-ico svg { width:21px; height:21px; }
    .cmdk-input-row input { flex:1; border:0; outline:none; background:transparent; font-size:21px; font-weight:500; letter-spacing:-.01em; color:var(--pro-text,#0f172a); }
    .cmdk-input-row input::placeholder { color:#9aa7bd; font-weight:400; }
    .cmdk-close { flex:none; width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; cursor:pointer;
        background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); color:#64748b; transition:background .12s, color .12s, transform .12s; }
    .cmdk-close svg { width:17px; height:17px; }
    .cmdk-close:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; transform:rotate(90deg); }

    .cmdk-results { max-height:58vh; overflow-y:auto; padding:10px; scrollbar-width:thin; }
    .cmdk-results::-webkit-scrollbar { width:10px; }
    .cmdk-results::-webkit-scrollbar-thumb { background:rgba(100,116,139,.30); border-radius:8px; border:3px solid transparent; background-clip:content-box; }
    .cmdk-group { font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#a0abc0; padding:14px 12px 7px; }

    .cmdk-item { position:relative; display:flex; align-items:center; gap:14px; padding:12px 14px; border-radius:14px; cursor:pointer;
        animation:cmdkItem .28s cubic-bezier(.2,.7,.2,1) both; }
    @keyframes cmdkItem { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
    .cmdk-item:nth-child(1){animation-delay:.01s}.cmdk-item:nth-child(2){animation-delay:.03s}.cmdk-item:nth-child(3){animation-delay:.05s}.cmdk-item:nth-child(4){animation-delay:.07s}.cmdk-item:nth-child(5){animation-delay:.09s}.cmdk-item:nth-child(6){animation-delay:.11s}.cmdk-item:nth-child(7){animation-delay:.13s}.cmdk-item:nth-child(8){animation-delay:.15s}.cmdk-item:nth-child(9){animation-delay:.17s}.cmdk-item:nth-child(n+10){animation-delay:.19s}
    .cmdk-item.active { background:linear-gradient(90deg, rgba(79,70,229,.13), rgba(37,99,235,.05)); }
    .cmdk-item.active::before { content:''; position:absolute; left:0; top:10px; bottom:10px; width:4px; border-radius:0 4px 4px 0; background:linear-gradient(180deg,#4f46e5,#22d3ee); }
    .cmdk-mono { flex:none; width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size:15px; box-shadow:0 6px 15px rgba(15,23,42,.20); transition:transform .16s cubic-bezier(.2,.7,.2,1); }
    .cmdk-item.active .cmdk-mono { transform:scale(1.06); }
    .cmdk-body { min-width:0; flex:1; }
    .cmdk-title { font-size:15.5px; font-weight:700; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cmdk-sub { font-size:12.5px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
    .cmdk-pill { flex:none; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px; background:var(--pro-soft,#eef2f7); color:#64748b; }
    .cmdk-enter { flex:none; font-size:11px; font-weight:800; color:#4f46e5; opacity:0; transform:translateX(-4px); transition:opacity .14s, transform .14s; }
    .cmdk-item.active .cmdk-enter { opacity:1; transform:none; }

    .cmdk-empty { display:flex; flex-direction:column; align-items:center; gap:11px; padding:46px 16px; color:#94a3b8; font-size:14px; }
    .cmdk-empty svg { width:30px; height:30px; color:#cbd5e1; }

    .cmdk-foot { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:13px 22px; border-top:1px solid var(--pro-line,#eef2f7); color:#94a3b8; font-size:12px; }
    .cmdk-foot-keys { display:flex; gap:16px; }
    .cmdk-foot kbd { font-family:inherit; font-size:11px; background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); border-bottom-width:2px; border-radius:6px; padding:1px 6px; margin-right:3px; }
    .cmdk-foot-tag { display:inline-flex; align-items:center; gap:6px; font-weight:600; color:#a0abc0; white-space:nowrap; }
    .cmdk-foot-tag svg { width:13px; height:13px; color:#818cf8; }
    @media (max-width:560px){ .cmdk-foot-tag { display:none; } }

    :root[data-theme="dark"] .cmdk-panel { background:#0e1526; border-color:#233150; box-shadow:0 40px 120px rgba(0,0,0,.7), 0 0 60px -12px rgba(99,102,241,.5); }
    :root[data-theme="dark"] .cmdk-input-row, :root[data-theme="dark"] .cmdk-foot { border-color:#1e293b; }
    :root[data-theme="dark"] .cmdk-item.active { background:linear-gradient(90deg, rgba(99,102,241,.20), rgba(34,211,238,.05)); }
    :root[data-theme="dark"] .cmdk-pill, :root[data-theme="dark"] .cmdk-foot kbd, :root[data-theme="dark"] .cmdk-head-kbd, :root[data-theme="dark"] .cmdk-close { background:#1a2440; border-color:#2b3b5e; color:#94a3b8; }
    @media (prefers-reduced-motion: reduce) { .cmdk-panel, .cmdk-item, .cmdk-backdrop { animation:none !important; } }
</style>

<script>
(function () {
    var el      = document.getElementById('cmdk');
    if (!el) return;
    // Show the right shortcut label for the platform (⌘ on Mac, Ctrl elsewhere).
    var headKbd = document.getElementById('cmdkHeadKbd');
    if (headKbd) headKbd.textContent = /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent) ? '⌘ K' : 'Ctrl K';
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

    var items = [];      // [{type,label,sub,boId,clientId}]
    var active = 0;
    var timer, token = 0;

    function open() {
        el.hidden = false;
        document.body.style.overflow = 'hidden';
        input.value = '';
        render([]);
        setTimeout(function () { input.focus(); }, 10);
    }
    function close() {
        el.hidden = true;
        document.body.style.overflow = '';
    }
    function isOpen(){ return !el.hidden; }

    function render(list) {
        items = list;
        active = 0;
        var ico = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>';
        if (!input.value.trim()) {
            results.innerHTML = '<div class="cmdk-empty">' + ico + 'Type a client or business owner name…</div>';
            return;
        }
        if (!list.length) {
            results.innerHTML = '<div class="cmdk-empty">' + ico + 'No matches yet — keep typing…</div>';
            return;
        }
        var html = '', lastGroup = null;
        list.forEach(function (it, i) {
            if (it.group !== lastGroup) { html += '<div class="cmdk-group">' + it.group + '</div>'; lastGroup = it.group; }
            var mono = it.type === 'bo'
                ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/></svg>'
                : esc(initials(it.label));
            html += '<div class="cmdk-item' + (i === 0 ? ' active' : '') + '" data-i="' + i + '">'
                  + '<div class="cmdk-mono" style="background:' + color(it.label) + '">' + mono + '</div>'
                  + '<div class="cmdk-body"><div class="cmdk-title">' + esc(it.label) + '</div>'
                  + (it.sub ? '<div class="cmdk-sub">' + esc(it.sub) + '</div>' : '') + '</div>'
                  + (it.pill ? '<div class="cmdk-pill">' + esc(it.pill) + '</div>' : '')
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
        // Business owners — instant, client-side.
        var bq = q.toLowerCase();
        BOS.filter(function (b) { return b.name.toLowerCase().indexOf(bq) !== -1; })
           .slice(0, 6)
           .forEach(function (b) { list.push({ type: 'bo', group: 'Business Owners', label: b.name, sub: 'Open this owner', boId: b.id }); });
        render(list);   // show BO matches immediately

        // Clients — from the scoped endpoint.
        var t = ++token;
        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (t !== token) return;
                var merged = list.slice();
                (res.results || []).forEach(function (c) {
                    merged.push({ type: 'client', group: 'Clients', label: c.name, sub: c.bo_name || c.email, pill: c.status, boId: c.bo_id, clientId: c.id });
                });
                render(merged);
            })
            .catch(function () {});
    }

    // Open/close shortcuts
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
        if (q.length < 1) { render([]); return; }   // results from the first keystroke
        timer = setTimeout(function () { search(q); }, 90);
    });

    results.addEventListener('mousemove', function (e) {
        var row = e.target.closest('.cmdk-item'); if (row) setActive(parseInt(row.dataset.i, 10));
    });
    results.addEventListener('click', function (e) {
        var row = e.target.closest('.cmdk-item'); if (row) activate(parseInt(row.dataset.i, 10));
    });
    el.addEventListener('click', function (e) { if (e.target.closest('[data-cmdk-close]')) close(); });
})();
</script>
@endif
