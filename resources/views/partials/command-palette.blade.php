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
    <div class="cmdk-panel" role="dialog" aria-modal="true" aria-label="Search">
        <div class="cmdk-input-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
            <input id="cmdkInput" type="text" placeholder="Search clients or business owners…" autocomplete="off" spellcheck="false" aria-label="Search">
            <kbd class="cmdk-esc">esc</kbd>
        </div>
        <div id="cmdkResults" class="cmdk-results" role="listbox"></div>
        <div class="cmdk-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
            <span><kbd>↵</kbd> open</span>
            <span><kbd>esc</kbd> close</span>
        </div>
    </div>
</div>

{{-- Hidden form that performs the "select owner + go to page" jump. --}}
<form id="cmdkGo" method="POST" action="" style="display:none;">
    @csrf
    <input type="hidden" name="redirect_to" id="cmdkRedirect">
</form>

@push('head')
<style>
    .cmdk { position:fixed; inset:0; z-index:1000; display:flex; align-items:flex-start; justify-content:center; }
    .cmdk[hidden] { display:none; }
    .cmdk-backdrop { position:absolute; inset:0; background:rgba(6,10,25,.55); backdrop-filter:blur(3px); animation:cmdkFade .12s ease; }
    @keyframes cmdkFade { from { opacity:0; } to { opacity:1; } }
    .cmdk-panel { position:relative; width:min(640px,92vw); margin-top:12vh; background:var(--pro-surface,#fff);
        border:1px solid var(--pro-line,#e6ebf2); border-radius:16px; box-shadow:0 30px 80px rgba(3,7,18,.5);
        overflow:hidden; animation:cmdkRise .14s cubic-bezier(.2,.7,.2,1); }
    @keyframes cmdkRise { from { opacity:0; transform:translateY(-8px) scale(.99); } to { opacity:1; transform:none; } }
    .cmdk-input-row { display:flex; align-items:center; gap:11px; padding:15px 17px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .cmdk-input-row svg { width:19px; height:19px; color:#94a3b8; flex:none; }
    .cmdk-input-row input { flex:1; border:0; outline:none; background:transparent; font-size:16px; color:var(--pro-text,#0f172a); }
    .cmdk-input-row input::placeholder { color:#9aa7bd; }
    .cmdk-esc { font-size:11px; color:#94a3b8; background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); border-radius:6px; padding:2px 7px; }
    .cmdk-results { max-height:56vh; overflow-y:auto; padding:8px; }
    .cmdk-group { font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; padding:10px 10px 6px; }
    .cmdk-item { display:flex; align-items:center; gap:12px; padding:9px 11px; border-radius:11px; cursor:pointer; }
    .cmdk-item.active { background:var(--pro-soft,#eef4ff); }
    .cmdk-mono { flex:none; width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size:13px; }
    .cmdk-body { min-width:0; flex:1; }
    .cmdk-title { font-size:14px; font-weight:700; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cmdk-sub { font-size:12px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cmdk-pill { flex:none; font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:999px; background:var(--pro-soft,#eef2f7); color:#64748b; }
    .cmdk-empty { padding:26px 14px; text-align:center; color:#94a3b8; font-size:13.5px; }
    .cmdk-foot { display:flex; gap:16px; padding:10px 16px; border-top:1px solid var(--pro-line,#eef2f7); color:#94a3b8; font-size:11.5px; }
    .cmdk-foot kbd { font-family:inherit; background:var(--pro-soft,#f1f5f9); border:1px solid var(--pro-line,#e2e8f0); border-radius:5px; padding:1px 5px; margin-right:2px; }
    :root[data-theme="dark"] .cmdk-panel { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .cmdk-input-row, :root[data-theme="dark"] .cmdk-foot { border-color:#1e293b; }
    :root[data-theme="dark"] .cmdk-item.active { background:#182444; }
    :root[data-theme="dark"] .cmdk-esc, :root[data-theme="dark"] .cmdk-pill, :root[data-theme="dark"] .cmdk-foot kbd { background:#1a2440; border-color:#2b3b5e; color:#94a3b8; }
</style>
@endpush

@push('scripts')
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
        if (!input.value.trim()) {
            results.innerHTML = '<div class="cmdk-empty">Type a client or business owner name…</div>';
            return;
        }
        if (!list.length) {
            results.innerHTML = '<div class="cmdk-empty">No matches.</div>';
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
        if (q.length < 2) { render([]); return; }
        timer = setTimeout(function () { search(q); }, 140);
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
@endpush
@endif
