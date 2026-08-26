{{--
    Negative-items + round-approval panel — shown only for owners with results
    tracking on. Requires: $endUser (with negativeItems loaded or lazy).
--}}
@php
    $items      = $endUser->negativeItems->sortBy([['status', 'asc'], ['name', 'asc']]);
    $reporting  = $items->where('status', 'reporting');
    $deleted    = $items->where('status', 'deleted');
    $updated    = $items->where('status', 'updated');
    $nextRound  = min(8, $endUser->current_round + 1);
    $appStatus  = $endUser->round_approval_status;
    $approvalMsg = "Hi, {$endUser->full_name} is due for their next round (Round {$nextRound}). Can we proceed?";
@endphp

<div class="card" id="results-panel">
    <div class="card-header">
        <h3>Negative Items &amp; Results</h3>
        <div class="ni-counts">
            <span class="ni-chip ni-chip-report">{{ $reporting->count() }} reporting</span>
            <span class="ni-chip ni-chip-del">{{ $deleted->count() }} deleted</span>
            <span class="ni-chip ni-chip-upd">{{ $updated->count() }} updated</span>
            @if ($endUser->isNearingCompletion())
                <span class="ni-chip ni-chip-near">⚑ Nearing completion</span>
            @endif
        </div>
    </div>

    {{-- Round approval (SOP §2) --}}
    <div class="ni-approval">
        <div class="ni-approval-head">
            <strong>Next-round approval</strong>
            @if ($appStatus === 'awaiting')
                <span class="ni-chip ni-chip-wait">Awaiting approval · Round {{ $endUser->round_approval_round }}</span>
            @elseif ($appStatus === 'approved')
                <span class="ni-chip ni-chip-ok">Approved · Round {{ $endUser->round_approval_round }}</span>
            @else
                <span class="muted small">Not requested</span>
            @endif
        </div>
        <div class="ni-approval-body">
            <div class="ni-copywrap">
                <input type="text" class="ni-copy-input" id="apprMsg" value="{{ $approvalMsg }}" readonly>
                <button type="button" class="btn btn-sm" onclick="niCopy('apprMsg')">📋 Copy</button>
            </div>
            <div class="ni-approval-actions">
                @if ($appStatus !== 'awaiting')
                    <form method="POST" action="{{ route('admin.end-users.request-approval', $endUser->id) }}">@csrf
                        <button class="btn btn-sm btn-primary">Mark awaiting approval (R{{ $nextRound }})</button>
                    </form>
                @endif
                @if ($appStatus === 'awaiting')
                    <form method="POST" action="{{ route('admin.end-users.approve-round', $endUser->id) }}">@csrf
                        <button class="btn btn-sm btn-primary">Clinecea approved ✓</button>
                    </form>
                @endif
                @if ($appStatus)
                    <form method="POST" action="{{ route('admin.end-users.clear-approval', $endUser->id) }}">@csrf
                        <button class="btn btn-sm">Clear</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Items list --}}
    @if ($items->isEmpty())
        <p class="muted" style="padding:6px 2px 12px;">No negative items entered yet. Add them below.</p>
    @else
        <div class="ni-list">
            @foreach ($items as $item)
                <div class="ni-item ni-item-{{ $item->status }}">
                    <div class="ni-item-main">
                        <span class="ni-item-name">{{ $item->name }}@if ($item->detail)<span class="ni-item-detail">{{ $item->detailLabel() }} {{ $item->detail }}</span>@endif</span>
                        <span class="ni-item-tags">
                            <span class="ni-tag">{{ $item->categoryLabel() }}</span>
                            @if ($item->category === 'negative_account')<span class="ni-tag ni-tag-goal">{{ $item->goalLabel() }}</span>@endif
                            <span class="ni-tag">{{ $item->bureauLabel() }}</span>
                            <span class="ni-status ni-status-{{ $item->status }}">{{ $item->statusLabel() }}@if ($item->resolved_at) · {{ $item->resolved_at->format('M j, Y') }}@endif</span>
                        </span>
                    </div>
                    <div class="ni-item-actions">
                        @if ($item->isReporting())
                            <form method="POST" action="{{ route('admin.negative-items.resolve', $item->id) }}" class="ni-resolve">@csrf
                                <input type="date" name="resolved_on" value="{{ now()->toDateString() }}">
                                <button class="btn btn-sm btn-primary">Mark {{ $item->goal === 'update' ? 'Updated' : 'Deleted' }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.negative-items.reopen', $item->id) }}">@csrf
                                <button class="btn btn-sm">Reopen</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.negative-items.destroy', $item->id) }}"
                              onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add item — pick the type first, then the fields adapt --}}
    <form method="POST" action="{{ route('admin.negative-items.store') }}" class="ni-add" id="niAddForm">@csrf
        <input type="hidden" name="end_user_id" value="{{ $endUser->id }}">
        <select name="category" data-ni="category">@foreach (\App\Models\NegativeItem::CATEGORIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <input type="text" name="name" data-ni="name" maxlength="255" required>
        <input type="text" name="detail" data-ni="detail" maxlength="255">
        <select name="goal" data-ni="goal">@foreach (\App\Models\NegativeItem::GOALS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <select name="bureau" data-ni="bureau" required>
            @foreach (\App\Models\NegativeItem::BUREAUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-primary">+ Add</button>
    </form>
</div>

@once
<style>
    #results-panel .ni-counts { display:flex; gap:6px; flex-wrap:wrap; }
    .ni-chip { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .ni-chip-report { background:#e0f2fe; color:#075985; }
    .ni-chip-del { background:#dcfce7; color:#166534; }
    .ni-chip-upd { background:#ede9fe; color:#5b21b6; }
    .ni-chip-near { background:#fef3c7; color:#92400e; }
    .ni-chip-wait { background:#fef3c7; color:#92400e; }
    .ni-chip-ok { background:#dcfce7; color:#166534; }
    .ni-approval { border:1px solid var(--pro-line,var(--border)); border-radius:12px; padding:12px 14px; margin:4px 0 16px; background:rgba(148,163,184,.06); }
    .ni-approval-head { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .ni-approval-body { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; }
    .ni-copywrap { display:flex; gap:6px; align-items:center; flex:1 1 320px; }
    .ni-copy-input { flex:1; padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:12.5px; background:var(--surface); color:var(--text); }
    .ni-approval-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .ni-approval-actions form { display:inline; margin:0; }
    .ni-list { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
    .ni-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid var(--pro-line,var(--border)); border-radius:10px; flex-wrap:wrap; }
    .ni-item-del, .ni-item-updated { background:rgba(34,197,94,.06); }
    .ni-item-main { display:flex; flex-direction:column; gap:4px; min-width:200px; }
    .ni-item-name { font-weight:700; }
    .ni-item-tags { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    .ni-tag { font-size:11px; padding:2px 8px; border-radius:6px; background:var(--surface-2); color:var(--text-soft); }
    .ni-tag-goal { background:#e0e7ff; color:#3730a3; }
    .ni-status { font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
    .ni-status-reporting { background:#e0f2fe; color:#075985; }
    .ni-status-deleted { background:#dcfce7; color:#166534; }
    .ni-status-updated { background:#ede9fe; color:#5b21b6; }
    .ni-item-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .ni-item-actions form, .ni-resolve { display:inline-flex; gap:6px; align-items:center; margin:0; }
    .ni-resolve input[type=date] { padding:6px 8px; border:1px solid var(--border); border-radius:8px; font-size:12px; }
    .ni-add { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding-top:10px; border-top:1px dashed var(--pro-line,var(--border)); }
    .ni-add [data-ni="category"] { flex:0 0 160px; }
    .ni-add [data-ni="name"] { flex:1 1 200px; min-width:160px; }
    .ni-add [data-ni="detail"] { flex:0 0 170px; }
    .ni-add [data-ni="goal"] { flex:0 0 150px; }
    .ni-add [data-ni="bureau"] { flex:0 0 140px; }
    .ni-add input, .ni-add select { padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--surface); color:var(--text); }
    .ni-item-detail { margin-left:8px; font-size:12px; color:var(--pro-muted,var(--muted)); font-weight:600; }
    :root[data-theme="dark"] .ni-copy-input,
    :root[data-theme="dark"] .ni-resolve input,
    :root[data-theme="dark"] .ni-add input,
    :root[data-theme="dark"] .ni-add select { background:#10152a; border-color:var(--pro-line); color:var(--pro-text); }
    :root[data-theme="dark"] .ni-tag { background:rgba(148,163,184,.16); color:var(--pro-text-soft); }
</style>
<script>
    window.niCopy = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.select();
        try { navigator.clipboard.writeText(el.value); } catch (e) { document.execCommand('copy'); }
    };
</script>
@include('admin.partials.negative-item-script')
<script>
    (function () {
        var form = document.getElementById('niAddForm');
        if (form && window.niBind) window.niBind(form);
    })();
</script>
@endonce
