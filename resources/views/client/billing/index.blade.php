@extends('layouts.client')

@section('title', 'Billing')

@php
    $byPeriod = in_array($model, ['hourly', 'package'], true);
    $oTotal   = $byPeriod ? (float) $outstanding : (float) $outstanding['total'];
    $owes     = $oTotal > 0;
    $fee      = (float) ($client->per_round_fee ?? 0);

    // Plain-English hint under each summary number.
    $hints = [
        'Total Paid'         => 'Everything you have paid so far',
        'Outstanding'        => $owes ? 'Still owed right now' : 'Nothing owed',
        'Paid This Month'    => now()->format('F Y'),
        'Fee per Round'      => 'Charged per client, per round',
        'Advance Credit'     => 'Prepaid — used on unpaid rounds',
        'Rounds Paid'        => 'Rounds with a payment recorded',
        'Hourly Rate'        => 'Paid per hour logged',
        'Payouts Recorded'   => 'Payments on your account',
        'Package'            => 'Your monthly package',
        'Rounds This Period' => 'Used in the current period',
    ];

    // Per-round model: group the unpaid rounds by client so each client is one clear row.
    $owedByClient = collect();
    if (! $byPeriod) {
        $owedByClient = collect($outstanding['items'])->groupBy('name')->map(fn ($rows, $name) => [
            'name'   => $name,
            'rounds' => $rows->pluck('round')->sort()->values()->all(),
            'amount' => $rows->sum('amount'),
        ])->sortBy('name')->values();
    }

    $lastPayment = $byPeriod ? ($payouts->first() ?? null) : ($payments->first() ?? null);
@endphp

@push('head')
<style>
    .bl-hero { display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;
        padding:24px 28px; border-radius:14px; margin-bottom:20px; border:1px solid var(--border); }
    .bl-hero.owes { background:var(--tint-amber-bg); border-color:transparent; }
    .bl-hero.clear { background:var(--tint-green-bg); border-color:transparent; }
    .bl-hero-label { font-size:13px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
    .bl-hero.owes .bl-hero-label, .bl-hero.owes .bl-hero-amt { color:var(--tint-amber-fg); }
    .bl-hero.clear .bl-hero-label, .bl-hero.clear .bl-hero-amt { color:var(--tint-green-fg); }
    .bl-hero-amt { font-size:44px; font-weight:800; line-height:1.1; margin:4px 0 6px; }
    .bl-hero-sub { font-size:14px; color:var(--text); max-width:560px; }
    .bl-hero-side { text-align:right; font-size:13px; color:var(--text-soft); }
    .bl-hero-side strong { display:block; font-size:15px; color:var(--text); }

    .bl-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:20px; }
    .bl-stat { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px 18px; }
    .bl-stat-label { font-size:12px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); }
    .bl-stat-value { font-size:26px; font-weight:700; margin:4px 0 2px; color:var(--text); }
    .bl-stat-value.green { color:#059669; }
    .bl-stat-value.orange { color:#ea580c; }
    .bl-stat-hint { font-size:12px; color:var(--muted); }

    .bl-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .bl-head h2 { margin:0 0 2px; }
    .bl-head p { margin:0; font-size:13px; color:var(--text-soft); }
    .bl-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
    .bl-pill.unpaid { background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .bl-pill.paid { background:var(--tint-green-bg); color:var(--tint-green-fg); }
    .bl-chip { display:inline-block; padding:2px 9px; margin:2px 4px 2px 0; border-radius:6px; font-size:12px; font-weight:600;
        background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .bl-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bl-total-row td { font-weight:700; border-top:2px solid var(--border); }
    .bl-credit-row td { color:#047857; }
    .bl-search { width:100%; max-width:260px; padding:8px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--surface); color:var(--text); font-size:14px; }
    .bl-help { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-top:6px; }
    .bl-help div { font-size:13px; color:var(--text-soft); }
    .bl-help strong { display:block; color:var(--text); margin-bottom:2px; font-size:14px; }
    .bl-empty { padding:22px 0; text-align:center; color:var(--muted); font-size:14px; }

    @media (max-width:700px) {
        .bl-hero { padding:18px; }
        .bl-hero-amt { font-size:36px; }
        .bl-hero-side { text-align:left; }
    }
</style>
@endpush

@section('content')
<div class="welcome">
    <h2>Billing</h2>
    <p class="muted">What you owe, what you've paid, and the full payment history for your account. This page is view-only.</p>
</div>

{{-- 1. The answer to "what do I owe?" — first thing on the page. --}}
<div class="bl-hero {{ $owes ? 'owes' : 'clear' }}">
    <div>
        @if ($owes)
            <div class="bl-hero-label">You currently owe</div>
            <div class="bl-hero-amt">${{ number_format($oTotal, 2) }}</div>
            <div class="bl-hero-sub">
                @if ($byPeriod)
                    That is {{ $model === 'package' ? 'in monthly rounds' : 'in logged hours' }} that has not yet been paid out. The period breakdown is in Payment History below.
                @else
                    {{ $outstanding['count'] }} unpaid {{ \Illuminate\Support\Str::plural('round', $outstanding['count']) }}
                    × ${{ number_format($fee, 2) }} = ${{ number_format($outstanding['subtotal'], 2) }}.
                    @if ($outstanding['used'] > 0)
                        Your advance credit covers ${{ number_format($outstanding['used'], 2) }} of that.
                    @endif
                @endif
            </div>
        @else
            <div class="bl-hero-label">Account status</div>
            <div class="bl-hero-amt">All paid up ✓</div>
            <div class="bl-hero-sub">You have nothing outstanding. Thank you!</div>
        @endif
    </div>
    <div class="bl-hero-side">
        @if ($lastPayment)
            Last payment recorded
            @if ($byPeriod)
                <strong>${{ number_format($lastPayment->amount_paid, 2) }} · {{ $lastPayment->paid_at?->format('M d, Y') }}</strong>
            @else
                <strong>${{ number_format($lastPayment->amount, 2) }} · {{ $lastPayment->paid_at?->format('M d, Y') }}</strong>
                {{ $lastPayment->endUser?->full_name }} — Round {{ $lastPayment->round }}
            @endif
        @else
            No payments recorded yet
        @endif
    </div>
</div>

{{-- 2. Summary numbers, each with a plain-English line under it. --}}
<div class="bl-stats">
    @foreach ($stats as $s)
        <div class="bl-stat">
            <div class="bl-stat-label">{{ $s['label'] }}</div>
            <div class="bl-stat-value {{ $s['tone'] }}">{{ $s['value'] }}</div>
            @if (! empty($hints[$s['label']]))
                <div class="bl-stat-hint">{{ $hints[$s['label']] }}</div>
            @endif
        </div>
    @endforeach
</div>

{{-- 3. What is unpaid, one row per client. --}}
@if (! $byPeriod)
<div class="card">
    <div class="bl-head">
        <div>
            <h2>What's unpaid</h2>
            <p>Each round below has no payment recorded yet.</p>
        </div>
        <span class="bl-pill {{ $owes ? 'unpaid' : 'paid' }}">{{ $owes ? '$' . number_format($oTotal, 2) . ' unpaid' : 'Nothing unpaid' }}</span>
    </div>

    @if ($owedByClient->isNotEmpty())
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Client</th><th>Unpaid rounds</th><th class="bl-num">Amount owed</th></tr></thead>
            <tbody>
                @foreach ($owedByClient as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>@foreach ($row['rounds'] as $r)<span class="bl-chip">Round {{ $r }}</span>@endforeach</td>
                        <td class="bl-num">${{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;">Unpaid rounds subtotal</td>
                    <td class="bl-num">${{ number_format($outstanding['subtotal'], 2) }}</td>
                </tr>
                @if ($outstanding['used'] > 0)
                    <tr class="bl-credit-row">
                        <td colspan="2" style="text-align:right;">Less: your advance payment</td>
                        <td class="bl-num">−${{ number_format($outstanding['used'], 2) }}</td>
                    </tr>
                @endif
                <tr class="bl-total-row">
                    <td colspan="2" style="text-align:right;">Total you owe</td>
                    <td class="bl-num" style="color:#ea580c;">${{ number_format($outstanding['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table></div>
    @else
        <div class="bl-empty">Every round on your account has been paid. 🎉</div>
    @endif
</div>
@endif

{{-- Invoices list intentionally hidden from the business-owner portal: the
     running history of every generated invoice made it look like a large
     accumulated bill. Invoices are still generated and viewable on the admin
     side; BOs see only their current balance and Payment History. --}}

{{-- 4. Everything already paid. --}}
<div class="card">
    <div class="bl-head">
        <div>
            <h2>Payment history</h2>
            <p>Every payment recorded on your account, newest first.</p>
        </div>
        @unless ($byPeriod)
            <input type="search" id="blSearch" class="bl-search" placeholder="Search by client name…" autocomplete="off">
        @endunless
    </div>

    @if ($byPeriod)
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Period</th><th>{{ $model === 'package' ? 'Rounds' : 'Hours' }}</th><th class="bl-num">Amount paid</th><th>Date paid</th><th>Method</th></tr></thead>
            <tbody>
                @forelse ($payouts as $p)
                    <tr>
                        <td>{{ $p->period_start?->format('M d, Y') }} – {{ $p->period_end?->format('M d, Y') }}</td>
                        <td>{{ $model === 'package' ? ($p->rounds_in_period ?? '—') : number_format($p->hours_in_period, 2) . ' hrs' }}</td>
                        <td class="bl-num">${{ number_format($p->amount_paid, 2) }}</td>
                        <td>{{ $p->paid_at?->format('M d, Y') }}</td>
                        <td>{{ $p->method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    @else
        <div class="table-scroll"><table class="data-table" id="blPayments">
            <thead><tr><th>Client</th><th>Round</th><th>Status</th><th class="bl-num">Amount</th><th>Date paid</th><th>Method</th></tr></thead>
            <tbody>
                @forelse ($payments as $pay)
                    <tr data-name="{{ strtolower($pay->endUser?->full_name ?? '') }}">
                        <td>{{ $pay->endUser?->full_name ?? '—' }}</td>
                        <td>Round {{ $pay->round }}</td>
                        <td><span class="bl-pill paid">Paid</span></td>
                        <td class="bl-num">${{ number_format($pay->amount, 2) }}</td>
                        <td>{{ $pay->paid_at?->format('M d, Y') }}</td>
                        <td>{{ $pay->method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
            @if ($payments->isNotEmpty())
                <tfoot>
                    <tr class="bl-total-row">
                        <td colspan="3" style="text-align:right;">Total paid</td>
                        <td class="bl-num">${{ number_format($payments->sum('amount'), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table></div>
        <div class="bl-empty" id="blNoMatch" style="display:none;">No payments match that name.</div>
    @endif
</div>

{{-- 5. How it works, in plain words. --}}
<div class="card">
    <div class="bl-head"><div><h2>How billing works</h2></div></div>
    <div class="bl-help">
        @if ($model === 'per_round')
            <div><strong>You're billed per round</strong>You pay ${{ number_format($fee, 2) }} for each round of each client's credit repair.</div>
            <div><strong>Unpaid until recorded</strong>A round shows as unpaid until our team records your payment for it.</div>
            <div><strong>Advance credit</strong>If you pay ahead, the extra shows as credit and is applied to the next unpaid rounds.</div>
        @elseif ($model === 'package')
            <div><strong>Monthly package</strong>You pay a fixed fee for a set number of rounds each period.</div>
            <div><strong>Paid by period</strong>Each payment covers one billing period and appears in Payment History.</div>
        @else
            <div><strong>Hourly billing</strong>You're paid for the hours logged at your hourly rate.</div>
            <div><strong>Paid by period</strong>Each payout covers one pay period and appears in Payment History.</div>
        @endif
        <div><strong>Something look wrong?</strong>Send us a message from <a href="{{ route('client.messages.index') }}">Messages</a> and we'll check it right away.</div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var box = document.getElementById('blSearch');
        var table = document.getElementById('blPayments');
        if (!box || !table) return;
        var rows = table.querySelectorAll('tbody tr[data-name]');
        var none = document.getElementById('blNoMatch');
        box.addEventListener('input', function () {
            var q = box.value.trim().toLowerCase(), shown = 0;
            rows.forEach(function (r) {
                var hit = !q || r.dataset.name.indexOf(q) !== -1;
                r.style.display = hit ? '' : 'none';
                if (hit) shown++;
            });
            if (none) none.style.display = shown ? 'none' : '';
        });
    })();
</script>
@endpush
@endsection
