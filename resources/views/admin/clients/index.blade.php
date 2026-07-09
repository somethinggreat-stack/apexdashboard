@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Business Clients')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Business Owner Clients</h2>
        <button class="btn btn-primary" onclick="openModal('createClientModal')">+ Add Business Client</button>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Business Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Clients</th>
                <th>Monthly Fee</th>
                <th>Monthly Revenue</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td>{{ $client->business_name }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->phone ?? 'â€”' }}</td>
                    <td>{{ $client->end_users_count }}</td>
                    <td>${{ number_format($client->monthly_fee, 2) }}</td>
                    <td>${{ number_format($client->monthly_revenue, 2) }}</td>
                    <td><span class="pill pill-{{ $client->status }}">{{ $client->status }}</span></td>
                    <td>
                        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline" onsubmit="return confirm('Delete {{ $client->business_name }} and ALL of their clients? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No business clients yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

<div id="createClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Business Client</h3>
            <button class="modal-close" onclick="closeModal('createClientModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="business_name" value="{{ old('business_name') }}" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="form-group">
                <label>Monthly Fee ($)</label>
                <input type="number" step="0.01" name="monthly_fee" value="{{ old('monthly_fee', '149.00') }}">
            </div>

            <div class="form-section" style="margin-top:18px; padding:16px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                <h4 style="margin-top:0;">Payment Arrangement *</h4>
                <p class="muted" style="margin:0 0 12px; font-size:12.5px;">How does this BO pay you for the work on their clients?</p>

                <div class="form-group">
                    <label>Payment Model *</label>
                    <select name="compensation_model" id="modal-comp-model" required>
                        <option value="per_round" @selected(old('compensation_model', 'per_round') === 'per_round')>Per Round (flat fee per client per round)</option>
                        <option value="hourly"    @selected(old('compensation_model') === 'hourly')>Hourly (rate Ã— hours worked, paid in periods)</option>
                    </select>
                </div>

                <div class="modal-pay-per-round">
                    <div class="form-group">
                        <label>Fee per Round ($)</label>
                        <input type="number" step="0.01" min="0" name="per_round_fee" value="{{ old('per_round_fee') }}" placeholder="e.g. 12.00">
                    </div>
                </div>

                <div class="modal-pay-hourly">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hourly Rate ($)</label>
                            <input type="number" step="0.01" min="0" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="e.g. 5.00">
                        </div>
                        <div class="form-group">
                            <label>Weekly Hours Target</label>
                            <input type="number" min="0" max="168" name="weekly_hours_target" value="{{ old('weekly_hours_target') }}" placeholder="e.g. 30">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pay Cycle</label>
                            <select name="pay_cycle">
                                <option value="biweekly" @selected(old('pay_cycle', 'biweekly') === 'biweekly')>Bi-weekly (every 2 weeks)</option>
                                <option value="monthly"  @selected(old('pay_cycle') === 'monthly')>Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cycle Start Anchor</label>
                            <input type="date" name="pay_cycle_anchor" value="{{ old('pay_cycle_anchor', now()->startOfWeek()->toDateString()) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var sel = document.getElementById('modal-comp-model');
    if (!sel) return;
    function apply() {
        var isHourly = sel.value === 'hourly';
        document.querySelectorAll('.modal-pay-per-round').forEach(function (el) { el.style.display = isHourly ? 'none' : ''; });
        document.querySelectorAll('.modal-pay-hourly').forEach(function (el) { el.style.display = isHourly ? '' : 'none'; });
    }
    sel.addEventListener('change', apply);
    apply();

    // Re-open the modal automatically if the submission bounced back with errors,
    // so the entered values (preserved via old()) stay visible.
    @if ($errors->any())
        if (typeof openModal === 'function') openModal('createClientModal');
    @endif
})();
</script>
@endpush
@endsection
