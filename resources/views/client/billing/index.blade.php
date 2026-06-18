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
            <div class="stat-value" @if ($s['tone'] === 'green') style="color:#059669;" @endif>{{ $s['value'] }}</div>
        </div>
    @endforeach
</div>

{{-- Invoices --}}
<div class="card">
    <div class="card-header">
        <h2>Invoices</h2>
    </div>
    <table class="data-table">
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
    </table>
</div>

{{-- Payment history --}}
<div class="card">
    <div class="card-header">
        <h2>Payment History</h2>
    </div>
    @if ($model === 'hourly')
        <table class="data-table">
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
        </table>
    @else
        <table class="data-table">
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
        </table>
    @endif
</div>
@endsection
