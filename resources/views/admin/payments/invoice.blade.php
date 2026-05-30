<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }} — {{ $invoice->client->business_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ===== Design tokens (mirrored from public/css/admin.css — Banker Navy) ===== */
        :root {
            --accent:        #142646;
            --accent-700:    #142646;
            --accent-800:    #0d1c36;
            --accent-soft:   #eef2f8;
            --ink:           #0f172a;
            --ink-2:         #1e293b;
            --muted:         #64748b;
            --border:        #e5e7eb;
            --border-2:      #cbd5e1;
            --surface:       #ffffff;
            --bg:            #f8fafc;
            --radius-sm:     6px;
            --radius-md:     8px;
            --radius-lg:     12px;
            --space-1:       4px;
            --space-2:       8px;
            --space-3:       12px;
            --space-4:       16px;
            --space-5:       20px;
            --space-6:       24px;
            --space-7:       28px;
            --space-8:       32px;
            --text-xs:       11px;
            --text-sm:       12px;
            --text-md:       13px;
            --text-lg:       15px;
            --text-xl:       17px;
            --text-2xl:      28px;
            --shadow-xs:     0 1px 1px rgba(15,23,42,.03), 0 1px 2px rgba(15,23,42,.04);
            --shadow-sm:     0 2px 4px rgba(15,23,42,.04), 0 4px 8px rgba(15,23,42,.04);
            --font-sans:     "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            --font-num:      "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }

        @page { size: letter; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: var(--space-6);
            font-family: var(--font-sans);
            font-feature-settings: "cv11", "ss01", "ss03";
            -webkit-font-smoothing: antialiased;
            background: var(--bg);
            color: var(--ink);
            font-size: var(--text-md);
            line-height: 1.5;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            max-width: 760px;
            margin: 0 auto var(--space-4);
            display: flex;
            gap: var(--space-2);
            justify-content: flex-end;
        }
        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            height: 36px;
            padding: 0 var(--space-4);
            font-family: var(--font-sans);
            font-size: var(--text-md);
            font-weight: 600;
            line-height: 1;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface);
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .toolbar a.secondary {
            background: var(--surface);
            color: var(--ink-2);
            border-color: var(--border);
        }
        .toolbar a.secondary:hover {
            background: var(--accent-soft);
            color: var(--accent-700);
            border-color: var(--accent-soft);
        }
        .toolbar button.primary {
            background: var(--accent-700);
            color: #fff;
            border-color: var(--accent-700);
        }
        .toolbar button.primary:hover {
            background: var(--accent-800);
            border-color: var(--accent-800);
        }

        /* ===== INVOICE CARD ===== */
        .invoice {
            max-width: 760px;
            margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 44px 44px var(--space-7);
            box-shadow: var(--shadow-xs);
        }

        /* ===== HEADER ===== */
        .inv-header {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            padding-bottom: var(--space-5);
        }
        .inv-logo { flex: 0 0 220px; }
        .inv-logo img { max-width: 100%; height: auto; display: block; }
        .inv-divider {
            width: 1px;
            background: var(--border);
            align-self: stretch;
        }
        .inv-meta {
            flex: 1;
            padding-left: var(--space-2);
        }
        .inv-meta-title {
            font-size: var(--text-2xl);
            font-weight: 600;
            color: var(--accent-700);
            letter-spacing: -0.02em;
            margin-bottom: var(--space-4);
        }
        .inv-meta-row {
            display: flex;
            gap: var(--space-4);
            font-size: var(--text-md);
            margin-bottom: var(--space-1);
            font-variant-numeric: tabular-nums;
        }
        .inv-meta-row strong {
            color: var(--muted);
            font-weight: 500;
            min-width: 96px;
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding-top: 1px;
        }
        .inv-meta-row span {
            color: var(--ink);
            font-weight: 500;
            font-family: var(--font-num);
        }

        /* ===== BILLED TO ===== */
        .inv-billed {
            padding: var(--space-4) 0 var(--space-6);
        }
        .inv-billed-label {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: var(--space-2);
        }
        .inv-billed-name {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.01em;
        }
        .inv-billed-sub {
            font-size: var(--text-md);
            color: var(--muted);
            font-style: italic;
            margin-top: 2px;
        }

        /* ===== TABLE ===== */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inv-table thead th {
            background: var(--accent-soft);
            color: var(--accent-700);
            font-size: var(--text-sm);
            font-weight: 600;
            letter-spacing: 0.04em;
            text-align: left;
            padding: var(--space-3) var(--space-4);
            border-bottom: 1px solid var(--border);
        }
        .inv-table thead th.right { text-align: right; }
        .inv-table thead th.center { text-align: center; }
        .inv-table tbody td {
            border-bottom: 1px solid var(--border);
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-md);
            vertical-align: top;
            color: var(--ink);
        }
        .inv-table tbody td.right {
            text-align: right;
            font-family: var(--font-num);
            font-variant-numeric: tabular-nums;
            font-weight: 500;
        }
        .inv-table tbody td.center {
            text-align: center;
            font-variant-numeric: tabular-nums;
        }
        .inv-desc-head {
            font-weight: 600;
            color: var(--accent-700);
            font-size: var(--text-md);
            padding: var(--space-4) var(--space-4) var(--space-2) !important;
            border-bottom: 0 !important;
            background: transparent;
        }
        .inv-line-num {
            color: var(--muted);
            width: 30px;
            font-variant-numeric: tabular-nums;
        }

        /* ===== TOTALS ===== */
        .inv-totals {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .inv-totals td {
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-md);
        }
        .inv-totals td.label {
            text-align: right;
            font-weight: 500;
            color: var(--ink-2);
        }
        .inv-totals td.amount {
            text-align: right;
            width: 140px;
            font-weight: 600;
            font-family: var(--font-num);
            font-variant-numeric: tabular-nums;
            color: var(--ink);
        }
        .inv-totals tr.subtotal td {
            border-top: 1px solid var(--border-2);
        }
        .inv-totals tr.total td {
            background: var(--accent);
            color: #fff;
            font-size: var(--text-lg);
            font-weight: 600;
            letter-spacing: -0.01em;
            padding: var(--space-4);
        }
        .inv-totals tr.total td.label {
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: var(--text-sm);
            font-weight: 600;
        }
        .inv-totals tr.total td.amount {
            font-size: var(--text-xl);
            color: #fff;
            font-family: var(--font-num);
            font-variant-numeric: tabular-nums;
        }

        /* ===== FOOTER ===== */
        .inv-footer {
            margin-top: var(--space-7);
            padding-top: var(--space-4);
            border-top: 1px solid var(--border);
            text-align: center;
        }
        .inv-thank {
            font-family: var(--font-sans);
            font-style: italic;
            font-weight: 400;
            font-size: var(--text-lg);
            color: var(--accent-700);
            margin-bottom: var(--space-1);
            letter-spacing: -0.005em;
        }
        .inv-brand {
            font-size: var(--text-xs);
            color: var(--muted);
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        @media print {
            @page { size: letter; margin: 12mm; }
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .invoice {
                box-shadow: none;
                border: 0;
                max-width: 100%;
                padding: 0;
                border-radius: 0;
            }
            .inv-totals tr.total td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .inv-table thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ route('admin.payments.index') }}" class="secondary">← Back to Payments</a>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="invoice">
    {{-- ===== HEADER ===== --}}
    <div class="inv-header">
        <div class="inv-logo">
            <img src="{{ asset('Images/logo.png') }}" alt="Apex Growth Solutions">
        </div>
        <div class="inv-divider"></div>
        <div class="inv-meta">
            <div class="inv-meta-title">INVOICE</div>
            <div class="inv-meta-row">
                <strong>Invoice Date:</strong>
                <span>{{ $invoice->invoice_date->format('F j, Y') }}</span>
            </div>
            <div class="inv-meta-row">
                <strong>Invoice No.:</strong>
                <span>{{ $invoice->invoice_number }}</span>
            </div>
        </div>
    </div>

    {{-- ===== BILLED TO ===== --}}
    @php
        $bo = $invoice->client;
        $boName = $bo->intake_display_name ?: $bo->business_name;
        // If we have both names, show one as headline and the other as sub-line
        $hasDistinct = $bo->intake_display_name && $bo->intake_display_name !== $bo->business_name;
    @endphp
    <div class="inv-billed">
        <div class="inv-billed-label">BILLED TO:</div>
        <div class="inv-billed-name">{{ $boName }}</div>
        @if ($hasDistinct)
            <div class="inv-billed-sub">{{ $bo->business_name }}</div>
        @endif
    </div>

    {{-- ===== TABLE ===== --}}
    @php
        $itemsByRound = collect($invoice->items)->groupBy('round')->sortKeys();
        $rowNum = 0;
        $unitPrice = collect($invoice->items)->first()['amount'] ?? 0;
    @endphp
    <table class="inv-table">
        <thead>
            <tr>
                <th colspan="2">DESCRIPTION</th>
                <th class="center">QTY</th>
                <th class="right">UNIT PRICE</th>
                <th class="right">AMOUNT (USD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($itemsByRound as $rNum => $items)
                @php
                    $rLabel = ['1st','2nd','3rd','4th','5th'][$rNum - 1] ?? "{$rNum}th";
                @endphp
                <tr>
                    <td class="inv-desc-head" colspan="5">{{ $rLabel }} Round Credit Repair — for the following clients:</td>
                </tr>
                @foreach ($items as $it)
                    @php $rowNum++; @endphp
                    <tr>
                        <td class="inv-line-num">{{ $rowNum }}.</td>
                        <td>{{ $it['name'] }}</td>
                        <td class="center">1</td>
                        <td class="right">${{ number_format($it['amount'], 2) }}</td>
                        <td class="right">${{ number_format($it['amount'], 2) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- ===== TOTALS ===== --}}
    <table class="inv-totals">
        <tr class="subtotal">
            <td class="label">Subtotal ({{ count($invoice->items) }} {{ count($invoice->items) === 1 ? 'Client' : 'Clients' }})</td>
            <td class="amount">${{ number_format($invoice->total, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Tax (0%)</td>
            <td class="amount">$0.00</td>
        </tr>
        <tr class="total">
            <td class="label">TOTAL AMOUNT DUE</td>
            <td class="amount">${{ number_format($invoice->total, 2) }}</td>
        </tr>
    </table>

    {{-- ===== FOOTER ===== --}}
    <div class="inv-footer">
        <div class="inv-thank">Thank you for your business!</div>
        <div class="inv-brand">Apex Growth Solutions</div>
    </div>
</div>

<script>
    // Auto-open print dialog so the user can save as PDF immediately.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 500);
    });
</script>

</body>
</html>
