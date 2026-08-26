@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Business Clients')

@section('content')
@php
    $boPalette = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#ef4444','#3b82f6','#22c55e'];
    $boAccent  = fn ($name) => $boPalette[abs(crc32($name)) % count($boPalette)];
    $boInitials = function ($name) {
        $p = preg_split('/\s+/', trim($name));
        $a = mb_substr($p[0] ?? '', 0, 1);
        $b = count($p) > 1 ? mb_substr(end($p), 0, 1) : mb_substr($p[0] ?? '', 1, 1);
        return mb_strtoupper($a . $b);
    };
@endphp

<div class="card bo-wrap">
    <div class="card-header bo-head">
        <div class="bo-head-title">
            <h2 style="margin:0;">Business Owners</h2>
            <span class="bo-count">{{ $clients->count() }}</span>
        </div>
        <button class="btn btn-primary" onclick="openModal('createClientModal')">+ Add Business Owner</button>
    </div>

    @if ($clients->isEmpty())
        <div class="empty" style="padding:36px 0; text-align:center;">No business owners yet — add one to get started.</div>
    @else
        <div class="bo-grid">
            @foreach ($clients as $client)
                @php $cycle = $client->roundCycleDays(); @endphp
                <div class="bo-card">
                    <div class="bo-card-top">
                        <span class="bo-avatar" style="background:{{ $boAccent($client->business_name) }};">{{ $boInitials($client->business_name) }}</span>
                        <div class="bo-id">
                            <div class="bo-name-row">
                                <span class="bo-name">{{ $client->business_name }}</span>
                                <span class="bo-cycle bo-cycle-{{ $cycle }}">{{ $cycle }}-Day</span>
                            </div>
                            <span class="bo-email" title="{{ $client->email }}">{{ $client->email }}</span>
                        </div>
                        <span class="pill pill-{{ $client->status }} bo-status">{{ ucfirst($client->status) }}</span>
                    </div>

                    <div class="bo-meta">
                        <span class="bo-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                            <strong>{{ $client->end_users_count }}</strong> {{ $client->end_users_count === 1 ? 'client' : 'clients' }}
                        </span>
                        <span class="bo-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            {{ $client->phone ?: '—' }}
                        </span>
                    </div>

                    <div class="bo-actions">
                        <a href="{{ route('admin.clients.edit', $client) }}" class="bo-btn bo-btn-edit">Edit</a>
                        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline;"
                              data-confirm-delete
                              data-confirm-title="Delete this business owner?"
                              data-confirm-message="{{ $client->business_name }} and ALL of their clients will be moved to the Recycle Bin. Type the business name below to confirm."
                              data-confirm-name="{{ $client->business_name }}"
                              data-confirm-ok="Delete business owner">
                            @csrf @method('DELETE')
                            <button class="bo-btn bo-btn-del">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('head')
<style>
    .bo-head { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .bo-head-title { display:flex; align-items:center; gap:10px; }
    .bo-count { font-size:12.5px; font-weight:800; color:var(--tint-indigo-fg); background:var(--tint-indigo-bg); padding:2px 11px; border-radius:999px; }
    .bo-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(330px, 1fr)); gap:14px; padding:6px 2px 2px; }
    .bo-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:15px 16px; display:flex; flex-direction:column; gap:13px; transition:border-color .12s, box-shadow .12s, transform .12s; }
    .bo-card:hover { border-color:var(--tint-indigo-fg); box-shadow:var(--shadow); transform:translateY(-1px); }
    .bo-card-top { display:flex; align-items:flex-start; gap:12px; }
    .bo-avatar { flex:0 0 auto; width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; letter-spacing:.3px; }
    .bo-id { flex:1 1 auto; min-width:0; }
    .bo-name-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .bo-name { font-size:15px; font-weight:700; color:var(--text); }
    .bo-cycle { font-size:10.5px; font-weight:800; letter-spacing:.03em; padding:2px 8px; border-radius:999px; text-transform:uppercase; }
    .bo-cycle-30 { background:var(--tint-blue-bg);  color:var(--tint-blue-fg); }
    .bo-cycle-20 { background:var(--tint-amber-bg); color:var(--tint-amber-fg); }
    .bo-email { display:block; font-size:12.5px; color:var(--muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bo-status { flex:0 0 auto; }
    .bo-meta { display:flex; flex-wrap:wrap; gap:8px; }
    .bo-chip { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--text-soft); background:var(--surface-2); border:1px solid var(--border); border-radius:9px; padding:5px 10px; }
    .bo-chip strong { color:var(--text); }
    .bo-chip svg { width:14px; height:14px; color:var(--muted); }
    .bo-actions { display:flex; gap:8px; margin-top:auto; }
    .bo-btn { font-size:13px; font-weight:700; border-radius:9px; padding:7px 16px; cursor:pointer; text-decoration:none; border:1px solid var(--border); transition:background .12s, border-color .12s; }
    .bo-btn-edit { color:var(--tint-indigo-fg); background:var(--tint-indigo-bg); border-color:transparent; }
    .bo-btn-edit:hover { filter:brightness(.97); }
    .bo-btn-del { color:var(--tint-red-fg); background:var(--tint-red-bg); border-color:transparent; }
    .bo-btn-del:hover { filter:brightness(.97); }
    @media (max-width:560px){ .bo-grid { grid-template-columns:1fr; } }
</style>
@endpush

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
                <label>Round Timeline *</label>
                <select name="round_cycle_days" required>
                    <option value="30" @selected(old('round_cycle_days', '30') == 30)>30-Day Rounds (standard)</option>
                    <option value="20" @selected(old('round_cycle_days') == 20)>20-Day Rounds (accelerated)</option>
                </select>
                <small class="muted" style="display:block; margin-top:4px;">How long each dispute round runs for this owner's clients. All the same steps, paced to fit the window — drives next-round dates, days-left and step reminders.</small>
            </div>

            @include('admin.clients._referral-fields')

            <div class="form-section" style="margin-top:18px; padding:16px 18px; background:var(--surface-2); border:1px solid var(--border); border-radius:12px;">
                <h4 style="margin-top:0;">Payment Arrangement *</h4>
                <p class="muted" style="margin:0 0 12px; font-size:12.5px;">How does this BO pay you for the work on their clients?</p>

                <div class="form-group">
                    <label>Payment Model *</label>
                    <select name="compensation_model" id="modal-comp-model" required>
                        <option value="per_round" @selected(old('compensation_model', 'per_round') === 'per_round')>Per Round (flat fee per client per round)</option>
                        <option value="hourly"    @selected(old('compensation_model') === 'hourly')>Hourly (rate × hours worked, paid in periods)</option>
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
