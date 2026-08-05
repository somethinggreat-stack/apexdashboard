<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/Images/logo.png">
    <link rel="apple-touch-icon" href="/Images/logo.png">
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }} — {{ $invoice->client->business_name }}</title>
    <style>
        @page { size: letter; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background: #f1f5f9; color: #0f172a;
        }
        .toolbar {
            max-width: 760px; margin: 0 auto 18px; display: flex; gap: 8px; justify-content: flex-end;
        }
        .toolbar a, .toolbar button {
            font-size: 13px; padding: 8px 14px; border-radius: 8px;
            border: 0; cursor: pointer; text-decoration: none; font-weight: 600;
        }
        .toolbar a.secondary { background: #e5e7eb; color: #374151; }
        .toolbar button.primary { background: #0ea5e9; color: #fff; }

        .invoice {
            max-width: 760px; margin: 0 auto;
            background: #fff; border-radius: 14px;
            padding: 44px 44px 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
        }

        /* ===== HEADER ===== */
        .inv-header { display: flex; gap: 30px; align-items: flex-start; padding-bottom: 18px; }
        .inv-logo { flex: 0 0 220px; }
        .inv-logo img { max-width: 100%; height: auto; display: block; }
        .inv-divider { width: 1.5px; background: #0b2e5b; align-self: stretch; opacity: .6; }
        .inv-meta { flex: 1; padding-left: 8px; }
        .inv-meta-title { font-size: 38px; font-weight: 800; color: #0b2e5b; letter-spacing: -1.5px; margin-bottom: 14px; }
        .inv-meta-row { display: flex; gap: 16px; font-size: 13px; margin-bottom: 6px; }
        .inv-meta-row strong { color: #0b2e5b; min-width: 96px; }
        .inv-meta-row span { color: #1e293b; font-weight: 500; }

        /* ===== BILLED TO ===== */
        .inv-billed { padding: 14px 0 22px; }
        .inv-billed-label { font-size: 11px; font-weight: 700; color: #0b2e5b; letter-spacing: 1px; margin-bottom: 6px; }
        .inv-billed-name { font-size: 19px; font-weight: 700; color: #0f172a; }
        .inv-billed-sub { font-size: 13px; color: #64748b; font-style: italic; margin-top: 2px; }

        /* ===== TABLE ===== */
        .inv-table { width: 100%; border-collapse: collapse; }
        .inv-table thead th {
            background: #0b2e5b; color: #fff;
            font-size: 11.5px; font-weight: 700; letter-spacing: .6px;
            text-align: left; padding: 12px 14px;
        }
        .inv-table thead th.right { text-align: right; }
        .inv-table thead th.center { text-align: center; }
        .inv-table tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 13px;
            vertical-align: top;
        }
        .inv-table tbody td.right { text-align: right; }
        .inv-table tbody td.center { text-align: center; }
        .inv-desc-head {
            font-weight: 700; color: #0b2e5b;
            padding: 14px 14px 8px !important;
            border-bottom: 0 !important;
        }
        .inv-line-num { color: #64748b; width: 30px; }
        /* A round delivered free of charge — listed so the client sees it, but $0 */
        .inv-free-row td { color: #047857; }
        .inv-free-tag {
            display: inline-block; margin-left: 8px; padding: 1px 8px;
            border-radius: 999px; background: #ecfdf5; color: #047857;
            border: 1px solid #a7f3d0; font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .04em;
        }

        /* ===== FOOTER (totals) ===== */
        .inv-totals { width: 100%; border-collapse: collapse; margin-top: 0; }
        .inv-totals td { padding: 10px 14px; font-size: 13px; }
        .inv-totals td.label { text-align: right; font-weight: 600; color: #0f172a; }
        .inv-totals td.amount { text-align: right; width: 140px; font-weight: 600; }
        .inv-totals tr.subtotal td { border-top: 1px solid #cbd5e1; }
        .inv-totals tr.total td {
            background: #0b2e5b; color: #fff;
            font-size: 15px; font-weight: 700;
            padding: 14px;
        }
        .inv-totals tr.total td.amount { font-size: 17px; }

        .inv-footer {
            margin-top: 28px; padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .inv-thank {
            font-family: "Brush Script MT", "Lucida Handwriting", cursive;
            font-size: 18px; color: #0b2e5b; margin-bottom: 4px;
        }
        .inv-brand {
            font-size: 11px; color: #94a3b8; font-weight: 700;
            letter-spacing: 2.5px; text-transform: uppercase;
        }

        @media print {
            @page { size: letter; margin: 12mm; }
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .invoice { box-shadow: none; max-width: 100%; padding: 0; border-radius: 0; }
        }
    </style>
</head>
<body>

@php
    $backUrl = Auth::guard('client')->check()
        ? route('client.billing.index')
        : route('admin.payments.index');
@endphp
<div class="toolbar">
    <a href="{{ $backUrl }}" class="secondary">← Back to Billing</a>
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
        $allItems = collect($invoice->items);
        $isHourly = $allItems->first() && ($allItems->first()['type'] ?? null) === 'hourly';
    @endphp

    @if ($isHourly)
        @php
            $rowNum    = 0;
            $totalHrs  = $allItems->sum('hours');
            $rate      = $allItems->first()['rate'] ?? 0;
            $firstDay  = \Carbon\Carbon::parse($allItems->min('date'))->format('M j, Y');
            $lastDay   = \Carbon\Carbon::parse($allItems->max('date'))->format('M j, Y');
        @endphp
        <table class="inv-table">
            <thead>
                <tr>
                    <th colspan="2">DESCRIPTION</th>
                    <th class="center">HOURS</th>
                    <th class="right">RATE</th>
                    <th class="right">AMOUNT (USD)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="inv-desc-head" colspan="5">Hourly Services — {{ $firstDay }} to {{ $lastDay }} @ ${{ number_format($rate, 2) }}/hr</td>
                </tr>
                @foreach ($allItems as $it)
                    @php $rowNum++; @endphp
                    <tr>
                        <td class="inv-line-num">{{ $rowNum }}.</td>
                        <td>{{ $it['label'] }}@if (!empty($it['description']) && $it['description'] !== 'Hourly work') <span style="color:#64748b;">— {{ $it['description'] }}</span>@endif</td>
                        <td class="center">{{ number_format($it['hours'], 2) }}</td>
                        <td class="right">${{ number_format($it['rate'], 2) }}</td>
                        <td class="right">${{ number_format($it['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ===== TOTALS ===== --}}
        <table class="inv-totals">
            <tr class="subtotal">
                <td class="label">Subtotal ({{ rtrim(rtrim(number_format($totalHrs, 2), '0'), '.') }} hrs × ${{ number_format($rate, 2) }})</td>
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
    @else
        @php
            $itemsByRound = $allItems->groupBy('round')->sortKeys();
            $rowNum = 0;
            // Complimentary rounds are listed but carry $0, so the billable
            // count must exclude them or the subtotal line misreads.
            $freeCount     = $allItems->filter(fn ($i) => !empty($i['free']))->count();
            $billableCount = $allItems->count() - $freeCount;
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
                        @php $rowNum++; $isFree = !empty($it['free']); @endphp
                        <tr class="{{ $isFree ? 'inv-free-row' : '' }}">
                            <td class="inv-line-num">{{ $rowNum }}.</td>
                            <td>
                                {{ $it['name'] }}
                                @if ($isFree)<span class="inv-free-tag">Complimentary — no charge</span>@endif
                            </td>
                            <td class="center">1</td>
                            <td class="right">{{ $isFree ? 'FREE' : '$' . number_format($it['amount'], 2) }}</td>
                            <td class="right">${{ number_format($it['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        {{-- ===== TOTALS ===== --}}
        <table class="inv-totals">
            <tr class="subtotal">
                <td class="label">Subtotal ({{ $billableCount }} {{ $billableCount === 1 ? 'Client' : 'Clients' }})</td>
                <td class="amount">${{ number_format($invoice->total, 2) }}</td>
            </tr>
            @if ($freeCount > 0)
                <tr>
                    <td class="label">Complimentary ({{ $freeCount }} {{ $freeCount === 1 ? 'round' : 'rounds' }} at no charge)</td>
                    <td class="amount">$0.00</td>
                </tr>
            @endif
            <tr>
                <td class="label">Tax (0%)</td>
                <td class="amount">$0.00</td>
            </tr>
            <tr class="total">
                <td class="label">TOTAL AMOUNT DUE</td>
                <td class="amount">${{ number_format($invoice->total, 2) }}</td>
            </tr>
        </table>
    @endif

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
