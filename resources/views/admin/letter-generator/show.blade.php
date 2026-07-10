@extends('layouts.admin-pro')

@section('title', $report->consumer_name ?: 'Report Review')
@section('subtitle', 'Flagged items are pre-selected. Adjust, add a reason, and save.')

@php
    $catLabel = [
        'collection' => 'Collection', 'charge_off' => 'Charge-off', 'closed_late' => 'Closed — Late',
        'open_late' => 'Open — Late', 'open' => 'Open', 'closed_positive' => 'Closed — Positive',
        'bankruptcy' => 'Bankruptcy', 'unknown' => 'Unknown',
    ];
    $bureauShort = ['TransUnion' => 'TU', 'Experian' => 'EXP', 'Equifax' => 'EQF'];
@endphp

@section('topbar-action')
    <a href="{{ route('admin.letter-generator.index') }}" class="pro-act view" style="padding:11px 18px;">← All Reports</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.letter-generator.save', $report) }}">
    @csrf

    <div class="lg-summary">
        <div>
            <h2>{{ $report->consumer_name ?: 'Unnamed consumer' }}</h2>
            <p class="muted">
                {{ $report->report_date?->format('M j, Y') ?: 'Report date unknown' }} ·
                {{ $accounts->count() }} accounts · {{ $inquiries->count() }} inquiries ·
                <strong style="color:#b91c1c;">{{ $report->negative_count }} flagged</strong>
            </p>
        </div>
        <div class="lg-summary-actions">
            <button type="button" class="pro-act warn" onclick="lgSelectFlagged()">Select all flagged</button>
            <button type="submit" class="pro-cta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Save Selections</span></button>
        </div>
    </div>

    {{-- =================== ACCOUNTS =================== --}}
    <section class="pro-panel lg-section">
        <div class="lg-section-head"><h3>Accounts <span class="lg-count">{{ $accounts->count() }}</span></h3></div>
        @forelse ($accounts as $item)
            @php $d = $item->detail ?? []; @endphp
            <div class="lg-item {{ $item->is_negative ? 'is-neg' : '' }} {{ $item->selected ? 'is-open' : '' }}" data-flagged="{{ $item->is_negative ? 1 : 0 }}">
                <label class="lg-row">
                    <input type="checkbox" name="selected[{{ $item->id }}]" value="1" @checked($item->selected) onchange="lgToggle(this)">
                    <span class="lg-body">
                        <span class="lg-title">
                            {{ $item->creditor_name ?: 'Unknown creditor' }}
                            @if ($item->account_number)<span class="lg-acct">#{{ $item->account_number }}</span>@endif
                        </span>
                        <span class="lg-bureaus">
                            @foreach (($d['bureaus'] ?? []) as $bureau => $f)
                                @php $cls = $d['per_bureau'][$bureau] ?? 'unknown'; @endphp
                                <span class="lg-bchip cat-{{ $cls }}" title="{{ ($f['account_status'] ?? '') . ' · ' . ($f['payment_status'] ?? '') }}">
                                    {{ $bureauShort[$bureau] ?? $bureau }}: {{ $catLabel[$cls] ?? $cls }}
                                </span>
                            @endforeach
                        </span>
                    </span>
                    @if ($item->is_negative)
                        <span class="lg-flag" title="{{ $item->auto_reason }}">● {{ $catLabel[$item->category] ?? 'Negative' }}</span>
                    @endif
                </label>
                <div class="lg-fields">
                    <label class="lg-field"><span>Instruction</span><textarea name="instruction[{{ $item->id }}]" rows="2" placeholder="What to do with this account…">{{ $item->dispute_instruction }}</textarea></label>
                    <label class="lg-field"><span>Reason</span><textarea name="reason[{{ $item->id }}]" rows="2" placeholder="Why it's being disputed…">{{ $item->dispute_reason }}</textarea></label>
                </div>
            </div>
        @empty
            <div class="empty">No accounts found in this report.</div>
        @endforelse
    </section>

    {{-- =================== INQUIRIES =================== --}}
    <section class="pro-panel lg-section">
        <div class="lg-section-head"><h3>Inquiries <span class="lg-count">{{ $inquiries->count() }}</span></h3></div>
        @forelse ($inquiries as $item)
            @php $d = $item->detail ?? []; @endphp
            <div class="lg-item {{ $item->is_negative ? 'is-neg' : '' }} {{ $item->selected ? 'is-open' : '' }}" data-flagged="{{ $item->is_negative ? 1 : 0 }}">
                <label class="lg-row">
                    <input type="checkbox" name="selected[{{ $item->id }}]" value="1" @checked($item->selected) onchange="lgToggle(this)">
                    <span class="lg-body">
                        <span class="lg-title">{{ $item->creditor_name ?: 'Unknown' }}</span>
                        <span class="lg-bureaus">
                            <span class="lg-bchip">{{ $d['bureau'] ?? '—' }}</span>
                            <span class="lg-bchip">{{ $d['date'] ?? '—' }}</span>
                            @if (!empty($d['business']))<span class="lg-bchip">{{ $d['business'] }}</span>@endif
                        </span>
                    </span>
                    @if ($item->is_negative)<span class="lg-flag" title="{{ $item->auto_reason }}">● Disputable</span>@endif
                </label>
                <div class="lg-fields">
                    <label class="lg-field"><span>Instruction</span><textarea name="instruction[{{ $item->id }}]" rows="2" placeholder="What to do with this inquiry…">{{ $item->dispute_instruction }}</textarea></label>
                    <label class="lg-field"><span>Reason</span><textarea name="reason[{{ $item->id }}]" rows="2" placeholder="Why it's being disputed…">{{ $item->dispute_reason }}</textarea></label>
                </div>
            </div>
        @empty
            <div class="empty">No inquiries found.</div>
        @endforelse
    </section>

    {{-- =================== PUBLIC RECORDS =================== --}}
    @if ($records->isNotEmpty())
    <section class="pro-panel lg-section">
        <div class="lg-section-head"><h3>Public Records <span class="lg-count">{{ $records->count() }}</span></h3></div>
        @foreach ($records as $item)
            <div class="lg-item {{ $item->is_negative ? 'is-neg' : '' }} {{ $item->selected ? 'is-open' : '' }}" data-flagged="{{ $item->is_negative ? 1 : 0 }}">
                <label class="lg-row">
                    <input type="checkbox" name="selected[{{ $item->id }}]" value="1" @checked($item->selected) onchange="lgToggle(this)">
                    <span class="lg-body"><span class="lg-title">{{ $item->creditor_name }}</span></span>
                    @if ($item->is_negative)<span class="lg-flag" title="{{ $item->auto_reason }}">● Negative</span>@endif
                </label>
                <div class="lg-fields">
                    <label class="lg-field"><span>Instruction</span><textarea name="instruction[{{ $item->id }}]" rows="2" placeholder="Bankruptcy chapter, case/reference number…">{{ $item->dispute_instruction }}</textarea></label>
                    <label class="lg-field"><span>Reason</span><textarea name="reason[{{ $item->id }}]" rows="2">{{ $item->dispute_reason }}</textarea></label>
                </div>
            </div>
        @endforeach
    </section>
    @endif

    {{-- =================== PERSONAL INFORMATION =================== --}}
    <section class="pro-panel lg-section">
        <div class="lg-section-head"><h3>Personal Information — Addresses <span class="lg-count">{{ $addresses->count() }}</span></h3></div>
        @forelse ($addresses as $item)
            <div class="lg-item {{ $item->selected ? 'is-open' : '' }}" data-flagged="0">
                <label class="lg-row">
                    <input type="checkbox" name="selected[{{ $item->id }}]" value="1" @checked($item->selected) onchange="lgToggle(this)">
                    <span class="lg-body"><span class="lg-title">{{ $item->creditor_name }}</span></span>
                </label>
                <div class="lg-fields lg-fields-1">
                    <label class="lg-field"><span>Reason</span><textarea name="reason[{{ $item->id }}]" rows="2" placeholder="Why this address is being disputed…">{{ $item->dispute_reason }}</textarea></label>
                </div>
            </div>
        @empty
            <div class="empty">No addresses found.</div>
        @endforelse
    </section>

    <div class="lg-savebar">
        <button type="submit" class="pro-cta"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Save Selections</span></button>
    </div>
</form>

@push('head')
<style>
    .lg-summary { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
    .lg-summary h2 { margin:0; font-size:22px; font-weight:800; color:var(--pro-text); }
    .lg-summary .muted { margin:4px 0 0; font-size:13px; }
    .lg-summary-actions { display:flex; gap:10px; align-items:center; }

    .lg-section { padding:6px 8px 10px; margin-bottom:18px; }
    .lg-section-head { padding:14px 16px 8px; }
    .lg-section-head h3 { margin:0; font-size:16px; font-weight:800; color:var(--pro-text); display:flex; align-items:center; gap:9px; }
    .lg-count { font-size:12px; font-weight:700; color:#4f46e5; background:#eef2ff; border-radius:999px; padding:2px 9px; }

    .lg-item { border-radius:12px; border:1px solid var(--pro-line); margin:8px; overflow:hidden; transition:border-color .15s, box-shadow .15s; }
    .lg-item.is-neg { border-color:#fecaca; background:#fff7f7; }
    .lg-item.is-open { box-shadow:0 4px 14px rgba(15,23,42,.06); }
    .lg-row { display:flex; align-items:center; gap:13px; padding:13px 15px; cursor:pointer; }
    .lg-row input[type=checkbox] { width:19px; height:19px; flex:none; cursor:pointer; accent-color:#4f46e5; }
    .lg-body { flex:1; min-width:0; display:flex; flex-direction:column; gap:5px; }
    .lg-title { font-weight:700; font-size:14px; color:var(--pro-text); }
    .lg-acct { font-weight:500; font-size:12px; color:var(--pro-muted); margin-left:6px; font-family:Menlo,Consolas,monospace; }
    .lg-bureaus { display:flex; flex-wrap:wrap; gap:6px; }
    .lg-bchip { font-size:11px; font-weight:600; padding:2px 9px; border-radius:6px; background:var(--pro-line-soft); color:var(--pro-text-soft); white-space:nowrap; }
    .lg-bchip.cat-collection, .lg-bchip.cat-charge_off, .lg-bchip.cat-closed_late, .lg-bchip.cat-open_late { background:#fee2e2; color:#b91c1c; }
    .lg-bchip.cat-open, .lg-bchip.cat-closed_positive { background:#ecfdf5; color:#047857; }
    .lg-flag { flex:none; font-size:11px; font-weight:700; color:#b91c1c; background:#fee2e2; border-radius:999px; padding:4px 11px; white-space:nowrap; }

    .lg-fields { display:none; grid-template-columns:1fr 1fr; gap:12px; padding:0 15px 15px 47px; }
    .lg-fields.lg-fields-1 { grid-template-columns:1fr; }
    .lg-item.is-open .lg-fields { display:grid; }
    @media (max-width:720px){ .lg-fields { grid-template-columns:1fr; } }
    .lg-field { display:flex; flex-direction:column; gap:4px; }
    .lg-field span { font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--pro-muted); }
    .lg-field textarea { padding:9px 11px; border:1px solid var(--pro-line); border-radius:9px; font:inherit; font-size:13px; resize:vertical; color:var(--pro-text); background:var(--pro-card); }
    .lg-field textarea:focus { outline:none; border-color:var(--pro-indigo); }

    .lg-savebar { display:flex; justify-content:flex-end; padding:4px 0 20px; }
    .empty { text-align:center; padding:20px; color:var(--pro-muted); font-size:13px; }
</style>
@endpush

@push('scripts')
<script>
function lgToggle(cb) {
    cb.closest('.lg-item').classList.toggle('is-open', cb.checked);
}
function lgSelectFlagged() {
    document.querySelectorAll('.lg-item[data-flagged="1"]').forEach(function (el) {
        var cb = el.querySelector('input[type=checkbox]');
        if (cb && !cb.checked) { cb.checked = true; lgToggle(cb); }
    });
}
</script>
@endpush
@endsection
