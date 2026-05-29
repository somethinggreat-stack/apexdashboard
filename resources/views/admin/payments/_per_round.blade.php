<div class="pay-stats">
    <div class="pay-stat-card">
        <div class="pay-stat-label">Rate per Round</div>
        <div class="pay-stat-value">${{ number_format($data['rate'], 2) }}</div>
        <div class="pay-stat-sub">Set in payment arrangement above</div>
    </div>
    <div class="pay-stat-card pay-stat-green">
        <div class="pay-stat-label">Earned This Month</div>
        <div class="pay-stat-value">${{ number_format($data['earnedThisMonth'], 2) }}</div>
        <div class="pay-stat-sub">From payments paid_at in current month</div>
    </div>
    <div class="pay-stat-card">
        <div class="pay-stat-label">Total Earned (Lifetime)</div>
        <div class="pay-stat-value">${{ number_format($data['earnedTotal'], 2) }}</div>
        <div class="pay-stat-sub">From this BO, all-time</div>
    </div>
    <div class="pay-stat-card pay-stat-orange">
        <div class="pay-stat-label">Outstanding</div>
        <div class="pay-stat-value">${{ number_format($data['outstanding'], 2) }}</div>
        <div class="pay-stat-sub">{{ $data['dueCount'] }} round(s) due to invoice</div>
    </div>
</div>

<div class="pay-matrix">
    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th class="pay-cell">R1</th>
                <th class="pay-cell">R2</th>
                <th class="pay-cell">R3</th>
                <th class="pay-cell">R4</th>
                <th class="pay-cell">R5</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] as $row)
                @php $eu = $row['end_user']; @endphp
                <tr>
                    <td class="pay-client">
                        <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                    </td>
                    @foreach ($row['cells'] as $r => $cell)
                        <td class="pay-cell">
                            @if ($cell['state'] === 'paid')
                                <span class="pay-cell-paid"
                                    onclick="openPayEdit({{ $cell['payment']->id }}, {{ json_encode((float) $cell['payment']->amount) }}, '{{ optional($cell['payment']->paid_at)->toDateString() }}', '{{ addslashes($cell['payment']->method ?? '') }}', '{{ addslashes($cell['payment']->notes ?? '') }}')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    {{ optional($cell['payment']->paid_at)->format('M j') }}
                                </span>
                            @elseif ($cell['state'] === 'due')
                                <div class="pay-cell-due">
                                    <button type="button"
                                        onclick="openMarkPaid({{ $eu->id }}, {{ $r }}, '{{ addslashes($eu->full_name) }}', {{ json_encode($data['rate']) }})">
                                        Mark Paid
                                    </button>
                                </div>
                            @else
                                <span class="pay-cell-idle">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="6" class="pay-empty">No clients for this BO yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Mark-Paid modal --}}
<div id="markPaidModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark Round <span id="mp-round">N</span> paid — <span id="mp-name">client</span></h3>
            <button class="modal-close" onclick="closeModal('markPaidModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.payments.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" id="mp-eu">
            <input type="hidden" name="round" id="mp-rd">
            <div class="form-row">
                <div class="form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" name="amount" id="mp-amount" required></div>
                <div class="form-group"><label>Date Paid</label><input type="date" name="paid_at" id="mp-date" value="{{ now()->toDateString() }}" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Method (optional)</label><input type="text" name="method" placeholder="Zelle / Bank / Cash"></div>
                <div class="form-group"><label>Notes (optional)</label><input type="text" name="notes"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('markPaidModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit/Undo payment modal --}}
<div id="payEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Payment</h3>
            <button class="modal-close" onclick="closeModal('payEditModal')">&times;</button>
        </div>
        <form method="POST" id="payEditForm">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" name="amount" id="pe-amount" required></div>
                <div class="form-group"><label>Date Paid</label><input type="date" name="paid_at" id="pe-date" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Method</label><input type="text" name="method" id="pe-method"></div>
                <div class="form-group"><label>Notes</label><input type="text" name="notes" id="pe-notes"></div>
            </div>
            <div class="form-actions" style="display:flex; justify-content:space-between; gap:8px;">
                <button type="button" class="btn btn-danger" id="pe-delete">Undo (Mark Unpaid)</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('payEditModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
        <form method="POST" id="payDeleteForm" style="display:none;">
            @csrf @method('DELETE')
        </form>
    </div>
</div>

@push('scripts')
<script>
window.openMarkPaid = function (euId, round, name, rate) {
    document.getElementById('mp-eu').value = euId;
    document.getElementById('mp-rd').value = round;
    document.getElementById('mp-round').textContent = round;
    document.getElementById('mp-name').textContent = name;
    document.getElementById('mp-amount').value = (rate || 0).toFixed(2);
    openModal('markPaidModal');
};

window.openPayEdit = function (paymentId, amount, paidAt, method, notes) {
    var base = "{{ url('admin/payments') }}/" + paymentId;
    document.getElementById('payEditForm').action = base;
    document.getElementById('payDeleteForm').action = base;
    document.getElementById('pe-amount').value = amount;
    document.getElementById('pe-date').value = paidAt;
    document.getElementById('pe-method').value = method || '';
    document.getElementById('pe-notes').value = notes || '';
    document.getElementById('pe-delete').onclick = function () {
        if (confirm('Mark this round unpaid?')) {
            document.getElementById('payDeleteForm').submit();
        }
    };
    openModal('payEditModal');
};
</script>
@endpush
