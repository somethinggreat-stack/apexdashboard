<div class="pay-stats">
    <div class="pay-stat-card">
        <div class="pay-stat-label">Hourly Rate</div>
        <div class="pay-stat-value">${{ number_format($data['rate'], 2) }}</div>
        <div class="pay-stat-sub">{{ ucfirst($data['cycle']) }} cycle{{ $data['weeklyHoursTarget'] ? ' · '.$data['weeklyHoursTarget'].' hrs/wk target' : '' }}</div>
    </div>
    <div class="pay-stat-card">
        <div class="pay-stat-label">Current Period</div>
        <div class="pay-stat-value" style="font-size:18px;">{{ $data['currentStart']->format('M j') }} – {{ $data['currentEnd']->format('M j') }}</div>
        <div class="pay-stat-sub">{{ number_format($data['hoursThisPeriod'], 2) }} hrs this period</div>
    </div>
    <div class="pay-stat-card pay-stat-orange">
        <div class="pay-stat-label">Expected This Period</div>
        <div class="pay-stat-value">${{ number_format($data['expectedNow'], 2) }}</div>
        <div class="pay-stat-sub">Hours × rate</div>
    </div>
    <div class="pay-stat-card pay-stat-green">
        <div class="pay-stat-label">Earned This Month</div>
        <div class="pay-stat-value">${{ number_format($data['earnedThisMonth'], 2) }}</div>
        <div class="pay-stat-sub">Total all-time: ${{ number_format($data['earnedTotal'], 2) }}</div>
    </div>
</div>

{{-- Hours by Period — type the total hours for each period, no daily logging --}}
<div class="pay-block">
    <div class="pay-block-head">
        <div class="pay-block-title">Hours by Period</div>
        <form method="POST" action="{{ route('admin.payments.invoice.generate') }}" target="_blank" style="margin:0;">
            @csrf
            <button type="submit" class="pay-btn-primary" {{ $data['hoursThisPeriod'] <= 0 ? 'disabled' : '' }}
                    title="Creates an invoice for this period's total hours and opens the printable PDF page">
                Generate Invoice PDF
            </button>
        </form>
    </div>

    <div class="pay-period-current">
        Periods run from the <strong>cycle start date</strong> ({{ $data['currentStart']->format('M j, Y') }} – {{ $data['currentEnd']->format('M j, Y') }} is current). Enter the total hours worked in each period below.
    </div>

    <table class="pay-time-table">
        <thead>
            <tr>
                <th>Period</th>
                <th class="hours-col">Hours</th>
                <th class="hours-col">Expected</th>
                <th>Status</th>
                <th class="actions-col">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['periods'] as $p)
                <tr>
                    <td>
                        <strong>{{ $p['start']->format('M j, Y') }} – {{ $p['end']->format('M j, Y') }}</strong>
                        @if ($p['is_current']) <span class="pay-model-pill pay-pill-per-round" style="margin-left:6px;">Current</span> @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.payments.period.hours') }}" style="margin:0; display:flex; gap:6px; align-items:center;">
                            @csrf
                            <input type="hidden" name="period_start" value="{{ $p['start']->toDateString() }}">
                            <input type="hidden" name="period_end" value="{{ $p['end']->toDateString() }}">
                            <input type="number" step="0.25" min="0" max="1000" name="hours"
                                   value="{{ rtrim(rtrim(number_format($p['hours'], 2, '.', ''), '0'), '.') }}"
                                   class="period-hours-input" data-rate="{{ $data['rate'] }}"
                                   style="width:90px; padding:6px 8px; border:1px solid var(--border); border-radius:8px; font-size:13px;">
                            <button type="submit" class="pay-btn-primary" style="padding:6px 12px;">Save</button>
                        </form>
                    </td>
                    <td class="hours-col period-expected">${{ number_format($p['expected'], 2) }}</td>
                    <td>
                        @if ($p['payout'])
                            <span class="pay-payout-paid">✓ ${{ number_format($p['payout']->amount_paid, 2) }}</span>
                            <div style="font-size:11px; color:var(--muted);">{{ $p['payout']->paid_at?->format('M j, Y') }}{{ $p['payout']->method ? ' · '.$p['payout']->method : '' }}</div>
                        @else
                            <span class="pay-payout-pending">Awaiting payment</span>
                        @endif
                    </td>
                    <td class="actions-col">
                        @if ($p['payout'])
                            <form method="POST" action="{{ route('admin.payments.payout.destroy', $p['payout']->id) }}" data-confirm-delete data-confirm-message="Remove this payout record?" style="display:inline;">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Undo</button>
                            </form>
                        @else
                            <button class="pay-btn-primary" type="button"
                                onclick="openRecordPayout('{{ $p['start']->toDateString() }}', '{{ $p['end']->toDateString() }}', {{ json_encode((float) $p['hours']) }}, {{ json_encode((float) $p['expected']) }})">
                                Record Payment
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Record Payout modal --}}
<div id="recordPayoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <button class="modal-close" onclick="closeModal('recordPayoutModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.payout.store') }}">
            @csrf
            <input type="hidden" name="period_start" id="rp-start">
            <input type="hidden" name="period_end"   id="rp-end">
            <input type="hidden" name="hours_in_period" id="rp-hours">
            <div class="form-row">
                <div class="form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" name="amount_paid" id="rp-amount" required></div>
                <div class="form-group"><label>Date Paid</label><input type="date" name="paid_at" value="{{ now()->toDateString() }}" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Method (optional)</label><input type="text" name="method" placeholder="Bank / Zelle / Wire"></div>
                <div class="form-group"><label>Comments (optional)</label><input type="text" name="notes"></div>
            </div>
            <div class="muted small" id="rp-period-label" style="margin: 4px 0 8px;"></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('recordPayoutModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payout</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
window.openRecordPayout = function (start, end, hours, expected) {
    document.getElementById('rp-start').value = start;
    document.getElementById('rp-end').value = end;
    document.getElementById('rp-hours').value = hours;
    document.getElementById('rp-amount').value = (expected || 0).toFixed(2);
    document.getElementById('rp-period-label').textContent = 'Period: ' + start + ' to ' + end + ' (' + hours + ' hrs)';
    openModal('recordPayoutModal');
};

// Live-update the Expected cell as hours are typed (saved value persists on Save).
document.querySelectorAll('.period-hours-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
        var rate = parseFloat(inp.dataset.rate) || 0;
        var hrs = parseFloat(inp.value) || 0;
        var cell = inp.closest('tr').querySelector('.period-expected');
        if (cell) cell.textContent = '$' + (hrs * rate).toFixed(2);
    });
});
</script>
@endpush
