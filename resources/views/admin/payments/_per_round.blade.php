@php
    // Build the invoice-ready text once, reuse in the modal textarea.
    $invoiceText = "INVOICE LIST — " . $client->business_name . "\n";
    $invoiceText .= "Date: " . now()->format('Y-m-d') . "\n\n";
    $invoiceText .= "UNPAID CLIENTS\n\n";
    $byRound = collect($data['unpaidItems'])->groupBy('round')->sortKeys();
    $itemNum = 0;
    foreach ($byRound as $rNum => $items) {
        $roundLabel = ['1st','2nd','3rd','4th','5th'][$rNum - 1] ?? "{$rNum}th";
        $invoiceText .= "{$roundLabel} Round Credit Repair\n";
        $subtotal = 0;
        foreach ($items as $it) {
            $itemNum++;
            $subtotal += $it['amount'];
            $invoiceText .= sprintf("%d. %s — \$%s\n", $itemNum, $it['name'], number_format($it['amount'], 2));
        }
        $invoiceText .= sprintf("Subtotal %s Round: %d client(s) = \$%s\n\n",
            $roundLabel, count($items), number_format($subtotal, 2));
    }
    $invoiceText .= "TOTAL UNPAID: \$" . number_format($data['totalUnpaid'], 2);
@endphp

<div class="pay-stats">
    <div class="pay-stat-card">
        <div class="pay-stat-label">Rate per Round</div>
        <div class="pay-stat-value">${{ number_format($data['rate'], 2) }}</div>
        <div class="pay-stat-sub">Per client per round</div>
    </div>
    <div class="pay-stat-card pay-stat-green">
        <div class="pay-stat-label">Paid This Month</div>
        <div class="pay-stat-value">${{ number_format($data['earnedThisMonth'], 2) }}</div>
        <div class="pay-stat-sub">All-time: ${{ number_format($data['earnedTotal'], 2) }}</div>
    </div>
    <div class="pay-stat-card pay-stat-orange">
        <div class="pay-stat-label">Total Unpaid</div>
        <div class="pay-stat-value">${{ number_format($data['totalUnpaid'], 2) }}</div>
        <div class="pay-stat-sub">
            @php $parts = []; @endphp
            @foreach ($data['unpaidByRound'] as $rn => $cnt)
                @if ($cnt > 0) @php $parts[] = "R{$rn}: {$cnt}"; @endphp @endif
            @endforeach
            {{ $parts ? implode(' · ', $parts) : 'All paid up' }}
        </div>
    </div>
    <div class="pay-stat-card">
        <div class="pay-stat-label">Invoice</div>
        <div class="pay-stat-value" style="font-size:14px; padding-top:4px; display:flex; gap:6px; flex-wrap:wrap;">
            <form method="POST" action="{{ route('admin.payments.invoice.generate') }}" target="_blank" style="margin:0;">
                @csrf
                <button type="submit" class="inv-gen-btn" {{ count($data['unpaidItems']) === 0 ? 'disabled' : '' }}
                        title="Creates an invoice for all currently-unpaid items and opens the printable PDF page">
                    Generate Invoice PDF
                </button>
            </form>
            <button type="button" class="inv-copy-btn" onclick="openModal('invoiceListModal')" {{ count($data['unpaidItems']) === 0 ? 'disabled' : '' }}
                    title="Copy a plain-text invoice list (for ChatGPT)">
                Copy List
            </button>
        </div>
        <div class="pay-stat-sub">{{ count($data['unpaidItems']) }} unpaid item(s)</div>
    </div>
</div>

@push('head')
<style>
    .inv-gen-btn {
        background: #0b2e5b; color: #fff; border: 0;
        font-size: 12px; font-weight: 700; letter-spacing: .3px;
        padding: 7px 12px; border-radius: 6px; cursor: pointer;
    }
    .inv-gen-btn:hover:not(:disabled) { background: #082246; }
    .inv-gen-btn:disabled { background: #cbd5e1; cursor: not-allowed; }
    .inv-copy-btn {
        background: #fff; color: #475569; border: 1px solid #cbd5e1;
        font-size: 12px; font-weight: 600;
        padding: 6px 10px; border-radius: 6px; cursor: pointer;
    }
    .inv-copy-btn:hover:not(:disabled) { background: #f1f5f9; }
    .inv-copy-btn:disabled { color: #cbd5e1; cursor: not-allowed; }
</style>
@endpush

{{-- ===== BULK ACTION TOOLBAR ===== --}}
<form method="POST" action="{{ route('admin.payments.bulk') }}" id="bulk-pay-form">
    @csrf
    <input type="hidden" name="round" id="bulk-round" value="1">

    <div class="bulk-bar">
        <label class="bulk-checkbox">
            <input type="checkbox" id="bulk-select-all">
            <span>Select All</span>
        </label>
        <span class="bulk-count" id="bulk-count">0 selected</span>
        <div class="bulk-action">
            <span>Mark selected as paid for</span>
            <select id="bulk-round-picker">
                @for ($r = 1; $r <= 5; $r++)
                    <option value="{{ $r }}">Round {{ $r }}</option>
                @endfor
            </select>
            <button type="submit" id="bulk-apply" disabled>Apply (each client's rate)</button>
        </div>
    </div>

    {{-- ===== MAIN TABLE ===== --}}
    <div class="pay-matrix">
        <table>
            <thead>
                <tr>
                    <th class="sel-col">&nbsp;</th>
                    <th>Client</th>
                    <th class="round-col">Round 1</th>
                    <th class="round-col">Round 2</th>
                    <th class="round-col">Round 3</th>
                    <th class="round-col">Round 4</th>
                    <th class="round-col">Round 5</th>
                    <th class="total-col">Paid</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    @php $eu = $row['end_user']; @endphp
                    <tr>
                        <td class="sel-col">
                            <input type="checkbox" name="end_user_ids[]" value="{{ $eu->id }}" class="row-check">
                        </td>
                        @php $euRate = $row['rate']; $euRateLabel = rtrim(rtrim(number_format($euRate, 2), '0'), '.'); @endphp
                        <td class="pay-client">
                            <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            <button type="button" class="rate-pill {{ $row['custom_fee'] ? 'rate-pill-custom' : '' }}"
                                    title="{{ $row['custom_fee'] ? 'Custom rate — click to change' : 'Default rate — click to set a custom rate' }}"
                                    onclick="openRateEdit({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', {{ json_encode($row['custom_fee'] ? (float) $euRate : null) }}, {{ json_encode((float) $data['rate']) }})">
                                ${{ $euRateLabel }}/rd
                                @if ($row['custom_fee'])<span class="rate-tag">custom</span>@endif
                            </button>
                        </td>
                        @foreach ($row['cells'] as $r => $cell)
                            @php $cellRate = $cell['rate']; $cellRateLabel = rtrim(rtrim(number_format($cellRate, 2), '0'), '.'); @endphp
                            <td class="round-col">
                                @if ($cell['state'] === 'paid')
                                    @php $isFreePaid = (bool) ($cell['payment']->is_free ?? false); @endphp
                                    <button type="button" class="chip {{ $isFreePaid ? 'chip-free-paid' : 'chip-paid' }}"
                                            title="{{ $isFreePaid ? 'Marked free/test on '.optional($cell['payment']->paid_at)->format('M j, Y').' — not billed, no commission' : 'Paid '.optional($cell['payment']->paid_at)->format('M j, Y').' · $'.number_format($cell['payment']->amount, 2) }}"
                                            onclick="openPayEdit({{ $cell['payment']->id }}, {{ json_encode((float) $cell['payment']->amount) }}, '{{ optional($cell['payment']->paid_at)->toDateString() }}', '{{ addslashes($cell['payment']->method ?? '') }}', '{{ addslashes($cell['payment']->notes ?? '') }}')">
                                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ $isFreePaid ? 'Free' : optional($cell['payment']->paid_at)->format('M j') }}
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.payments.store') }}" class="chip-stack">
                                        @csrf
                                        <input type="hidden" name="end_user_id" value="{{ $eu->id }}">
                                        <input type="hidden" name="round" value="{{ $r }}">
                                        <button type="submit" class="chip chip-unpaid {{ $cell['custom'] ? 'chip-custom' : '' }} {{ $cell['due'] ? 'chip-due' : '' }}"
                                                title="{{ $cell['due'] ? 'Round '.$r.' is done — click to mark it paid (${'.number_format($cellRate, 2).'})' : 'Click to mark Round '.$r.' paid (${'.number_format($cellRate, 2).'})' }}{{ $cell['custom'] ? ' — custom rate for this round' : '' }}">
                                            ${{ $cellRateLabel }}
                                        </button>
                                        <div class="chip-actions">
                                            <button type="submit" name="free" value="1" class="chip-mini chip-free"
                                                    title="Mark Round {{ $r }} done as free/test — $0, not billed, no commission">
                                                Free
                                            </button>
                                            <button type="button" class="chip-mini chip-edit" title="Edit Round {{ $r }} rate for {{ $eu->full_name }}"
                                                    onclick="openRoundRateEdit({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', {{ $r }}, {{ json_encode($cell['custom'] ? (float) $cellRate : null) }}, {{ json_encode((float) $data['rate']) }})">
                                                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </td>
                        @endforeach
                        <td class="total-col">${{ number_format($row['total_paid'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="pay-empty">No clients for this BO yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</form>

<p class="muted" style="margin: 14px 4px 0; font-size: 12px;">
    Tip: Click any <strong>$</strong> chip to mark that round paid instantly, or the little
    <strong>✎</strong> next to it to set a custom amount for just that round (e.g. $12 on Round 1, $20 after).
    Click a green chip to edit or undo. Select multiple clients (checkboxes) to mark a batch paid for the same round.
</p>

{{-- Invoice list modal — copy-paste into ChatGPT / invoice tool --}}
<div id="invoiceListModal" class="modal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h3>Invoice List — {{ $client->business_name }}</h3>
            <button class="modal-close" onclick="closeModal('invoiceListModal')">&times;</button>
        </div>
        <p class="muted" style="margin:0 0 10px; font-size:13px;">
            {{ count($data['unpaidItems']) }} unpaid item(s) totaling
            <strong>${{ number_format($data['totalUnpaid'], 2) }}</strong>.
            Copy the block below and paste into ChatGPT (or your invoice tool).
        </p>
        <textarea id="invoiceListText" readonly
                  style="width:100%; min-height:320px; padding:14px; font-family:Menlo,Consolas,monospace; font-size:12.5px; line-height:1.55; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; color:#0f172a; resize:vertical;">{{ $invoiceText }}</textarea>
        <div class="form-actions" style="margin-top:14px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceListModal')">Close</button>
            <button type="button" class="btn btn-primary" id="copyInvoiceBtn">Copy to Clipboard</button>
        </div>
    </div>
</div>

{{-- Edit/Undo modal (only for already-paid records) --}}
<div id="payEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Payment</h3>
            <button class="modal-close" onclick="closeModal('payEditModal')">&times;</button>
        </div>
        <form method="POST" id="payEditForm">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" name="amount" id="pe-amount" required></div>
                <div class="form-group"><label>Date Paid</label><input type="date" name="paid_at" id="pe-date" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Method (optional)</label><input type="text" name="method" id="pe-method" placeholder="Zelle / Bank / Cash"></div>
                <div class="form-group"><label>Comments (optional)</label><input type="text" name="notes" id="pe-notes"></div>
            </div>
            <div class="form-actions" style="display:flex; justify-content:space-between; gap:8px;">
                <button type="button" class="btn btn-danger" id="pe-delete">Undo (Mark Unpaid)</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('payEditModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
        <form method="POST" id="payDeleteForm" style="display:none;">
            @csrf @method('DELETE')
        </form>
    </div>
</div>

{{-- Custom per-client rate modal --}}
<div id="rateEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Per-Round Rate — <span id="rate-client-name"></span></h3>
            <button class="modal-close" onclick="closeModal('rateEditModal')">&times;</button>
        </div>
        <form method="POST" id="rateEditForm">
            @csrf @method('PUT')
            <p class="muted" style="font-size:13px; margin:0 0 12px;">
                Set a custom per-round fee for this client. Leave blank to use the
                business owner's default rate (<strong id="rate-default-label"></strong>).
            </p>
            <div class="form-group">
                <label>Custom Fee per Round ($)</label>
                <input type="number" step="0.01" min="0" max="100000" name="per_round_fee" id="rate-input" placeholder="Default">
            </div>
            <div class="form-actions" style="display:flex; justify-content:space-between; gap:8px; margin-top:14px;">
                <button type="button" class="btn btn-danger" id="rate-reset">Use Default</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rateEditModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rate</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Per-round custom rate modal (one round only) --}}
<div id="roundRateEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Round <span id="rr-round-label"></span> Rate — <span id="rr-client-name"></span></h3>
            <button class="modal-close" onclick="closeModal('roundRateEditModal')">&times;</button>
        </div>
        <form method="POST" id="roundRateForm">
            @csrf @method('PUT')
            <input type="hidden" name="round" id="rr-round-input">
            <p class="muted" style="font-size:13px; margin:0 0 12px;">
                Set the fee for <strong>this round only</strong>. Leave blank to use the
                default rate (<strong id="rr-default-label"></strong>).
            </p>
            <div class="form-group">
                <label>Fee for this Round ($)</label>
                <input type="number" step="0.01" min="0" max="100000" name="per_round_fee" id="rr-input" placeholder="Default">
            </div>
            <label style="display:flex; align-items:center; gap:8px; margin-top:10px; font-size:13px; color:#475569; cursor:pointer;">
                <input type="checkbox" name="apply_all" id="rr-apply-all" value="1">
                Apply this amount to <strong>all rounds</strong> for this client
            </label>
            <div class="form-actions" style="display:flex; justify-content:space-between; gap:8px; margin-top:14px;">
                <button type="button" class="btn btn-danger" id="rr-reset">Use Default</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('roundRateEditModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rate</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    /* Bulk action bar */
    .bulk-bar {
        display: flex; align-items: center; gap: 16px;
        background: linear-gradient(135deg, #f8fafc, #fff);
        border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 10px 16px; margin-bottom: 12px;
        flex-wrap: wrap;
    }
    .bulk-checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; }
    .bulk-count { font-size: 12px; color: #94a3b8; font-weight: 500; }
    .bulk-action { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
    .bulk-action > span { font-size: 12.5px; color: #475569; }
    .bulk-action select {
        font-size: 13px; padding: 6px 10px; border: 1px solid #cbd5e1;
        border-radius: 6px; background: #fff; font-weight: 500;
    }
    .bulk-action button {
        font-size: 13px; font-weight: 600; padding: 7px 14px;
        background: #2563eb; color: #fff; border: 0; border-radius: 6px; cursor: pointer;
    }
    .bulk-action button:disabled { background: #cbd5e1; cursor: not-allowed; }
    .bulk-action button:not(:disabled):hover { background: #1d4ed8; }

    /* Round chips — the actionable cells */
    .chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 12px; border-radius: 8px;
        font-size: 12.5px; font-weight: 700;
        border: 0; cursor: pointer;
        min-width: 62px; justify-content: center;
        transition: transform .1s, box-shadow .1s, background .1s;
    }
    .chip:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(15,23,42,.08); }

    .chip-paid {
        background: #d1fae5; color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .chip-paid:hover { background: #a7f3d0; }
    .chip-paid svg { color: #059669; }

    .chip-unpaid {
        background: #fff; color: #2563eb;
        border: 1.5px dashed #cbd5e1;
    }
    .chip-unpaid:hover { background: #eff6ff; border-color: #2563eb; border-style: solid; }

    /* Unpaid chip carrying a custom per-round rate (purple, like the rate pill) */
    .chip-unpaid.chip-custom {
        background: #f5f3ff; color: #5b21b6;
        border: 1.5px solid #ddd6fe;
    }
    .chip-unpaid.chip-custom:hover { background: #ede9fe; border-color: #8b5cf6; }

    /* "Due" chip — this round is done but not marked paid yet. Gently pulses
       amber to nudge the VA to collect for it. Turns plain green on click. */
    .chip-unpaid.chip-due {
        color: #b45309; border-style: solid; border-color: #fcd34d;
        animation: chipDuePulse 1.35s ease-in-out infinite;
    }
    .chip-unpaid.chip-due:hover {
        background: #fef3c7; border-color: #f59e0b;
        animation: none;
    }
    @keyframes chipDuePulse {
        0%, 100% { background: #fffbeb; border-color: #fcd34d; box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        50%      { background: #fef3c7; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .18); }
    }
    /* Respect users who prefer no motion — fall back to a static amber chip. */
    @media (prefers-reduced-motion: reduce) {
        .chip-unpaid.chip-due { animation: none; background: #fef3c7; border-color: #f59e0b; }
    }

    .inline-pay-form { display: inline; margin: 0; padding: 0; }

    /* Unpaid cell: big $ chip on top, small "Free" + edit-rate beneath it. */
    .chip-stack { display: inline-flex; flex-direction: column; gap: 4px; align-items: stretch; min-width: 62px; margin: 0; }
    .chip-stack .chip { width: 100%; min-width: 0; padding: 7px 8px; }
    .chip-actions { display: flex; gap: 4px; }
    .chip-mini {
        flex: 1; display: inline-flex; align-items: center; justify-content: center;
        padding: 4px 6px; border-radius: 7px; font-size: 11px; font-weight: 700; line-height: 1;
        cursor: pointer; transition: background .12s, color .12s, border-color .12s;
    }
    /* "Free / test" — mark a round done at $0 (no revenue, no commission) */
    .chip-free { background: #f1f5f9; color: #64748b; border: 1px solid #d7dee8; }
    .chip-free:hover { background: #e2e8f0; color: #475569; border-color: #94a3b8; }
    /* Edit this round's rate */
    .chip-edit { background: #fff; color: #a3aec0; border: 1px solid #e6ebf2; }
    .chip-edit:hover { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .chip-edit svg { width: 12px; height: 12px; }
    /* Stronger affordance when a custom rate is already set on this round */
    .chip-stack:has(.chip-custom) .chip-edit { color: #8b5cf6; border-color: #ddd6fe; }
    .chip-stack:has(.chip-custom) .chip-edit:hover { color: #6d28d9; }

    /* A round closed as free/test — slate instead of the green paid chip */
    .chip-free-paid { background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; }
    .chip-free-paid:hover { background: #e2e8f0; border-color: #94a3b8; }

    /* Per-client rate pill (next to client name) */
    .rate-pill {
        display: inline-flex; align-items: center; gap: 5px;
        margin-left: 8px; padding: 2px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 700; cursor: pointer;
        background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .rate-pill:hover { background: #e2e8f0; }
    .rate-pill-custom { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
    .rate-pill-custom:hover { background: #ddd6fe; }
    .rate-tag { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; opacity: .8; }

    /* Tighter cells */
    .pay-matrix .sel-col { width: 32px; text-align: center; }
    .pay-matrix .round-col { width: 92px; text-align: center; }
    .pay-matrix .total-col { width: 80px; text-align: right; font-weight: 600; color: #0f172a; }
    .pay-matrix tbody tr:hover { background: #f8fafc; }
    .row-check, #bulk-select-all { cursor: pointer; }

    @media (max-width: 900px) {
        .bulk-bar { gap: 10px; }
        .bulk-action { margin-left: 0; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.getElementById('bulk-pay-form');
    var selectAll = document.getElementById('bulk-select-all');
    var checks = form.querySelectorAll('.row-check');
    var count = document.getElementById('bulk-count');
    var apply = document.getElementById('bulk-apply');
    var roundPicker = document.getElementById('bulk-round-picker');
    var roundHidden = document.getElementById('bulk-round');

    function updateCount() {
        var n = 0;
        checks.forEach(function (c) { if (c.checked) n++; });
        count.textContent = n + ' selected';
        apply.disabled = (n === 0);
    }

    selectAll.addEventListener('change', function () {
        checks.forEach(function (c) { c.checked = selectAll.checked; });
        updateCount();
    });
    checks.forEach(function (c) {
        c.addEventListener('change', function () {
            if (!c.checked) selectAll.checked = false;
            updateCount();
        });
    });
    roundPicker.addEventListener('change', function () {
        roundHidden.value = roundPicker.value;
    });

    // Confirm bulk action
    form.addEventListener('submit', function (e) {
        if (apply.disabled) { e.preventDefault(); return; }
        var n = form.querySelectorAll('.row-check:checked').length;
        var round = roundPicker.value;
        if (!confirm('Mark ' + n + ' client(s) paid for Round ' + round + '?')) {
            e.preventDefault();
        }
    });

    updateCount();
})();

// Copy invoice list to clipboard
(function () {
    var btn = document.getElementById('copyInvoiceBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var ta = document.getElementById('invoiceListText');
        ta.select();
        ta.setSelectionRange(0, 99999);
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(ta.value).then(function () {
                    btn.textContent = 'Copied ✓';
                    setTimeout(function () { btn.textContent = 'Copy to Clipboard'; }, 1600);
                });
            } else {
                document.execCommand('copy');
                btn.textContent = 'Copied ✓';
                setTimeout(function () { btn.textContent = 'Copy to Clipboard'; }, 1600);
            }
        } catch (e) {
            alert('Could not copy — select the text manually and copy with Ctrl/Cmd+C.');
        }
    });
})();

window.openRateEdit = function (endUserId, name, customRate, defaultRate) {
    var form = document.getElementById('rateEditForm');
    form.action = "{{ url('admin/payments/client-rate') }}/" + endUserId;
    document.getElementById('rate-client-name').textContent = name;
    document.getElementById('rate-default-label').textContent = '$' + (defaultRate || 0).toFixed(2);
    document.getElementById('rate-input').value = (customRate === null || customRate === undefined) ? '' : customRate;
    document.getElementById('rate-reset').onclick = function () {
        document.getElementById('rate-input').value = '';
        form.submit();
    };
    openModal('rateEditModal');
};

window.openRoundRateEdit = function (endUserId, name, round, customRate, defaultRate) {
    var form = document.getElementById('roundRateForm');
    form.action = "{{ url('admin/payments/round-rate') }}/" + endUserId;
    document.getElementById('rr-round-input').value = round;
    document.getElementById('rr-round-label').textContent = round;
    document.getElementById('rr-client-name').textContent = name;
    document.getElementById('rr-default-label').textContent = '$' + (defaultRate || 0).toFixed(2);
    document.getElementById('rr-input').value = (customRate === null || customRate === undefined) ? '' : customRate;
    document.getElementById('rr-apply-all').checked = false;
    document.getElementById('rr-reset').onclick = function () {
        document.getElementById('rr-input').value = '';
        document.getElementById('rr-apply-all').checked = false;
        form.submit();
    };
    openModal('roundRateEditModal');
};

window.openPayEdit = function (paymentId, amount, paidAt, method, notes) {
    var base = "{{ url('admin/payments') }}/" + paymentId;
    document.getElementById('payEditForm').action = base;
    document.getElementById('payDeleteForm').action = base;
    document.getElementById('pe-amount').value = amount;
    document.getElementById('pe-date').value = paidAt;
    document.getElementById('pe-method').value = method || '';
    document.getElementById('pe-notes').value = notes || '';
    document.getElementById('pe-delete').onclick = function () {
        if (confirm('Mark this round unpaid?')) {
            document.getElementById('payDeleteForm').submit();
        }
    };
    openModal('payEditModal');
};
</script>
@endpush
