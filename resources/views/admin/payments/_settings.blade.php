<div class="pay-settings-card">
    <form method="POST" action="{{ route('admin.payments.config') }}">
        @csrf @method('PUT')
        <div class="pay-settings-head">
            <div class="pay-settings-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Payment Arrangement for {{ $client->business_name }}
                @php $cm = $client->compensation_model ?: 'per_round'; @endphp
                <span class="pay-model-pill {{ $cm === 'per_round' ? 'pay-pill-per-round' : 'pay-pill-hourly' }}">
                    {{ ['hourly' => 'Hourly', 'package' => 'Monthly Package'][$cm] ?? 'Per-Round' }}
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
                    {{-- Only offered to an owner already on it (Don Cadet), so it can't be picked for anyone else by mistake. --}}
                    @if ($client->isPackage())
                        <option value="package" selected>Monthly Package (fixed fee for N rounds + per extra round)</option>
                    @endif
                </select>
            </div>

            @if ($client->isPackage())
            <div class="pay-field-package">
                <label>Monthly Package Fee ($)</label>
                <input type="number" step="0.01" min="0" name="package_fee" value="{{ old('package_fee', $client->package_fee) }}" placeholder="500.00">
            </div>

            <div class="pay-field-package">
                <label>Rounds Included per Month</label>
                <input type="number" min="0" name="package_rounds" value="{{ old('package_rounds', $client->package_rounds) }}" placeholder="70">
            </div>

            <div class="pay-field-package">
                <label>Each Extra Round ($)</label>
                <input type="number" step="0.01" min="0" name="package_overage_fee" value="{{ old('package_overage_fee', $client->package_overage_fee) }}" placeholder="7.00">
            </div>
            @endif

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

            <div class="pay-field-hourly pay-field-package">
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
        var m = sel.value;
        function show(cls, on) {
            document.querySelectorAll(cls).forEach(function (el) { el.style.display = on ? '' : 'none'; });
        }
        show('.pay-field-per-round', m === 'per_round');
        show('.pay-field-hourly', m === 'hourly');
        // Last, so the shared Cycle Start Anchor field shows for the package too.
        if (m === 'package') show('.pay-field-package', true); else show('.pay-field-package:not(.pay-field-hourly)', false);
    }
    sel.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
