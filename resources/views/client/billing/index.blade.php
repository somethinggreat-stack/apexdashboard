@extends('layouts.client')

@section('title', 'Billing')

@section('content')
<div class="welcome">
    <h2>Billing</h2>
    <p class="muted">A read-only summary of your invoices and the payments recorded on your account.</p>
</div>

<div class="stats-grid">
    @foreach ($stats as $s)
        <div class="stat-card">
            <div class="stat-label">{{ $s['label'] }}</div>
            <div class="stat-value"
                @if ($s['tone'] === 'green') style="color:#059669;"
                @elseif ($s['tone'] === 'orange') style="color:#ea580c;" @endif>{{ $s['value'] }}</div>
        </div>
    @endforeach
</div>

{{-- Outstanding / Unpaid --}}
<div class="card">
    <div class="card-header">
        <h2>Outstanding</h2>
        @php $oTotal = $model === 'hourly' ? $outstanding : $outstanding['total']; @endphp
        <span class="badge" style="background:{{ $oTotal > 0 ? '#ffedd5' : '#dcfce7' }}; color:{{ $oTotal > 0 ? '#9a3412' : '#166534' }};">
            ${{ number_format($oTotal, 2) }} unpaid
        </span>
    </div>

    @if ($model === 'hourly')
        @if ($outstanding > 0)
            <p class="muted" style="font-size:13px;">
                You have <strong>${{ number_format($outstanding, 2) }}</strong> in logged hours that has not yet been paid out.
                See the period breakdown in Payment History below.
            </p>
        @else
            <p class="muted" style="font-size:13px;">You're all paid up — nothing outstanding.</p>
        @endif
    @else
        @if ($outstanding['count'] > 0)
            <p class="muted" style="font-size:13px; margin-bottom:12px;">
                {{ $outstanding['count'] }} unpaid round(s) across your clients, totaling
                <strong>${{ number_format($outstanding['total'], 2) }}</strong>
                at ${{ number_format((float) ($client->per_round_fee ?? 0), 2) }} per round.
            </p>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Client</th><th>Round</th><th>Amount</th></tr></thead>
                <tbody>
                    @foreach ($outstanding['items'] as $it)
                        <tr>
                            <td>{{ $it['name'] }}</td>
                            <td>R{{ $it['round'] }}</td>
                            <td>${{ number_format($it['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right; font-weight:600;">Total Outstanding</td>
                        <td style="font-weight:700; color:#ea580c;">${{ number_format($outstanding['total'], 2) }}</td>
                    </tr>
                </tfoot>
            </table></div>
        @else
            <p class="muted" style="font-size:13px;">You're all paid up — nothing outstanding. 🎉</p>
        @endif
    @endif
</div>

{{-- Invoices --}}
<div class="card">
    <div class="card-header">
        <h2>Invoices</h2>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Invoice #</th><th>Date</th><th>Amount</th><th>&nbsp;</th></tr></thead>
        <tbody>
            @forelse ($invoices as $inv)
                <tr>
                    <td>{{ $inv->invoice_number }}</td>
                    <td>{{ $inv->invoice_date?->format('M d, Y') }}</td>
                    <td>${{ number_format($inv->total, 2) }}</td>
                    <td><a href="{{ route('client.billing.invoice.show', $inv->id) }}" target="_blank" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

{{-- Payment history --}}
<div class="card">
    <div class="card-header">
        <h2>Payment History</h2>
    </div>
    @if ($model === 'hourly')
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Period</th><th>Hours</th><th>Amount Paid</th><th>Date Paid</th><th>Method</th></tr></thead>
            <tbody>
                @forelse ($payouts as $p)
                    <tr>
                        <td>{{ $p->period_start?->format('M d, Y') }} – {{ $p->period_end?->format('M d, Y') }}</td>
                        <td>{{ number_format($p->hours_in_period, 2) }} hrs</td>
                        <td>${{ number_format($p->amount_paid, 2) }}</td>
                        <td>{{ $p->paid_at?->format('M d, Y') }}</td>
                        <td>{{ $p->method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    @else
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Client</th><th>Round</th><th>Amount</th><th>Date Paid</th><th>Method</th></tr></thead>
            <tbody>
                @forelse ($payments as $pay)
                    <tr>
                        <td>{{ $pay->endUser?->full_name ?? '—' }}</td>
                        <td>R{{ $pay->round }}</td>
                        <td>${{ number_format($pay->amount, 2) }}</td>
                        <td>{{ $pay->paid_at?->format('M d, Y') }}</td>
                        <td>{{ $pay->method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    @endif
</div>
@endsection
