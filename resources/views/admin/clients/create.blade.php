@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Add Business Client')

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.clients.store') }}">
        @csrf
        <div class="form-group">
            <label>Business Name *</label>
            <input type="text" name="business_name" value="{{ old('business_name') }}" required>
        </div>
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="form-group">
            <label>Password *</label>
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

        <div class="form-group">
            <label>Round Timeline *</label>
            <select name="round_cycle_days" required>
                <option value="30" @selected(old('round_cycle_days', '30') == 30)>30-Day Rounds (standard)</option>
                <option value="20" @selected(old('round_cycle_days') == 20)>20-Day Rounds (accelerated)</option>
            </select>
            <small class="muted" style="display:block; margin-top:4px;">How long each dispute round runs for this owner's clients. All the same steps, paced to fit the window. Drives next-round dates, days-left and step reminders.</small>
        </div>

        @include('admin.clients._referral-fields')

        <div class="form-section" style="margin-top:24px; padding:16px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
            <h4 style="margin-top:0;">Payment Arrangement *</h4>
            <p class="muted" style="margin:0 0 12px; font-size:12.5px;">
                How does this BO pay you for the work on their clients?
            </p>

            <div class="form-group">
                <label>Payment Model *</label>
                <select name="compensation_model" id="create-comp-model" required>
                    <option value="per_round" @selected(old('compensation_model', 'per_round') === 'per_round')>Per Round (flat fee per client per round)</option>
                    <option value="hourly"    @selected(old('compensation_model') === 'hourly')>Hourly (rate × hours worked, paid in periods)</option>
                </select>
            </div>

            <div class="create-pay-per-round">
                <div class="form-group">
                    <label>Fee per Round ($) *</label>
                    <input type="number" step="0.01" min="0" name="per_round_fee" value="{{ old('per_round_fee') }}" placeholder="e.g. 12.00">
                </div>
            </div>

            <div class="create-pay-hourly">
                <div class="form-row">
                    <div class="form-group">
                        <label>Hourly Rate ($) *</label>
                        <input type="number" step="0.01" min="0" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="e.g. 5.00">
                    </div>
                    <div class="form-group">
                        <label>Weekly Hours Target</label>
                        <input type="number" min="0" max="168" name="weekly_hours_target" value="{{ old('weekly_hours_target') }}" placeholder="e.g. 30">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Pay Cycle *</label>
                        <select name="pay_cycle">
                            <option value="biweekly" @selected(old('pay_cycle', 'biweekly') === 'biweekly')>Bi-weekly (every 2 weeks)</option>
                            <option value="monthly"  @selected(old('pay_cycle') === 'monthly')>Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cycle Start Anchor *</label>
                        <input type="date" name="pay_cycle_anchor" value="{{ old('pay_cycle_anchor', now()->startOfWeek()->toDateString()) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    var sel = document.getElementById('create-comp-model');
    if (!sel) return;
    function apply() {
        var isHourly = sel.value === 'hourly';
        document.querySelectorAll('.create-pay-per-round').forEach(function (el) { el.style.display = isHourly ? 'none' : ''; });
        document.querySelectorAll('.create-pay-hourly').forEach(function (el) { el.style.display = isHourly ? '' : 'none'; });
    }
    sel.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
@endsection
