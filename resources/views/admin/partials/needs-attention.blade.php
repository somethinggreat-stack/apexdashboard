{{--
    Premium "Needs Attention" panel — shared by the super-admin Dashboard and the
    VA-side Select Business Owner page. Requires: $attention (array of
    ['client','pending','incomplete','overdue','score']).
--}}
@if (!empty($attention))
    @php
        $naTotNew  = array_sum(array_column($attention, 'pending'));
        $naTotInc  = array_sum(array_column($attention, 'incomplete'));
        $naTotOver = array_sum(array_column($attention, 'overdue'));
        $naCntNew  = count(array_filter($attention, fn ($a) => $a['pending'] > 0));
        $naCntInc  = count(array_filter($attention, fn ($a) => $a['incomplete'] > 0));
        $naCntOver = count(array_filter($attention, fn ($a) => $a['overdue'] > 0));
        $naPriority = function ($a) {
            $w = $a['overdue'] * 3 + $a['pending'] * 2 + $a['incomplete'];
            return $w >= 45 ? ['Critical', 4] : ($w >= 20 ? ['High', 3] : ($w >= 8 ? ['Medium', 2] : ['Low', 1]));
        };
        $nxAccents = ['#4f46e5','#ec4899','#0ea5e9','#10b981','#f59e0b','#8b5cf6','#f97316','#14b8a6','#f43f5e','#3b82f6'];
        $nxAccent = fn ($name) => $nxAccents[abs(crc32($name)) % count($nxAccents)];
    @endphp
    <div class="card nx-card">
        <div class="nx-head">
            <div class="nx-head-left">
                <span class="nx-head-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
                </span>
                <div>
                    <h2>Needs Attention</h2>
                    <p class="nx-sub">{{ count($attention) }} business owner{{ count($attention) === 1 ? '' : 's' }} require action</p>
                </div>
            </div>
            <div class="nx-head-right">
                <label class="nx-sort">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                    <select id="nxSort" onchange="nxSort(this.value)">
                        <option value="priority">Highest priority</option>
                        <option value="overdue">Most overdue</option>
                        <option value="incomplete">Most incomplete</option>
                        <option value="new">Most new</option>
                    </select>
                    <svg class="nx-sort-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6,9 12,15 18,9"/></svg>
                </label>
            </div>
        </div>

        <div class="nx-tiles">
            <div class="nx-tile nx-t-blue">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                <div><span class="nx-tile-lbl">New Clients</span><span class="nx-tile-val">{{ number_format($naTotNew) }}</span></div>
            </div>
            <div class="nx-tile nx-t-amber">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <div><span class="nx-tile-lbl">Incomplete</span><span class="nx-tile-val">{{ number_format($naTotInc) }}</span></div>
            </div>
            <div class="nx-tile nx-t-red">
                <span class="nx-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16"/></svg></span>
                <div><span class="nx-tile-lbl">Overdue</span><span class="nx-tile-val">{{ number_format($naTotOver) }}</span></div>
            </div>
        </div>

        <div class="nx-tabs">
            <button type="button" class="nx-tab is-active" data-tab="all">All <span>{{ count($attention) }}</span></button>
            <button type="button" class="nx-tab" data-tab="new">New <span>{{ $naCntNew }}</span></button>
            <button type="button" class="nx-tab" data-tab="incomplete">Incomplete <span>{{ $naCntInc }}</span></button>
            <button type="button" class="nx-tab" data-tab="overdue">Overdue <span>{{ $naCntOver }}</span></button>
        </div>

        <div class="nx-table">
            <div class="nx-thead">
                <span>Business Owner</span><span>New</span><span>Incomplete</span><span>Overdue</span><span>Priority</span><span class="nx-act-h">Action</span>
            </div>
            <div class="nx-tbody" id="nxBody">
                @foreach ($attention as $a)
                    @php
                        $bo = $a['client'];
                        [$plabel, $plevel] = $naPriority($a);
                        $accent = $nxAccent($bo->business_name);
                        $init = mb_strtoupper(mb_substr($bo->business_name, 0, 1));
                    @endphp
                    <div class="nx-row" data-new="{{ $a['pending'] }}" data-inc="{{ $a['incomplete'] }}" data-over="{{ $a['overdue'] }}" data-prio="{{ $plevel }}" data-score="{{ $a['score'] }}">
                        <span class="nx-bo">
                            <span class="nx-avatar" style="background:{{ $accent }}22; color:{{ $accent }};">{{ $init }}</span>
                            <span class="nx-name">{{ $bo->business_name }}</span>
                        </span>
                        <span class="nx-cell" data-h="New">@if ($a['pending'])<span class="nx-num nx-n-blue">{{ $a['pending'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell" data-h="Incomplete">@if ($a['incomplete'])<span class="nx-num nx-n-amber">{{ $a['incomplete'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell" data-h="Overdue">@if ($a['overdue'])<span class="nx-num nx-n-red">{{ $a['overdue'] }}</span>@else<span class="nx-dash">—</span>@endif</span>
                        <span class="nx-cell nx-prio nx-prio-{{ $plevel }}" data-h="Priority"><span class="nx-dot"></span>{{ $plabel }}</span>
                        <span class="nx-cell nx-act-cell">
                            <form method="POST" action="{{ route('admin.client-selector.select', $bo->id) }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ $a['pending'] ? route('admin.new-clients') : route('admin.end-users.index') }}">
                                <button type="submit" class="{{ $a['pending'] ? 'nx-btn-primary' : 'nx-btn-soft' }}">{{ $a['pending'] ? 'Review new client →' : 'View clients →' }}</button>
                            </form>
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="nx-empty" id="nxEmpty" style="display:none;">No business owners match this filter.</div>
        </div>
    </div>
@else
    <div class="card" style="padding:34px 22px; text-align:center; color:var(--muted);">
        Everything looks good — nothing needs attention right now.
    </div>
@endif

@push('head')
<style>
    .nx-card { margin-top:0; }
    .nx-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .nx-head-left { display:flex; align-items:center; gap:13px; }
    .nx-head-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .nx-head-ico { flex:none; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .nx-head-ico svg { width:22px; height:22px; }
    .nx-head h2 { margin:0; font-size:20px; font-weight:800; letter-spacing:-.01em; color:var(--text); }
    .nx-sub { margin:2px 0 0; font-size:13px; color:var(--muted); }
    .nx-sort { position:relative; display:inline-flex; align-items:center; }
    .nx-sort > svg:first-child { position:absolute; left:12px; width:15px; height:15px; color:var(--muted); pointer-events:none; }
    .nx-sort select { appearance:none; background:var(--surface); border:1px solid var(--border); border-radius:11px; color:var(--text); font:inherit; font-size:13px; font-weight:600; padding:9px 34px 9px 34px; cursor:pointer; }
    .nx-sort-chev { position:absolute; right:12px; width:13px; height:13px; color:var(--muted); pointer-events:none; }

    .nx-tiles { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
    .nx-tile { display:flex; align-items:center; gap:13px; padding:15px 17px; border-radius:14px; border:1px solid var(--border); background:var(--surface); }
    .nx-tile-ico { flex:none; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
    .nx-tile-ico svg { width:22px; height:22px; }
    .nx-tile-lbl { display:block; font-size:13px; font-weight:600; color:var(--muted); }
    .nx-tile-val { display:block; font-size:27px; font-weight:800; line-height:1.05; color:var(--text); }
    .nx-t-blue  { border-color:var(--tint-blue-fg);  } .nx-t-blue  .nx-tile-ico { background:var(--tint-blue-bg);  color:var(--tint-blue-fg);  } .nx-t-blue  .nx-tile-val { color:var(--tint-blue-fg); }
    .nx-t-amber { border-color:var(--tint-amber-fg); } .nx-t-amber .nx-tile-ico { background:var(--tint-amber-bg); color:var(--tint-amber-fg); } .nx-t-amber .nx-tile-val { color:var(--tint-amber-fg); }
    .nx-t-red   { border-color:var(--tint-red-fg);   } .nx-t-red   .nx-tile-ico { background:var(--tint-red-bg);   color:var(--tint-red-fg);   } .nx-t-red   .nx-tile-val { color:var(--tint-red-fg); }

    .nx-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
    .nx-tab { display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--muted); background:transparent; border:1px solid transparent; border-radius:10px; padding:7px 13px; cursor:pointer; transition:background .12s, color .12s; }
    .nx-tab span { font-size:11.5px; font-weight:800; padding:1px 8px; border-radius:999px; background:var(--surface-2); color:var(--muted); }
    .nx-tab:hover { background:var(--surface-2); color:var(--text); }
    .nx-tab.is-active { color:var(--tint-indigo-fg); background:var(--tint-indigo-bg); }
    .nx-tab.is-active span { background:var(--tint-indigo-fg); color:var(--on-accent, #fff); }

    .nx-thead, .nx-row { display:grid; grid-template-columns:minmax(150px,2.2fr) .8fr 1.1fr .9fr 1.1fr auto; gap:12px; align-items:center; }
    .nx-thead { padding:12px 10px; font-size:10.5px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--border); }
    .nx-thead span:nth-child(2), .nx-thead span:nth-child(3), .nx-thead span:nth-child(4) { text-align:center; }
    .nx-act-h { text-align:right; }
    .nx-row { padding:11px 10px; border-radius:10px; transition:background .12s; }
    .nx-row + .nx-row { border-top:1px solid var(--border-soft, var(--border)); }
    .nx-row:hover { background:var(--surface-2); }
    .nx-bo { display:flex; align-items:center; gap:11px; min-width:0; }
    .nx-avatar { flex:none; width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; }
    .nx-name { font-weight:700; font-size:14.5px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .nx-cell { display:flex; align-items:center; justify-content:center; }
    .nx-num { min-width:30px; text-align:center; font-size:13px; font-weight:800; padding:3px 10px; border-radius:8px; }
    .nx-n-blue  { background:var(--tint-blue-bg);  color:var(--tint-blue-fg); }
    .nx-n-amber { background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .nx-n-red   { background:var(--tint-red-bg);   color:var(--tint-red-fg); }
    .nx-dash { color:var(--muted); opacity:.55; }
    .nx-prio { justify-content:flex-start; gap:7px; font-size:13px; font-weight:700; }
    .nx-dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex:none; }
    .nx-prio-4 { color:var(--tint-red-fg); }
    .nx-prio-3 { color:var(--tint-amber-fg); }
    .nx-prio-2 { color:var(--tint-blue-fg); }
    .nx-prio-1 { color:var(--muted); }
    .nx-act-cell { justify-content:flex-end; }
    .nx-btn-primary { font-size:12.5px; font-weight:700; color:var(--on-accent, #fff); background:linear-gradient(135deg,#2563eb,#1d4ed8); border:0; border-radius:10px; padding:8px 15px; cursor:pointer; white-space:nowrap; box-shadow:0 6px 16px rgba(37,99,235,.28); transition:filter .12s; }
    .nx-btn-primary:hover { filter:brightness(1.06); }
    .nx-btn-soft { font-size:12.5px; font-weight:700; color:var(--text-soft, var(--text)); background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:8px 15px; cursor:pointer; white-space:nowrap; transition:background .12s; }
    .nx-btn-soft:hover { background:var(--surface-2); }
    .nx-empty { padding:26px; text-align:center; font-size:13px; color:var(--muted); }
    @media (max-width:720px){
        .nx-tiles { grid-template-columns:1fr; }
        .nx-thead { display:none; }
        .nx-row { grid-template-columns:1fr auto; gap:8px 10px; }
        .nx-bo { grid-column:1; } .nx-act-cell { grid-column:2; grid-row:1; }
        .nx-cell:not(.nx-act-cell) { justify-content:flex-start; }
        .nx-cell[data-h]::before { content:attr(data-h) ": "; font-size:11px; font-weight:700; color:var(--muted); margin-right:6px; text-transform:uppercase; letter-spacing:.04em; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var body = document.getElementById('nxBody');
    if (!body) return;
    var rows = Array.prototype.slice.call(body.querySelectorAll('.nx-row'));
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.nx-tab'));
    var empty = document.getElementById('nxEmpty');
    var activeTab = 'all';
    function num(el, k) { return parseInt(el.dataset[k] || '0', 10); }
    function applyFilter() {
        var shown = 0;
        rows.forEach(function (r) {
            var ok = activeTab === 'all'
                || (activeTab === 'new' && num(r, 'new') > 0)
                || (activeTab === 'incomplete' && num(r, 'inc') > 0)
                || (activeTab === 'overdue' && num(r, 'over') > 0);
            r.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (empty) empty.style.display = shown ? 'none' : '';
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.remove('is-active'); });
            t.classList.add('is-active');
            activeTab = t.dataset.tab;
            applyFilter();
        });
    });
    window.nxSort = function (key) {
        var map = { priority: 'prio', overdue: 'over', incomplete: 'inc', new: 'new' };
        var k = map[key] || 'prio';
        rows.slice().sort(function (a, b) {
            var d = num(b, k) - num(a, k);
            return d !== 0 ? d : num(b, 'score') - num(a, 'score');
        }).forEach(function (r) { body.appendChild(r); });
    };
})();
</script>
@endpush
