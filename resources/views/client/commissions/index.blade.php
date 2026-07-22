@extends('layouts.client')

@section('title', 'Commissions')

@section('content')
@php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

<div class="welcome">
    <h2>Your Referral Commission</h2>
    <p class="muted">You earn {{ $money($summary['rate']) }} for every client payment under the business owners you referred. Updated live.</p>
</div>

<div class="stats-grid" style="margin-bottom:18px;">
    <div class="stat-card">
        <div class="stat-label">Total Earned</div>
        <div class="stat-value">{{ $money($summary['earned']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Paid to You</div>
        <div class="stat-value">{{ $money($summary['paid']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value">{{ $money($summary['outstanding']) }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <div class="card-header"><div><h2 style="margin:0;">By Business Owner</h2></div></div>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr><th>Business Owner</th><th>Client Payments</th><th>Per Payment</th><th>Earned</th></tr>
        </thead>
        <tbody>
            @forelse ($summary['lines'] as $line)
                <tr>
                    <td><strong>{{ $line['bo']->business_name }}</strong></td>
                    <td>{{ $line['payments'] }}</td>
                    <td>{{ $money($line['rate']) }}</td>
                    <td><strong>{{ $money($line['earned']) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No referred business owners yet.</td></tr>
            @endforelse
            @if ($summary['lines']->isNotEmpty())
                <tr class="cm-total">
                    <td>Total</td>
                    <td>{{ $summary['lines']->sum('payments') }}</td>
                    <td>—</td>
                    <td>{{ $money($summary['earned']) }}</td>
                </tr>
            @endif
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><div><h2 style="margin:0;">Payouts to You</h2></div></div>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr><th>Paid On</th><th>Amount</th><th>Note</th></tr>
        </thead>
        <tbody>
            @forelse ($summary['payouts'] as $p)
                <tr>
                    <td>{{ $p->paid_at?->format('M j, Y') }}</td>
                    <td><strong>{{ $money($p->amount) }}</strong></td>
                    <td>{{ $p->note ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">No payouts recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .cm-total td { font-weight:800; background:#f8fafc; border-top:2px solid #e2e8f0; }
</style>
@endpush
@endsection
