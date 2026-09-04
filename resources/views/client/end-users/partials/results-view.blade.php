{{--
    Read-only Negative Items & Results panel for the business owner (results-tracking
    owners only, e.g. Clinecea). Mirrors the VA's Results tab but with NO actions —
    the owner can view every item and its status, not change anything.
    Requires: $endUser (with negativeItems loaded).
--}}
@php
    $items     = $endUser->negativeItems->sortBy([['status', 'asc'], ['name', 'asc']]);
    $reporting = $items->where('status', 'reporting');
    $deleted   = $items->where('status', 'deleted');
    $updated   = $items->where('status', 'updated');
    $appStatus = $endUser->round_approval_status;
@endphp

<div class="card" id="results-panel">
    <div class="card-header">
        <h3 style="margin:0;">Negative Items &amp; Results</h3>
        <div class="rv-counts">
            <span class="rv-chip rv-chip-report">{{ $reporting->count() }} reporting</span>
            <span class="rv-chip rv-chip-del">{{ $deleted->count() }} deleted</span>
            <span class="rv-chip rv-chip-upd">{{ $updated->count() }} updated</span>
            @if ($endUser->isNearingCompletion())
                <span class="rv-chip rv-chip-near">⚑ Nearing completion</span>
            @endif
        </div>
    </div>

    {{-- Next-round approval status (read-only) --}}
    <div class="rv-approval">
        <strong>Next-round approval</strong>
        @if ($appStatus === 'awaiting')
            <span class="rv-chip rv-chip-wait">Awaiting your approval · Round {{ $endUser->round_approval_round }}</span>
        @elseif ($appStatus === 'approved')
            <span class="rv-chip rv-chip-ok">Approved · Round {{ $endUser->round_approval_round }}</span>
        @else
            <span class="muted" style="font-size:13px;">Not requested</span>
        @endif
    </div>

    @if ($items->isEmpty())
        <p class="muted" style="padding:6px 2px 2px;">No negative items recorded yet.</p>
    @else
        <div class="rv-list">
            @foreach ($items as $item)
                <div class="rv-item rv-item-{{ $item->status }}">
                    <span class="rv-item-name">
                        {{ $item->name }}@if ($item->detail)<span class="rv-item-detail">{{ $item->detailLabel() }} {{ $item->detail }}</span>@endif
                    </span>
                    <span class="rv-item-tags">
                        <span class="rv-tag">{{ $item->categoryLabel() }}</span>
                        @if ($item->category === 'negative_account')<span class="rv-tag rv-tag-goal">{{ $item->goalLabel() }}</span>@endif
                        <span class="rv-tag">{{ $item->bureauLabel() }}</span>
                        <span class="rv-status rv-status-{{ $item->status }}">{{ $item->statusLabel() }}@if ($item->resolved_at) · {{ $item->resolved_at->format('M j, Y') }}@endif</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>

@once
@push('head')
<style>
    #results-panel .rv-counts { display:flex; gap:6px; flex-wrap:wrap; }
    .rv-chip { font-size:12px; font-weight:700; padding:3px 9px; border-radius:999px; white-space:nowrap; }
    .rv-chip-report { background:#eff6ff; color:#1d4ed8; }
    .rv-chip-del    { background:#ecfdf5; color:#047857; }
    .rv-chip-upd    { background:#f5f3ff; color:#6d28d9; }
    .rv-chip-near   { background:#fff7ed; color:#c2410c; }
    .rv-chip-wait   { background:#fffbeb; color:#b45309; }
    .rv-chip-ok     { background:#ecfdf5; color:#047857; }
    .rv-approval { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 0; border-bottom:1px solid var(--border,#e6ebf2); margin-bottom:10px; }
    .rv-list { display:flex; flex-direction:column; }
    .rv-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 0; border-bottom:1px solid var(--border,#eef2f7); flex-wrap:wrap; }
    .rv-item:last-child { border-bottom:none; }
    .rv-item-del .rv-item-name, .rv-item-updated .rv-item-name { opacity:.7; }
    .rv-item-name { font-weight:600; color:var(--text,#0f172a); }
    .rv-item-detail { color:var(--muted,#64748b); font-weight:500; font-size:12.5px; margin-left:8px; }
    .rv-item-tags { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .rv-tag { font-size:11.5px; font-weight:600; padding:2px 8px; border-radius:6px; background:var(--surface-2,#f1f5f9); color:var(--muted,#475569); }
    .rv-tag-goal { background:#eef2ff; color:#4338ca; }
    .rv-status { font-size:11.5px; font-weight:700; padding:2px 9px; border-radius:999px; }
    .rv-status-reporting { background:#eff6ff; color:#1d4ed8; }
    .rv-status-deleted   { background:#ecfdf5; color:#047857; }
    .rv-status-updated   { background:#f5f3ff; color:#6d28d9; }
</style>
@endpush
@endonce
