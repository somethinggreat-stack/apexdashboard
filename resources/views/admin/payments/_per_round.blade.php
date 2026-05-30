@php
    // Build the invoice-ready text once, reuse in the modal textarea.
    $invoiceText = "INVOICE LIST — " . $client->business_name . "\n";
    $invoiceText .= "Date: " . now()->format('Y-m-d') . "\n\n";
    $invoiceText .= "UNPAID CLIENTS\n\n";
    $byRound = collect($data['unpaidItems'])->groupBy('round')->sortKeys();
    $itemNum = 0;
    foreach ($byRound as $rNum => $items) {
        $roundLabel = ['1st','2nd','3rd','4th','5th'][$rNum - 1] ?? "{$rNum}th";
        $invoiceText .= "{$roundLabel} Round Credit Repair (\${$data['rate']} per client)\n";
        foreach ($items as $it) {
            $itemNum++;
            $invoiceText .= sprintf("%d. %s\n", $itemNum, $it['name']);
        }
        $invoiceText .= sprintf("Subtotal %s Round: %d × \$%s = \$%s\n\n",
            $roundLabel, count($items), number_format($data['rate'], 2),
            number_format(count($items) * $data['rate'], 2));
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
        <div class="pay-stat-value" style="font-size:18px; padding-top:4px;">
            <button type="button" class="pay-btn-primary" onclick="openModal('invoiceListModal')" {{ count($data['unpaidItems']) === 0 ? 'disabled' : '' }}>
                Generate Invoice List
            </button>
        </div>
        <div class="pay-stat-sub">{{ count($data['unpaidItems']) }} unpaid item(s)</div>
    </div>
</div>

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
            <button type="submit" id="bulk-apply" disabled>Apply (${{ number_format($data['rate'], 2) }} each)</button>
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
                        <td class="pay-client">
                            <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                        </td>
                        @foreach ($row['cells'] as $r => $cell)
                            <td class="round-col">
                                @if ($cell['state'] === 'paid')
                                    <button type="button" class="chip chip-paid"
                                            title="Paid {{ optional($cell['payment']->paid_at)->format('M j, Y') }} · ${{ number_format($cell['payment']->amount, 2) }}"
                                            onclick="openPayEdit({{ $cell['payment']->id }}, {{ json_encode((float) $cell['payment']->amount) }}, '{{ optional($cell['payment']->paid_at)->toDateString() }}', '{{ addslashes($cell['payment']->method ?? '') }}', '{{ addslashes($cell['payment']->notes ?? '') }}')">
                                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ optional($cell['payment']->paid_at)->format('M j') }}
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.payments.store') }}" class="inline-pay-form">
                                        @csrf
                                        <input type="hidden" name="end_user_id" value="{{ $eu->id }}">
                                        <input type="hidden" name="round" value="{{ $r }}">
                                        <button type="submit" class="chip chip-unpaid" title="Click to mark Round {{ $r }} paid (${{ number_format($data['rate'], 2) }})">
                                            ${{ (int) $data['rate'] }}
                                        </button>
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
    Tip: Click any <strong>$</strong> chip to mark that round paid instantly. Click a green chip to edit or undo.
    Select multiple clients (checkboxes) to mark a batch paid for the same round.
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
                <div class="form-group"><label>Notes (optional)</label><input type="text" name="notes" id="pe-notes"></div>
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

    .inline-pay-form { display: inline; margin: 0; padding: 0; }

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
