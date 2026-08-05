@extends('layouts.admin-pro')

@section('title', 'Commissions')

@section('topbar-action')
    <a href="{{ route('admin.commissions.index') }}" class="btn btn-secondary page-action-btn">← All Commissions</a>
@endsection

@section('content')
@php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#a78bfa,#7c3aed);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <h2>{{ $summary['name'] }} — Commission</h2>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        {{ $summary['name'] }} earns {{ $money($summary['rate']) }} for every real client payment under the business owners they referred.
        Test/free payments don't count. Mark who referred a business owner with the boxes on the Add/Edit Business Owner form.
    </p>

    <div class="cm-stats">
        <div class="cm-stat"><span class="cm-lbl">Total Earned</span><span class="cm-val" style="color:#4338ca;">{{ $money($summary['earned']) }}</span></div>
        <div class="cm-stat"><span class="cm-lbl">Paid Out</span><span class="cm-val" style="color:#047857;">{{ $money($summary['paid']) }}</span></div>
        <div class="cm-stat"><span class="cm-lbl">Outstanding</span><span class="cm-val" style="color:#b45309;">{{ $money($summary['outstanding']) }}</span></div>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table">
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
                    <tr><td colspan="4" class="empty">No referred business owners yet. On a business owner's form, tick “Referred by {{ $summary['name'] }}”.</td></tr>
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
        </table>
    </div>

    <div class="cm-payout-box">
        <h3>Record a payout to {{ $summary['name'] }}</h3>
        <form method="POST" action="{{ route('admin.commissions.payout.store', $summary['referrer']->id) }}" class="cm-payout">
            @csrf
            <label>Amount <input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required></label>
            <label>Paid on <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required></label>
            <label class="grow">Note <input type="text" name="note" placeholder="e.g. paid via Zelle for June"></label>
            <button type="submit" class="pro-cta">Record Payout</button>
        </form>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Paid On</th><th>Amount</th><th>Note</th><th>Recorded By</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($summary['payouts'] as $p)
                    <tr>
                        <td>{{ $p->paid_at?->format('M j, Y') }}</td>
                        <td><strong>{{ $money($p->amount) }}</strong></td>
                        <td>{{ $p->note ?: '—' }}</td>
                        <td class="muted">{{ $p->createdBy?->full_name ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.commissions.payout.destroy', $p->id) }}"
                                  data-confirm-delete data-confirm-title="Delete this payout?"
                                  data-confirm-message="This payout record will be permanently removed.">
                                @csrf @method('DELETE')
                                <button class="pro-act del">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No payouts recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .cm-stats { display:flex; flex-wrap:wrap; gap:14px; padding:4px 22px 14px; }
    .cm-stat { flex:1 1 160px; background:#fff; border:1px solid #eef1f6; border-radius:14px; padding:14px 16px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .cm-lbl { display:block; font-size:12px; font-weight:600; color:#64748b; }
    .cm-val { display:block; font-size:24px; font-weight:800; margin-top:4px; letter-spacing:-.5px; }
    .cm-total td { font-weight:800; background:#f8fafc; border-top:2px solid #e2e8f0; }
    .cm-payout-box { padding:14px 22px 4px; }
    .cm-payout-box h3 { margin:0 0 10px; font-size:15px; }
    .cm-payout { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
    .cm-payout label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#64748b; }
    .cm-payout label.grow { flex:1 1 220px; }
    .cm-payout input { padding:9px 12px; border:1px solid #d7dee8; border-radius:9px; font-size:13.5px; background:#fff; color:#0f172a; }
    .cm-payout input:focus { outline:none; border-color:var(--pro-indigo); }

    /* Dark mode */
    :root[data-theme="dark"] .cm-stat { background: var(--pro-card); border-color: var(--pro-line); }
    :root[data-theme="dark"] .cm-lbl { color: var(--pro-muted); }
    :root[data-theme="dark"] .cm-total td { background: rgba(255,255,255,.03); border-top-color: var(--pro-line); }
    :root[data-theme="dark"] .cm-payout label { color: var(--pro-muted); }
    :root[data-theme="dark"] .cm-payout input { background:#10152a; border-color: var(--pro-line); color: var(--pro-text); }
</style>
@endpush
@endsection
