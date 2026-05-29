<div class="pay-settings-card">
    <form method="POST" action="{{ route('admin.payments.config') }}">
        @csrf @method('PUT')
        <div class="pay-settings-head">
            <div class="pay-settings-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Payment Arrangement for {{ $client->business_name }}
                <span class="pay-model-pill {{ ($client->compensation_model ?? 'per_round') === 'hourly' ? 'pay-pill-hourly' : 'pay-pill-per-round' }}">
                    {{ ($client->compensation_model ?? 'per_round') === 'hourly' ? 'Hourly' : 'Per-Round' }}
                </span>
            </div>
            <button type="submit" class="pay-btn-primary">Save Settings</button>
        </div>

        <div class="pay-settings-grid">
            <div>
                <label>Payment Model</label>
                <select name="compensation_model" id="pay-model-select">
                    <option value="per_round" @selected(($client->compensation_model ?? 'per_round') === 'per_round')>Per Round (flat fee per client per round)</option>
                    <option value="hourly"    @selected(($client->compensation_model ?? '') === 'hourly')>Hourly (rate × hours worked)</option>
                </select>
            </div>

            <div class="pay-field-per-round">
                <label>Fee per Round ($)</label>
                <input type="number" step="0.01" min="0" name="per_round_fee" value="{{ old('per_round_fee', $client->per_round_fee) }}" placeholder="12.00">
            </div>

            <div class="pay-field-hourly">
                <label>Hourly Rate ($)</label>
                <input type="number" step="0.01" min="0" name="hourly_rate" value="{{ old('hourly_rate', $client->hourly_rate) }}" placeholder="5.00">
            </div>

            <div class="pay-field-hourly">
                <label>Weekly Hours Target</label>
                <input type="number" min="0" max="168" name="weekly_hours_target" value="{{ old('weekly_hours_target', $client->weekly_hours_target) }}" placeholder="30">
            </div>

            <div class="pay-field-hourly">
                <label>Pay Cycle</label>
                <select name="pay_cycle">
                    <option value="biweekly" @selected(($client->pay_cycle ?? 'biweekly') === 'biweekly')>Bi-weekly (every 2 weeks)</option>
                    <option value="monthly"  @selected(($client->pay_cycle ?? '') === 'monthly')>Monthly</option>
                </select>
            </div>

            <div class="pay-field-hourly">
                <label>Cycle Start Anchor</label>
                <input type="date" name="pay_cycle_anchor" value="{{ old('pay_cycle_anchor', optional($client->pay_cycle_anchor)->toDateString()) }}">
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    var sel = document.getElementById('pay-model-select');
    if (!sel) return;
    function apply() {
        var isHourly = sel.value === 'hourly';
        document.querySelectorAll('.pay-field-per-round').forEach(function (el) { el.style.display = isHourly ? 'none' : ''; });
        document.querySelectorAll('.pay-field-hourly').forEach(function (el) { el.style.display = isHourly ? '' : 'none'; });
    }
    sel.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
