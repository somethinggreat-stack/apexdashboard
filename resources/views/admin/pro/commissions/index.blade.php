@extends('layouts.admin-pro')

@section('title', 'Commissions')

@section('content')
@php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#a78bfa,#7c3aed);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <h2>Commissions</h2>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        Referrers earn $5.00 for every real client payment under the business owners they referred. Test/free payments don't count.
        Open a referrer to see the breakdown and record payouts. Make a business owner a referrer with the “Commission referrer” box on their form.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Referred BOs</th>
                    <th>Client Payments</th>
                    <th>Earned</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($referrers as $row)
                    <tr class="cm-row" data-href="{{ route('admin.commissions.show', $row['referrer']->id) }}">
                        <td><strong>{{ $row['referrer']->business_name }}</strong></td>
                        <td>{{ $row['referred_count'] }}</td>
                        <td>{{ $row['payments'] }}</td>
                        <td><strong style="color:#4338ca;">{{ $money($row['earned']) }}</strong></td>
                        <td style="color:#047857;">{{ $money($row['paid']) }}</td>
                        <td style="color:#b45309;">{{ $money($row['outstanding']) }}</td>
                        <td style="text-align:right;">
                            <a href="{{ route('admin.commissions.show', $row['referrer']->id) }}" class="pro-act">Open →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No commission referrers yet. Tick “Commission referrer” on a business owner's Add/Edit form.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .cm-row { cursor:pointer; transition:background .12s; }
    .cm-row:hover { background:#f8fafc; }
    .pro-act { font-weight:700; color:var(--pro-indigo); text-decoration:none; white-space:nowrap; }
</style>
@endpush
@push('scripts')
<script>
/* Whole referrer row is clickable (but real links inside still work). */
document.querySelectorAll('.cm-row[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a,button,form')) return;
        window.location = tr.getAttribute('data-href');
    });
});
</script>
@endpush
@endsection
