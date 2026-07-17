@extends('layouts.admin-pro')

@section('title', 'Commissions')

@section('content')
@php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

{{-- ============ Per-referrer summary (earned / paid / outstanding) ============ --}}
@forelse ($referrers as $ref)
<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#a78bfa,#7c3aed);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <h2>{{ $ref['name'] }} — Commission</h2>
        </div>
    </div>

    <div class="cm-stats">
        <div class="cm-stat"><span class="cm-lbl">Total Earned</span><span class="cm-val" style="color:#4338ca;">{{ $money($ref['earned']) }}</span></div>
        <div class="cm-stat"><span class="cm-lbl">Paid Out</span><span class="cm-val" style="color:#047857;">{{ $money($ref['paid']) }}</span></div>
        <div class="cm-stat"><span class="cm-lbl">Outstanding</span><span class="cm-val" style="color:#b45309;">{{ $money($ref['outstanding']) }}</span></div>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Business Owner</th><th>Client Payments</th><th>Per Payment</th><th>Earned</th></tr>
            </thead>
            <tbody>
                @foreach ($ref['lines'] as $line)
                    <tr>
                        <td><strong>{{ $line['bo']->business_name }}</strong></td>
                        <td>{{ $line['payments'] }}</td>
                        <td>{{ $money($line['rate']) }}</td>
                        <td><strong>{{ $money($line['earned']) }}</strong></td>
                    </tr>
                @endforeach
                <tr class="cm-total">
                    <td>Total</td>
                    <td>{{ $ref['lines']->sum('payments') }}</td>
                    <td>—</td>
                    <td>{{ $money($ref['earned']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="cm-payout-box">
        <h3>Record a payout to {{ $ref['name'] }}</h3>
        <form method="POST" action="{{ route('admin.commissions.payout.store') }}" class="cm-payout">
            @csrf
            <input type="hidden" name="referrer_name" value="{{ $ref['name'] }}">
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
                @forelse ($ref['payouts'] as $p)
                    <tr>
                        <td>{{ $p->paid_at?->format('M j, Y') }}</td>
                        <td><strong>{{ $money($p->amount) }}</strong></td>
                        <td>{{ $p->note ?: '—' }}</td>
                        <td class="muted">{{ $p->createdBy?->full_name ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.commissions.payout.destroy', $p->id) }}"
                                  onsubmit="return confirm('Delete this payout record?')">
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
@empty
<div class="pro-panel" style="margin-bottom:18px; padding:22px;">
    <p class="muted" style="margin:0;">No referred business owners yet. Mark which ones were referred (and by whom) in the section below — their commission will appear here.</p>
</div>
@endforelse

{{-- ============ Assign: pick which BOs are referred ============ --}}
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#38bdf8,#2563eb);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </span>
            <h2>Referred Business Owners</h2>
        </div>
    </div>
    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        Set a <strong>Referrer</strong> and a <strong>per-payment</strong> amount on any business owner. Every client payment recorded under them earns the referrer that amount. Clear the referrer to remove it.
    </p>
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Business Owner</th><th>Referrer</th><th>Commission / payment</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($allBos as $bo)
                    <tr>
                        <td><strong>{{ $bo->business_name }}</strong></td>
                        <td colspan="3">
                            <form method="POST" action="{{ route('admin.commissions.assign') }}" class="cm-assign">
                                @csrf
                                <input type="hidden" name="client_id" value="{{ $bo->id }}">
                                <input type="text" name="referrer_name" value="{{ $bo->referrer_name }}" placeholder="e.g. Chantal">
                                <input type="number" step="0.01" min="0" name="commission_per_payment"
                                       value="{{ $bo->commission_per_payment !== null ? number_format((float) $bo->commission_per_payment, 2, '.', '') : '' }}"
                                       placeholder="0.00">
                                <button type="submit" class="pro-act view">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
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
    .cm-payout, .cm-assign { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
    .cm-assign { align-items:center; }
    .cm-payout label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#64748b; }
    .cm-payout label.grow { flex:1 1 220px; }
    .cm-payout input, .cm-assign input {
        padding:9px 12px; border:1px solid #d7dee8; border-radius:9px; font-size:13.5px; background:#fff; color:#0f172a;
    }
    .cm-assign input[name="referrer_name"] { min-width:200px; }
    .cm-assign input[name="commission_per_payment"] { width:120px; }
    .cm-payout input:focus, .cm-assign input:focus { outline:none; border-color:var(--pro-indigo); }
</style>
@endpush
@endsection
