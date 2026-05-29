<div class="pay-stats">
    <div class="pay-stat-card">
        <div class="pay-stat-label">Hourly Rate</div>
        <div class="pay-stat-value">${{ number_format($data['rate'], 2) }}</div>
        <div class="pay-stat-sub">{{ ucfirst($data['cycle']) }} cycle{{ $data['weeklyHoursTarget'] ? ' · '.$data['weeklyHoursTarget'].' hrs/wk target' : '' }}</div>
    </div>
    <div class="pay-stat-card">
        <div class="pay-stat-label">Current Period</div>
        <div class="pay-stat-value" style="font-size:18px;">{{ $data['currentStart']->format('M j') }} – {{ $data['currentEnd']->format('M j') }}</div>
        <div class="pay-stat-sub">{{ number_format($data['hoursThisPeriod'], 2) }} hrs logged so far</div>
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

{{-- Time Card --}}
<div class="pay-block">
    <div class="pay-block-head">
        <div class="pay-block-title">Time Card</div>
        <button class="pay-btn-primary" type="button" onclick="openModal('logTimeModal')">+ Log Hours</button>
    </div>

    <div class="pay-period-current">
        Current period <strong>{{ $data['currentStart']->format('M j, Y') }} – {{ $data['currentEnd']->format('M j, Y') }}</strong> · {{ number_format($data['hoursThisPeriod'], 2) }} hrs · Expected ${{ number_format($data['expectedNow'], 2) }}
    </div>

    @if ($data['recentEntries']->isEmpty())
        <div class="pay-empty">No hours logged yet. Click + Log Hours to start.</div>
    @else
        <table class="pay-time-table">
            <thead><tr><th class="date-col">Date</th><th class="hours-col">Hours</th><th>Description</th><th class="actions-col">&nbsp;</th></tr></thead>
            <tbody>
                @foreach ($data['recentEntries'] as $e)
                    <tr>
                        <td class="date-col">{{ $e->work_date?->format('D · M j, Y') }}</td>
                        <td class="hours-col">{{ number_format($e->hours, 2) }} hrs</td>
                        <td>{{ $e->description ?? '—' }}</td>
                        <td class="actions-col">
                            <form method="POST" action="{{ route('admin.payments.time.destroy', $e->id) }}" onsubmit="return confirm('Delete this time entry?')" style="display:inline;">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">×</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Payouts --}}
<div class="pay-block">
    <div class="pay-block-title" style="margin-bottom:14px;">Payouts</div>

    @forelse ($data['periods'] as $p)
        <div class="pay-payout-row">
            <div>
                <strong>{{ $p['start']->format('M j, Y') }} – {{ $p['end']->format('M j, Y') }}</strong>
                @if ($p['is_current']) <span class="pay-model-pill pay-pill-per-round" style="margin-left:6px;">Current</span> @endif
                <div style="font-size:11.5px; color:#94a3b8;">{{ number_format($p['hours'], 2) }} hrs in period</div>
            </div>
            <div style="font-size:13px;">Expected: <strong>${{ number_format($p['expected'], 2) }}</strong></div>
            <div>
                @if ($p['payout'])
                    <span class="pay-payout-paid">✓ ${{ number_format($p['payout']->amount_paid, 2) }}</span>
                    <div style="font-size:11px; color:#94a3b8;">{{ $p['payout']->paid_at?->format('M j, Y') }}{{ $p['payout']->method ? ' · '.$p['payout']->method : '' }}</div>
                @else
                    <span class="pay-payout-pending">Awaiting payment</span>
                @endif
            </div>
            <div style="text-align:right;">
                @if ($p['payout'])
                    <form method="POST" action="{{ route('admin.payments.payout.destroy', $p['payout']->id) }}" onsubmit="return confirm('Remove this payout record?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Undo</button>
                    </form>
                @else
                    <button class="pay-btn-primary" type="button"
                        onclick="openRecordPayout('{{ $p['start']->toDateString() }}', '{{ $p['end']->toDateString() }}', {{ json_encode((float) $p['hours']) }}, {{ json_encode((float) $p['expected']) }})">
                        Record Payment
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div class="pay-empty">No periods yet — set the cycle anchor in payment settings above.</div>
    @endforelse
</div>

{{-- Log Time modal --}}
<div id="logTimeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Hours</h3>
            <button class="modal-close" onclick="closeModal('logTimeModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.time.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group"><label>Date</label><input type="date" name="work_date" value="{{ now()->toDateString() }}" required></div>
                <div class="form-group"><label>Hours (e.g. 4.5)</label><input type="number" step="0.25" min="0.25" max="24" name="hours" required></div>
            </div>
            <div class="form-group"><label>Description (optional)</label><textarea name="description" rows="3" placeholder="What was worked on…"></textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logTimeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
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
                <div class="form-group"><label>Notes (optional)</label><input type="text" name="notes"></div>
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
</script>
@endpush
