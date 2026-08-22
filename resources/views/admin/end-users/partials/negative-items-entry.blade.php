{{-- Negative-items repeater for the Add Client page. Item type is chosen first;
     the fields adapt (account #, inquiry date, bankruptcy ref, or name only). --}}
<div class="form-section">
    <h4>Negative Items <span class="muted">(optional — you can add these now or later on the client's Results tab)</span></h4>
    <p class="muted small" style="margin:0 0 10px;">
        Pick the <strong>item type</strong> first, then fill the fields that appear. Only a <strong>Negative Account</strong> can be
        <em>Updated to positive</em>; everything else is a deletion. Add more with <strong>+ Add Item</strong>. Leave blank to skip.
    </p>

    @php
        $niCats = \App\Models\NegativeItem::CATEGORIES;
        $niGoals = \App\Models\NegativeItem::GOALS;
        $niBureaus = \App\Models\NegativeItem::BUREAUS;
        $oldItems = collect(old('negative_items', []))
            ->filter(fn ($r) => trim((string) ($r['name'] ?? '')) !== '')->values();
        if ($oldItems->isEmpty()) {
            $oldItems = collect([['category' => 'negative_account', 'name' => '', 'detail' => '', 'goal' => 'delete', 'bureau' => 'all']]);
        }
    @endphp

    <div id="niRows" class="ni-rows">
        @foreach ($oldItems as $oi)
            <div class="ni-row">
                <select name="negative_items[{{ $loop->index }}][category]" data-ni="category">
                    @foreach ($niCats as $k => $v)<option value="{{ $k }}" @selected(($oi['category'] ?? 'negative_account') === $k)>{{ $v }}</option>@endforeach
                </select>
                <input type="text" name="negative_items[{{ $loop->index }}][name]" data-ni="name" value="{{ $oi['name'] ?? '' }}" maxlength="255">
                <input type="text" name="negative_items[{{ $loop->index }}][detail]" data-ni="detail" value="{{ $oi['detail'] ?? '' }}" maxlength="255">
                <select name="negative_items[{{ $loop->index }}][goal]" data-ni="goal">
                    @foreach ($niGoals as $k => $v)<option value="{{ $k }}" @selected(($oi['goal'] ?? 'delete') === $k)>{{ $v }}</option>@endforeach
                </select>
                <select name="negative_items[{{ $loop->index }}][bureau]" data-ni="bureau">
                    @foreach ($niBureaus as $k => $v)<option value="{{ $k }}" @selected(($oi['bureau'] ?: 'all') === $k)>{{ $v }}</option>@endforeach
                </select>
                <button type="button" class="ni-del" onclick="this.closest('.ni-row').remove()" title="Remove">✕</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm" onclick="niAddRow()">+ Add Item</button>

    <template id="niRowTpl">
        <div class="ni-row">
            <select name="negative_items[__IDX__][category]" data-ni="category">
                @foreach ($niCats as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <input type="text" name="negative_items[__IDX__][name]" data-ni="name" maxlength="255">
            <input type="text" name="negative_items[__IDX__][detail]" data-ni="detail" maxlength="255">
            <select name="negative_items[__IDX__][goal]" data-ni="goal">
                @foreach ($niGoals as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <select name="negative_items[__IDX__][bureau]" data-ni="bureau">
                @foreach ($niBureaus as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <button type="button" class="ni-del" onclick="this.closest('.ni-row').remove()" title="Remove">✕</button>
        </div>
    </template>
</div>

<style>
    .ni-rows { display:flex; flex-direction:column; gap:8px; margin-bottom:10px; }
    .ni-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .ni-row [data-ni="category"] { flex:0 0 170px; }
    .ni-row [data-ni="name"] { flex:1 1 220px; min-width:180px; }
    .ni-row [data-ni="detail"] { flex:0 0 180px; }
    .ni-row [data-ni="goal"] { flex:0 0 150px; }
    .ni-row [data-ni="bureau"] { flex:0 0 150px; }
    .ni-row input, .ni-row select { padding:8px 10px; border:1px solid #d7dee8; border-radius:8px; font-size:13px; background:#fff; color:#0f172a; }
    .ni-del { flex:0 0 auto; border:none; background:#fee2e2; color:#b91c1c; border-radius:8px; height:34px; width:34px; cursor:pointer; font-weight:700; }
    :root[data-theme="dark"] .ni-row input, :root[data-theme="dark"] .ni-row select { background:#10152a; border-color:var(--pro-line); color:var(--pro-text); }
</style>

@include('admin.partials.negative-item-script')
<script>
    window.niIdx = {{ $oldItems->count() }};
    window.niAddRow = function () {
        var rows = document.getElementById('niRows'), tpl = document.getElementById('niRowTpl');
        if (!rows || !tpl) return;
        var node = tpl.content.firstElementChild.cloneNode(true);
        var idx = window.niIdx++;
        node.querySelectorAll('[name]').forEach(function (el) {
            el.setAttribute('name', el.getAttribute('name').replace('__IDX__', idx));
        });
        rows.appendChild(node);
        window.niBind(node);
    };
    document.querySelectorAll('#niRows .ni-row').forEach(function (r) { window.niBind(r); });
</script>
