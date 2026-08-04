@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Universal Search')

@section('content')
<div class="card usearch-card">
    <div class="usearch-head">
        <span class="usearch-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
        </span>
        <div>
            <h2>Universal Search</h2>
            <p class="usearch-sub">Find any client across every business owner and open them directly — no need to pick the owner first.</p>
        </div>
    </div>

    <div class="usearch-bar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
        <input type="text" id="uSearch" autocomplete="off" autofocus placeholder="Search any client by name, email or phone…">
    </div>

    <div id="uSearchResults" class="usearch-results" aria-live="polite"></div>

    <div id="uSearchEmpty" class="usearch-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
        <p>Start typing a name, email or phone number to search across all your business owners.</p>
    </div>
</div>

@push('head')
<style>
    .usearch-head { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .usearch-ico { width:44px; height:44px; flex:none; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; color:#fff; background:linear-gradient(135deg,#6366f1,#4f46e5); box-shadow:0 8px 18px rgba(79,70,229,.28); }
    .usearch-ico svg { width:22px; height:22px; }
    .usearch-head h2 { margin:0; font-size:21px; font-weight:800; color:var(--text); letter-spacing:-.01em; }
    .usearch-sub { margin:3px 0 0; font-size:13px; color:var(--muted); }

    .usearch-bar { position:relative; display:flex; align-items:center; }
    .usearch-bar > svg { position:absolute; left:16px; width:20px; height:20px; color:var(--muted); pointer-events:none; }
    .usearch-bar input {
        width:100%; padding:15px 16px 15px 48px; font:inherit; font-size:15.5px;
        color:var(--text); background:var(--surface); border:1px solid var(--border);
        border-radius:13px; box-shadow:0 1px 2px rgba(15,23,42,.04);
    }
    .usearch-bar input:focus { outline:none; border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.14); }

    .usearch-results { margin-top:14px; display:flex; flex-direction:column; gap:8px; }
    .usearch-results:empty { display:none; }
    .ures-form { margin:0; }
    .ures-item {
        width:100%; text-align:left; cursor:pointer;
        display:flex; align-items:center; gap:12px; padding:13px 15px; border-radius:12px;
        background:var(--surface); border:1px solid var(--border);
        transition:border-color .12s, transform .1s, box-shadow .12s;
    }
    .ures-item:hover { border-color:#4f46e5; transform:translateY(-1px); box-shadow:0 8px 18px rgba(15,23,42,.08); }
    .ures-av { width:38px; height:38px; flex:none; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; background:linear-gradient(135deg,#6366f1,#4338ca); }
    .ures-body { display:flex; flex-direction:column; gap:4px; min-width:0; flex:1; }
    .ures-name { font-weight:700; font-size:14.5px; color:var(--text); }
    .ures-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12.5px; color:var(--muted); min-width:0; }
    /* Business owner the client belongs to — a clearly-labelled chip. */
    .ures-bo { display:inline-flex; align-items:center; gap:5px; flex:none; font-weight:700; color:#4338ca; background:#eef2ff; padding:3px 10px; border-radius:999px; }
    .ures-bo svg { width:12px; height:12px; }
    :root[data-theme="dark"] .ures-bo { background:rgba(99,102,241,.16); color:#c7d2fe; }
    .ures-email { color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
    .ures-pill { flex:none; font-size:10.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; }
    .ures-open { flex:none; font-size:13px; font-weight:700; color:#4f46e5; white-space:nowrap; }
    .usearch-hint { padding:16px; text-align:center; font-size:13.5px; color:var(--muted); }

    .usearch-empty { text-align:center; padding:34px 16px 24px; color:var(--muted); }
    .usearch-empty svg { width:44px; height:44px; opacity:.35; margin-bottom:12px; }
    .usearch-empty p { margin:0; font-size:14px; max-width:420px; margin-inline:auto; line-height:1.5; }

    @media (max-width:600px){ .ures-meta { max-width:150px; } .ures-open { display:none; } }
</style>
@endpush

@push('scripts')
<script>
/* Universal Search page: live-search clients across all of the VA's business
   owners and render results. Each result posts to the owner-selector with a
   redirect straight to that client's profile. */
(function () {
    var input = document.getElementById('uSearch');
    var box   = document.getElementById('uSearchResults');
    var empty = document.getElementById('uSearchEmpty');
    if (!input || !box) return;

    var searchUrl  = @json(route('admin.client-selector.search'));
    var selectBase = @json(url('/admin/select-business-owner'));
    var showBase   = @json(url('/admin/end-users'));
    var csrf       = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var timer, controller;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    function initials(name) {
        return (name || '?').split(/\s+/).filter(Boolean).slice(0, 2)
            .map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
    }
    function showEmpty(on) { if (empty) empty.style.display = on ? '' : 'none'; }

    function render(results, q) {
        showEmpty(false);
        if (!results.length) {
            box.innerHTML = '<div class="usearch-hint">No clients match &ldquo;' + esc(q) + '&rdquo;.</div>';
            return;
        }
        box.innerHTML = results.map(function (r) {
            return '<form method="POST" action="' + selectBase + '/' + r.bo_id + '" class="ures-form">'
                + '<input type="hidden" name="_token" value="' + esc(csrf) + '">'
                + '<input type="hidden" name="redirect_to" value="' + showBase + '/' + r.id + '">'
                + '<button type="submit" class="ures-item">'
                +   '<span class="ures-av">' + esc(initials(r.name)) + '</span>'
                +   '<span class="ures-body">'
                +     '<span class="ures-name">' + esc(r.name) + '</span>'
                +     '<span class="ures-meta">'
                +       '<span class="ures-bo" title="Business owner">'
                +         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/></svg>'
                +         esc(r.bo_name || '—')
                +       '</span>'
                +       (r.email ? '<span class="ures-email">' + esc(r.email) + '</span>' : '')
                +     '</span>'
                +   '</span>'
                +   '<span class="ures-pill">' + esc(r.status) + '</span>'
                +   '<span class="ures-open">Open &rarr;</span>'
                + '</button></form>';
        }).join('');
    }

    function run() {
        var q = input.value.trim();
        if (q.length < 2) { box.innerHTML = ''; showEmpty(true); return; }
        if (controller) controller.abort();
        controller = new AbortController();
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { render(d.results || [], q); })
            .catch(function () { /* aborted or network — ignore */ });
    }

    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 220); });
})();
</script>
@endpush
@endsection
