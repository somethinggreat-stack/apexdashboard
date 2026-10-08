@php
    $cur      = $data['current'];
    $curCount = $cur ? $cur['price']['rounds'] : 0;
    $incl     = $data['includedRounds'];
    $pct      = $incl > 0 ? min(100, round($curCount / $incl * 100)) : 0;
@endphp

<div class="pay-stats">
    <div class="pay-stat-card">
        <div class="pay-stat-label">Package</div>
        <div class="pay-stat-value">${{ number_format($data['fee'], 2) }}</div>
        <div class="pay-stat-sub">{{ $incl }} rounds / month · ${{ number_format($data['overageFee'], 2) }} each extra</div>
    </div>
    <div class="pay-stat-card">
        <div class="pay-stat-label">Rounds This Period</div>
        <div class="pay-stat-value">{{ $curCount }} <span style="font-size:14px; color:var(--muted); font-weight:600;">/ {{ $incl }}</span></div>
        <div class="pay-stat-sub">
            @if ($cur){{ $cur['start']->format('M j') }} – {{ $cur['end']->format('M j') }}@endif
            @if ($cur && $cur['price']['extra'] > 0) · <strong style="color:#ea580c;">{{ $cur['price']['extra'] }} extra</strong>@elseif ($incl > $curCount) · {{ $incl - $curCount }} left in package @endif
        </div>
        <div class="pkg-meter"><span style="width: {{ $pct }}%;" class="{{ $cur && $cur['price']['extra'] > 0 ? 'over' : '' }}"></span></div>
    </div>
    <div class="pay-stat-card pay-stat-orange">
        <div class="pay-stat-label">Owed This Period</div>
        <div class="pay-stat-value">${{ number_format($cur['price']['total'] ?? 0, 2) }}</div>
        <div class="pay-stat-sub">Outstanding overall: ${{ number_format($data['outstanding'], 2) }}</div>
    </div>
    <div class="pay-stat-card pay-stat-green">
        <div class="pay-stat-label">Earned This Month</div>
        <div class="pay-stat-value">${{ number_format($data['earnedThisMonth'], 2) }}</div>
        <div class="pay-stat-sub">Total all-time: ${{ number_format($data['earnedTotal'], 2) }}</div>
    </div>
</div>

<div class="pay-block">
    <div class="pay-block-head">
        <div class="pay-block-title">Rounds by Month</div>
    </div>

    <div class="pay-period-current">
        A round counts in the month it was first selected for a client, and only while it is still on their round strip.
        Up to <strong>{{ $incl }}</strong> rounds are covered by the <strong>${{ number_format($data['fee'], 2) }}</strong> package;
        each round after that is <strong>${{ number_format($data['overageFee'], 2) }}</strong>. A month with no rounds owes nothing.
    </div>

    <table class="pay-time-table">
        <thead>
            <tr>
                <th>Period</th>
                <th class="hours-col">Rounds</th>
                <th class="hours-col">Amount</th>
                <th>Status</th>
                <th class="actions-col">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['periods'] as $p)
                @php $pr = $p['price']; @endphp
                <tr>
                    <td>
                        <strong>{{ $p['start']->format('M j, Y') }} – {{ $p['end']->format('M j, Y') }}</strong>
                        @if ($p['is_current']) <span class="pay-model-pill pay-pill-per-round" style="margin-left:6px;">Current</span> @endif
                        @if ($p['rounds']->isNotEmpty())
                            <details class="pkg-rounds">
                                <summary>Show the {{ $p['rounds']->count() }} {{ $p['rounds']->count() === 1 ? 'round' : 'rounds' }}</summary>
                                <ol>
                                    @foreach ($p['rounds'] as $i => $r)
                                        <li class="{{ $i >= $incl ? 'extra' : '' }}">
                                            {{ $r['name'] }} — Round {{ $r['round'] }}
                                            <span>{{ $r['processed_at']->copy()->timezone(\App\Services\RoundPackageBilling::TZ)->format('M j') }}</span>
                                            @if ($i >= $incl)<em>extra</em>@endif
                                        </li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                    </td>
                    <td class="hours-col">
                        {{ $pr['rounds'] }}
                        @if ($pr['extra'] > 0)
                            <div style="font-size:11px; color:#ea580c;">{{ $pr['included'] }} + {{ $pr['extra'] }} extra</div>
                        @endif
                    </td>
                    <td class="hours-col">
                        ${{ number_format($pr['total'], 2) }}
                        @if ($pr['extra'] > 0)
                            <div style="font-size:11px; color:var(--muted);">${{ number_format($pr['fee'], 2) }} + ${{ number_format($pr['extra_amount'], 2) }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($p['payout'])
                            <span class="pay-payout-paid">✓ ${{ number_format($p['payout']->amount_paid, 2) }}</span>
                            <div style="font-size:11px; color:var(--muted);">{{ $p['payout']->paid_at?->format('M j, Y') }}{{ $p['payout']->method ? ' · '.$p['payout']->method : '' }}</div>
                        @elseif ($pr['rounds'] === 0)
                            <span style="color:var(--muted);">No rounds</span>
                        @else
                            <span class="pay-payout-pending">{{ $p['is_current'] ? 'In progress' : 'Awaiting payment' }}</span>
                        @endif
                    </td>
                    <td class="actions-col" style="white-space:nowrap;">
                        @if ($pr['rounds'] > 0)
                            <form method="POST" action="{{ route('admin.payments.invoice.generate') }}" target="_blank" style="display:inline; margin:0;">
                                @csrf
                                <input type="hidden" name="period_start" value="{{ $p['start']->toDateString() }}">
                                <button type="submit" class="btn btn-sm btn-secondary" title="Invoice this month's package and extra rounds">Invoice</button>
                            </form>
                        @endif
                        @if ($p['payout'])
                            <form method="POST" action="{{ route('admin.payments.payout.destroy', $p['payout']->id) }}" data-confirm-delete data-confirm-message="Remove this payment record?" style="display:inline;">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Undo</button>
                            </form>
                        @elseif ($pr['rounds'] > 0)
                            <button class="pay-btn-primary" type="button"
                                onclick="openPackagePayout('{{ $p['start']->toDateString() }}', '{{ $p['end']->toDateString() }}', {{ $pr['rounds'] }}, {{ json_encode((float) $pr['total']) }})">
                                Record Payment
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Record Payment modal --}}
<div id="packagePayoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <button class="modal-close" onclick="closeModal('packagePayoutModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.payout.store') }}">
            @csrf
            <input type="hidden" name="period_start" id="pp-start">
            <input type="hidden" name="period_end"   id="pp-end">
            <input type="hidden" name="hours_in_period" value="0">
            <input type="hidden" name="rounds_in_period" id="pp-rounds">
            <div class="form-row">
                <div class="form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" name="amount_paid" id="pp-amount" required></div>
                <div class="form-group"><label>Date Paid</label><input type="date" name="paid_at" value="{{ now()->toDateString() }}" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Method (optional)</label><input type="text" name="method" placeholder="Bank / Zelle / Wire"></div>
                <div class="form-group"><label>Comments (optional)</label><input type="text" name="notes"></div>
            </div>
            <div class="muted small" id="pp-period-label" style="margin: 4px 0 8px;"></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('packagePayoutModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .pkg-meter { height:6px; border-radius:999px; background:var(--border, #e5e7eb); margin-top:8px; overflow:hidden; }
    .pkg-meter span { display:block; height:100%; background:#6366f1; border-radius:999px; }
    .pkg-meter span.over { background:#ea580c; }
    .pkg-rounds { margin-top:6px; font-size:12px; }
    .pkg-rounds summary { cursor:pointer; color:#4f46e5; font-weight:600; }
    .pkg-rounds ol { margin:6px 0 0 18px; padding:0; max-height:260px; overflow:auto; }
    .pkg-rounds li { padding:1px 0; }
    .pkg-rounds li span { color:var(--muted, #64748b); margin-left:4px; }
    .pkg-rounds li.extra { color:#c2410c; }
    .pkg-rounds li em { font-style:normal; font-size:10px; font-weight:700; text-transform:uppercase; margin-left:4px; }
</style>
@endpush

@push('scripts')
<script>
window.openPackagePayout = function (start, end, rounds, amount) {
    document.getElementById('pp-start').value = start;
    document.getElementById('pp-end').value = end;
    document.getElementById('pp-rounds').value = rounds;
    document.getElementById('pp-amount').value = (amount || 0).toFixed(2);
    document.getElementById('pp-period-label').textContent = 'Period: ' + start + ' to ' + end + ' (' + rounds + ' rounds)';
    openModal('packagePayoutModal');
};
</script>
@endpush
