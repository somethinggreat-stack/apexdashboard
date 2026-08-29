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

<div data-payments-refresh>
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
        background: #0b2e5b; color: var(--on-accent, #fff); border: 0;
        font-size: 12px; font-weight: 700; letter-spacing: .3px;
        padding: 7px 12px; border-radius: 6px; cursor: pointer;
    }
    .inv-gen-btn:hover:not(:disabled) { background: #082246; }
    .inv-gen-btn:disabled { background: var(--muted); cursor: not-allowed; }
    .inv-copy-btn {
        background: var(--surface); color: var(--text-soft); border: 1px solid var(--muted);
        font-size: 12px; font-weight: 600;
        padding: 6px 10px; border-radius: 6px; cursor: pointer;
    }
    .inv-copy-btn:hover:not(:disabled) { background: var(--surface-2); }
    .inv-copy-btn:disabled { color: var(--muted); cursor: not-allowed; }
    .pay-all-btn {
        margin-left: auto; background: linear-gradient(135deg, #34d399, #059669);
        color: var(--on-accent, #fff); border: 0; font-size: 12.5px; font-weight: 800; letter-spacing: .2px;
        padding: 9px 16px; border-radius: 8px; cursor: pointer; white-space: nowrap;
        box-shadow: 0 6px 16px rgba(5, 150, 105, .28);
    }
    .pay-all-btn:hover:not(:disabled) { filter: brightness(1.05); }
    .pay-all-btn:disabled { background: var(--muted); box-shadow: none; cursor: not-allowed; }
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
                @for ($r = 1; $r <= count(\App\Models\EndUser::ROUND_OPTIONS); $r++)
                    <option value="{{ $r }}">Round {{ $r }}</option>
                @endfor
            </select>
            <button type="submit" id="bulk-apply" disabled>Apply (each client's rate)</button>
        </div>
        <button type="submit" form="pay-all-form" class="pay-all-btn"
                {{ count($data['unpaidItems']) === 0 ? 'disabled' : '' }}
                title="Mark every unpaid round for every client as paid, at each client's rate">
            ✓ Mark ALL unpaid paid
        </button>
    </div>
</form>

{{-- Standalone form (kept OUTSIDE #bulk-pay-form so forms never nest). The
     button above targets it via form="pay-all-form". data-confirm-action shows
     the centered green confirm before anything is charged. --}}
<form id="pay-all-form" method="POST" action="{{ route('admin.payments.pay-all') }}"
      data-confirm-action
      data-confirm-title="Mark all balances paid?"
      data-confirm-message="This marks every unpaid round for all clients of {{ $client->business_name }} as paid at each client's rate — ${{ number_format($data['totalUnpaid'], 2) }} across {{ count($data['unpaidItems']) }} item(s). Already-paid rounds are left as-is, and you can still undo any single round afterward."
      data-confirm-ok="Yes, mark all paid">
    @csrf
</form>

{{-- ===== MAIN TABLE ===== --}}
{{-- Table is intentionally OUTSIDE #bulk-pay-form so the per-chip mark-paid
     forms are not nested inside another form (which browsers drop, breaking the
     chips). Row checkboxes stay tied to the bulk form via the form="" attribute. --}}
<div class="pay-matrix">
        <table>
            <thead>
                <tr>
                    <th class="sel-col">&nbsp;</th>
                    <th>Client</th>
                    @for ($r = 1; $r <= count(\App\Models\EndUser::ROUND_OPTIONS); $r++)
                        <th class="round-col">R{{ $r }}</th>
                    @endfor
                    <th class="total-col">Paid</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    @php $eu = $row['end_user']; @endphp
                    <tr>
                        <td class="sel-col">
                            <input type="checkbox" name="end_user_ids[]" value="{{ $eu->id }}" class="row-check" form="bulk-pay-form">
                        </td>
                        @php $euRate = $row['rate']; $euRateLabel = rtrim(rtrim(number_format($euRate, 2), '0'), '.'); @endphp
                        <td class="pay-client">
                            <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            <button type="button" class="rate-pill {{ $row['custom_fee'] ? 'rate-pill-custom' : '' }}"
                                    title="{{ $row['custom_fee'] ? 'Custom rate — click to change' : 'Default rate — click to set a custom rate' }}"
                                    data-rate-edit
                                    data-eu="{{ $eu->id }}"
                                    data-name="{{ $eu->full_name }}"
                                    data-custom="{{ $row['custom_fee'] ? (float) $euRate : '' }}"
                                    data-default="{{ (float) $data['rate'] }}">
                                ${{ $euRateLabel }}/rd
                                @if ($row['custom_fee'])<span class="rate-tag">custom</span>@endif
                            </button>
                        </td>
                        @foreach ($row['cells'] as $r => $cell)
                            @php $cellRate = $cell['rate']; $cellRateLabel = rtrim(rtrim(number_format($cellRate, 2), '0'), '.'); @endphp
                            <td class="round-col">
                                @if ($cell['state'] === 'paid')
                                    @php $isFreePaid = (bool) ($cell['payment']->is_free ?? false); @endphp
                                    {{-- Paid: coloured square, click opens the existing edit/undo box. --}}
                                    <div class="paycell">
                                        <button type="button" class="paysq {{ $isFreePaid ? 'paysq-free' : 'paysq-paid' }}"
                                                title="{{ $isFreePaid ? 'Free / test — marked '.optional($cell['payment']->paid_at)->format('M j, Y').' · $0, not billed' : 'Paid '.optional($cell['payment']->paid_at)->format('M j, Y').' · $'.number_format($cell['payment']->amount, 2).' — click to edit / undo' }}"
                                                data-pay-edit
                                                data-id="{{ $cell['payment']->id }}"
                                                data-amount="{{ (float) $cell['payment']->amount }}"
                                                data-paid="{{ optional($cell['payment']->paid_at)->toDateString() }}"
                                                data-method="{{ $cell['payment']->method ?? '' }}"
                                                data-notes="{{ $cell['payment']->notes ?? '' }}">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        </button>
                                        <span class="payfree-label">{{ $isFreePaid ? 'Free' : optional($cell['payment']->paid_at)->format('M j') }}</span>
                                    </div>
                                @else
                                    {{-- Unpaid: click the square to mark paid; "Free" below marks it free. --}}
                                    <form method="POST" action="{{ route('admin.payments.store') }}" class="paycell">
                                        @csrf
                                        <input type="hidden" name="end_user_id" value="{{ $eu->id }}">
                                        <input type="hidden" name="round" value="{{ $r }}">
                                        <button type="submit" class="paysq paysq-open {{ $cell['custom'] ? 'paysq-custom' : '' }} {{ $cell['due'] ? 'paysq-due' : '' }}"
                                                title="{{ $cell['due'] ? 'Round '.$r.' is done — click to mark paid ($'.number_format($cellRate, 2).')' : 'Click to mark Round '.$r.' paid ($'.number_format($cellRate, 2).')' }}{{ $cell['custom'] ? ' — custom rate' : '' }}">
                                            ${{ $cellRateLabel }}
                                        </button>
                                        <button type="submit" name="free" value="1" class="payfree"
                                                title="Mark Round {{ $r }} free / test — $0, not billed, no commission">
                                            Free
                                        </button>
                                        <button type="button" class="paysq-edit" title="Edit Round {{ $r }} rate for {{ $eu->full_name }}"
                                                data-round-rate-edit
                                                data-eu="{{ $eu->id }}"
                                                data-name="{{ $eu->full_name }}"
                                                data-round="{{ $r }}"
                                                data-custom="{{ $cell['custom'] ? (float) $cellRate : '' }}"
                                                data-default="{{ (float) $data['rate'] }}">
                                            <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endforeach
                        <td class="total-col">${{ number_format($row['total_paid'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count(\App\Models\EndUser::ROUND_OPTIONS) + 3 }}" class="pay-empty">No clients for this BO yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

<p class="muted" style="margin: 14px 4px 0; font-size: 12px;">
    Tip: each square is one round (R1–R15). Click a <strong>$</strong> square to mark that round paid,
    or <strong>Free</strong> beneath it to close it free/test. Hover a square for its details; a green
    (paid) square opens the edit / undo box. The little <strong>✎</strong> (top-right on hover) sets a
    custom amount for just that round. Select clients (checkboxes) to mark a batch paid for the same round.
</p>
</div>{{-- /data-payments-refresh --}}

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
                  style="width:100%; min-height:320px; padding:14px; font-family:Menlo,Consolas,monospace; font-size:12.5px; line-height:1.55; border:1px solid var(--muted); border-radius:8px; background:var(--surface-2); color:var(--text); resize:vertical;">{{ $invoiceText }}</textarea>
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
            <label style="display:flex; align-items:center; gap:8px; margin-top:10px; font-size:13px; color:var(--text-soft); cursor:pointer;">
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
        background: linear-gradient(135deg, var(--surface-2), var(--surface));
        border: 1px solid var(--border); border-radius: 12px;
        padding: 10px 16px; margin-bottom: 12px;
        flex-wrap: wrap;
    }
    .bulk-checkbox { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--text-soft); cursor: pointer; }
    .bulk-count { font-size: 12px; color: var(--muted); font-weight: 500; }
    .bulk-action { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
    .bulk-action > span { font-size: 12.5px; color: var(--text-soft); }
    .bulk-action select {
        font-size: 13px; padding: 6px 10px; border: 1px solid var(--muted);
        border-radius: 6px; background: var(--surface); font-weight: 500;
    }
    .bulk-action button {
        font-size: 13px; font-weight: 600; padding: 7px 14px;
        background: #2563eb; color: var(--on-accent, #fff); border: 0; border-radius: 6px; cursor: pointer;
    }
    .bulk-action button:disabled { background: var(--muted); cursor: not-allowed; }
    .bulk-action button:not(:disabled):hover { background: #1d4ed8; }


    /* Per-client rate pill (next to client name) */
    .rate-pill {
        display: inline-flex; align-items: center; gap: 5px;
        margin-left: 8px; padding: 2px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 700; cursor: pointer;
        background: var(--surface-2); color: var(--text-soft); border: 1px solid var(--border);
        vertical-align: middle;
    }
    .rate-pill:hover { background: var(--border); }
    .rate-pill-custom { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
    .rate-pill-custom:hover { background: #ddd6fe; }
    .rate-tag { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; opacity: .8; }

    /* Tighter cells */
    .pay-matrix .sel-col { width: 32px; text-align: center; }
    .pay-matrix .round-col { width: 46px; text-align: center; padding-left: 2px; padding-right: 2px; }
    .pay-matrix thead .round-col { font-size: 11px; }
    .pay-matrix .total-col { width: 76px; text-align: right; font-weight: 600; color: var(--text); }
    .pay-matrix tbody tr:hover { background: var(--surface-2); }
    .row-check, #bulk-select-all { cursor: pointer; }

    /* ===== Compact 15-round cells: a status square + a "Free" line beneath.
       Colours carry the status; details are on hover; a click marks/undoes. ===== */
    .paycell { position: relative; display: inline-flex; flex-direction: column; align-items: center; gap: 3px; margin: 0; padding: 0; }
    .paysq {
        width: 36px; height: 36px; box-sizing: border-box;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 9px; border: 1.5px solid transparent; cursor: pointer;
        font-size: 11px; font-weight: 800; line-height: 1; padding: 0;
        transition: transform .1s, box-shadow .1s, background .12s, border-color .12s;
    }
    .paysq:hover { transform: translateY(-1px); box-shadow: 0 2px 7px rgba(15,23,42,.14); }

    /* Paid (billed) — solid green with a check. */
    .paysq-paid { background: #d1fae5; color: #059669; border-color: #a7f3d0; }
    .paysq-paid:hover { background: #a7f3d0; }
    /* Paid free / test — slate. */
    .paysq-free { background: var(--surface-2); color: var(--text-soft); border-color: var(--muted); }
    .paysq-free:hover { background: var(--border); }

    /* Unpaid, round NOT reached yet — faint dashed (available to pre-mark). */
    .paysq-open { background: var(--surface); color: #94a3b8; border: 1.5px dashed var(--border); }
    .paysq-open:hover { background: #eff6ff; color: #2563eb; border-color: #2563eb; border-style: solid; }
    /* Unpaid with a custom per-round rate — purple. */
    .paysq-open.paysq-custom { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; border-style: solid; }
    /* "Due" — round is done but not paid → amber, gently pulsing to nudge collection. */
    .paysq-open.paysq-due {
        background: #fffbeb; color: #b45309; border-color: #fcd34d; border-style: solid;
        animation: paysqDue 1.4s ease-in-out infinite;
    }
    .paysq-open.paysq-due:hover { background: #fef3c7; border-color: #f59e0b; animation: none; }
    @keyframes paysqDue {
        0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        50%     { box-shadow: 0 0 0 3px rgba(245,158,11,.18); }
    }
    @media (prefers-reduced-motion: reduce) { .paysq-open.paysq-due { animation: none; } }

    /* "Free" line beneath an unpaid square; the paid square shows its date instead. */
    .payfree {
        border: 0; background: none; cursor: pointer;
        font-size: 9.5px; font-weight: 700; line-height: 1; color: var(--muted);
        padding: 1px 4px; border-radius: 5px;
    }
    .payfree:hover { background: var(--surface-2); color: var(--text-soft); }
    .payfree-label { font-size: 9px; color: var(--muted); line-height: 1; }

    /* Per-round rate edit — a tiny pencil, top-right, on cell hover only. */
    .paysq-edit {
        position: absolute; top: -5px; right: -3px;
        width: 16px; height: 16px; border-radius: 50%;
        display: none; align-items: center; justify-content: center;
        background: var(--surface); color: #8b5cf6; border: 1px solid #ddd6fe; cursor: pointer;
    }
    .paycell:hover .paysq-edit { display: inline-flex; }
    .paysq-edit:hover { background: #f5f3ff; }

    /* Narrower screens: shrink the round cells so all 15 still fit with no
       horizontal scrollbar and nothing gets clipped. */
    @media (max-width: 1400px) {
        .pay-matrix .round-col { width: 40px; }
        .pay-matrix .paysq { width: 32px; height: 32px; font-size: 10px; }
    }
    @media (max-width: 1200px) {
        .pay-matrix .round-col { width: 34px; padding-left: 1px; padding-right: 1px; }
        .pay-matrix .paysq { width: 28px; height: 28px; font-size: 9px; border-radius: 7px; }
        .pay-matrix .payfree { font-size: 8.5px; }
    }

    /* Pin the checkbox + client name so they stay visible if the row ever has to
       scroll on a narrow screen — the round squares never cover the name. */
    .pay-matrix thead th.sel-col,
    .pay-matrix tbody td.sel-col { position: sticky; left: 0; z-index: 2; background: var(--surface); }
    .pay-matrix thead th.sel-col { background: var(--surface-2); z-index: 3; }
    .pay-matrix thead th:nth-child(2),
    .pay-matrix tbody td.pay-client { position: sticky; left: 32px; z-index: 2; background: var(--surface); }
    .pay-matrix thead th:nth-child(2) { background: var(--surface-2); z-index: 3; }
    .pay-matrix tbody tr:hover td.sel-col,
    .pay-matrix tbody tr:hover td.pay-client { background: var(--surface-2); }

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
    // Checkboxes live in the table (outside the form, tied via form="") so query
    // them from the document, not the form element.
    var checks = document.querySelectorAll('.row-check');
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
        e.preventDefault();
        var n = document.querySelectorAll('.row-check:checked').length;
        var round = roundPicker.value;
        window.apexConfirmAction({
            title: 'Mark ' + n + ' client(s) paid?',
            message: 'This marks the selected client(s) paid for Round ' + round + " at each client's rate.",
            okLabel: 'Mark Paid',
            onConfirm: function () { form.submit(); }
        });
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
        window.apexConfirmDelete({
            title: 'Mark this round unpaid?',
            message: 'This removes the payment for this round. You can mark it paid again anytime.',
            okLabel: 'Mark Unpaid',
            onConfirm: function () {
                var f = document.getElementById('payDeleteForm');
                var fd = new FormData(f);
                fetch(f.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function () { closeModal('payEditModal'); apexRefreshPayments(); })
                    .catch(function () { window.location.reload(); });
            }
        });
    };
    openModal('payEditModal');
};

/* Chip edit popups via event delegation + data attributes. Robust against
   client names / payment notes containing quotes or line breaks — an inline
   onclick would break on those and the chip would stop responding. */
document.addEventListener('click', function (e) {
    var b;
    if ((b = e.target.closest('[data-pay-edit]'))) {
        window.openPayEdit(b.dataset.id, parseFloat(b.dataset.amount), b.dataset.paid || '', b.dataset.method || '', b.dataset.notes || '');
    } else if ((b = e.target.closest('[data-rate-edit]'))) {
        window.openRateEdit(b.dataset.eu, b.dataset.name, b.dataset.custom === '' ? null : parseFloat(b.dataset.custom), parseFloat(b.dataset.default));
    } else if ((b = e.target.closest('[data-round-rate-edit]'))) {
        window.openRoundRateEdit(b.dataset.eu, b.dataset.name, b.dataset.round, b.dataset.custom === '' ? null : parseFloat(b.dataset.custom), parseFloat(b.dataset.default));
    }
});

/* ---- Snappy payment actions: mark paid / free / save update the page in place
   instead of a full reload (no flash, no jump to the top). The refresh swaps the
   whole payments region so the stat cards and totals stay correct too. ---- */
window.apexRefreshPayments = function () {
    fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            // Swap the stat cards (totals) and the matrix rows only — NOT the bulk
            // bar, whose checkbox/select-all/apply listeners are bound per-element
            // and would be lost. The row cells' forms and chip-edits are delegated,
            // so the fresh squares keep working.
            var ok = false;
            ['.pay-stats', '.pay-matrix tbody'].forEach(function (sel) {
                var nw = doc.querySelector(sel), cur = document.querySelector(sel);
                if (nw && cur) { cur.innerHTML = nw.innerHTML; ok = true; }
            });
            if (! ok) { window.location.reload(); }
        })
        .catch(function () { window.location.reload(); });
};

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches) return;
    var isPaycell = form.matches('form.paycell');   // mark paid / free (square + Free)
    var isPayEdit = form.id === 'payEditForm';        // save changes to a payment
    if (!isPaycell && !isPayEdit) return;
    e.preventDefault();
    var fd = new FormData(form);
    if (e.submitter && e.submitter.name) { fd.append(e.submitter.name, e.submitter.value); }
    fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function () {
            if (isPayEdit) { closeModal('payEditModal'); }
            apexRefreshPayments();
        })
        .catch(function () { window.location.reload(); });
});
</script>
@endpush
