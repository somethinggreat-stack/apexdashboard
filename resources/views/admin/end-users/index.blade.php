@extends('layouts.admin')

@section('title', 'Clients')

@php
    $maxDob = now()->subDay()->toDateString();
    $hasErrors = $errors->any();
    $statusOptions = ['active','paused','graduated','cancelled'];
    $roundOptions  = App\Models\EndUser::ROUND_OPTIONS;
    $weekStepCanonical = [
        1 => 'ex_tu_eq_letters_generated',
        2 => 'tu_ex_call_followups',
        3 => 'aggressive_bureau_followup',
        4 => 'pull_latest_report',
    ];
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Clients — {{ $selectedClient->business_name }}</h2>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.today-queue') }}" class="btn btn-secondary">Today's Queue</a>
            <button class="btn btn-primary" onclick="openModal('createEndUserModal')">+ Add Client</button>
        </div>
    </div>
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All statuses</option>
            @foreach ($statusOptions as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name">
        <button class="btn btn-secondary">Filter</button>
    </form>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Round</th>
                <th>Steps</th>
                <th>Days</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr class="row-link" data-href="{{ route('admin.end-users.show', $eu) }}">
                    <td>
                        <a href="{{ route('admin.end-users.show', $eu) }}" class="name-link">{{ $eu->full_name }}</a>
                        @if ($eu->is_incomplete)
                            <button type="button"
                                    class="pill pill-incomplete inline-action"
                                    title="{{ $eu->incomplete_reason }} — click to log"
                                    onclick="openQuickLog({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', {{ $eu->missing_week ?? 1 }}, {{ $eu->current_round }})">
                                Incomplete · log
                            </button>
                        @endif
                    </td>
                    <td class="no-link">{{ $eu->email }}</td>
                    <td class="no-link">
                        <span class="inline-edit inline-edit-round"
                              data-id="{{ $eu->id }}"
                              data-current="{{ json_encode($eu->rounds ?? []) }}">
                            {{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}
                            <span class="inline-pencil" aria-hidden="true">✎</span>
                        </span>
                    </td>
                    <td class="no-link">{{ $eu->process_steps_count }}</td>
                    <td class="no-link">{{ $eu->days_active }}</td>
                    <td class="no-link">
                        <span class="inline-edit inline-edit-status"
                              data-id="{{ $eu->id }}"
                              data-current="{{ $eu->status }}">
                            <span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span>
                            <span class="inline-pencil" aria-hidden="true">✎</span>
                        </span>
                    </td>
                    <td class="no-link">
                        <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                        <button type="button" class="btn btn-sm"
                                onclick="openQuickNote({{ $eu->id }}, '{{ addslashes($eu->full_name) }}')">+ Note</button>
                        <a href="{{ route('admin.end-users.status-report', $eu) }}" target="_blank" class="btn btn-sm">Report</a>
                        <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}" style="display:inline" onsubmit="return confirm('Delete client {{ $eu->full_name }} and all their documents? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Quick-log step modal (opened from Incomplete pill) --}}
<div id="quickLogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log step for <span id="quickLogName">client</span></h3>
            <button class="modal-close" onclick="closeModal('quickLogModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.process-steps.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" id="quickLogEndUserId">
            <input type="hidden" name="step_type"   id="quickLogStepType">
            <div class="form-row">
                <div class="form-group">
                    <label>Round</label>
                    <select name="round" id="quickLogRound" required>
                        @for ($r = 1; $r <= 4; $r++)
                            <option value="{{ $r }}">Round {{ $r }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group">
                    <label>Week</label>
                    <select name="week" id="quickLogWeek" required>
                        @for ($w = 1; $w <= 4; $w++)
                            <option value="{{ $w }}">Week {{ $w }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="step_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="muted small" id="quickLogTypeHint">
                A canonical step will be created for the chosen week. Open the client to add additional step types.
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('quickLogModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Step</button>
            </div>
        </form>
    </div>
</div>

{{-- Quick-note modal (opened from + Note button in row) --}}
<div id="quickNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Note on <span id="quickNoteName">client</span></h3>
            <button class="modal-close" onclick="closeModal('quickNoteModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.notes.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" id="quickNoteEndUserId">
            <div class="form-group">
                <label>Note</label>
                <textarea name="note_text" rows="4" required placeholder="Quick note about this client&hellip;"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('quickNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Note</button>
            </div>
        </form>
    </div>
</div>

<div id="createEndUserModal" class="modal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h3>Add Client</h3>
            <button class="modal-close" onclick="closeModal('createEndUserModal')">&times;</button>
        </div>

        @if ($hasErrors)
            <div class="alert alert-error" style="margin:14px 18px;">
                <strong>Please fix the issues below:</strong>
                <ul style="margin:6px 0 0 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <div style="margin-top:8px; font-size:12px;">
                    Your text fields were preserved. <strong>Files (Photo ID / Proof of Address / SSN Picture) need to be re-attached.</strong>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.end-users.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-section">
                <h4>Business Owner & Status</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Business Owner</label>
                        <input type="text" value="{{ $selectedClient->business_name }}" disabled>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
                        @error('start_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4>Personal Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100">
                        @error('first_name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100">
                        @error('last_name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Suffix *</label>
                        <select name="suffix" required>
                            @foreach (['None','Jr.','Sr.','I','II','III','IV','V'] as $opt)
                                <option value="{{ $opt }}" @selected(old('suffix', 'None') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @error('suffix')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255">
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required maxlength="30">
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ $maxDob }}" required>
                        @error('date_of_birth')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Social Security Number *</label>
                        <input type="text" name="ssn" value="{{ old('ssn') }}" required placeholder="XXX-XX-XXXX" autocomplete="off" maxlength="32">
                        @error('ssn')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4>Identity Documents</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Government-Issued Photo ID *</label>
                        <input type="file" name="photo_id" required accept=".pdf,.jpg,.jpeg,.png">
                        @error('photo_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Proof of Address *</label>
                        <input type="file" name="proof_of_address" required accept=".pdf,.jpg,.jpeg,.png">
                        @error('proof_of_address')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SSN Picture <span class="muted">(optional)</span></label>
                        <input type="file" name="ssn_picture" accept=".pdf,.jpg,.jpeg,.png">
                        @error('ssn_picture')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Credit Monitoring</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name') }}" required placeholder="e.g. IdentityIQ, SmartCredit" maxlength="100">
                        @error('credit_monitoring_name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Username / Email *</label>
                        <input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username') }}" required autocomplete="off" maxlength="255">
                        @error('credit_monitoring_username')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="text" name="credit_monitoring_password" value="{{ old('credit_monitoring_password') }}" required autocomplete="off" maxlength="255">
                        @error('credit_monitoring_password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>Security Question Answer *</label>
                        <input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}" required autocomplete="off" maxlength="255">
                        @error('credit_monitoring_security_answer')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4>CFPB <span class="muted">(optional)</span></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CFPB Login Email</label>
                        <input type="email" name="cfpb_email" value="{{ old('cfpb_email') }}" autocomplete="off" maxlength="255">
                        @error('cfpb_email')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label>CFPB Password</label>
                        <input type="text" name="cfpb_password" value="{{ old('cfpb_password') }}" autocomplete="off" maxlength="255">
                        @error('cfpb_password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createEndUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Client</button>
            </div>
        </form>
    </div>
</div>

@if ($hasErrors)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof openModal === 'function') {
                openModal('createEndUserModal');
                var alert = document.querySelector('#createEndUserModal .alert-error');
                if (alert) alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
    @endpush
@endif

@push('head')
<style>
    .field-error { display:block; color:#dc2626; font-size:12px; margin-top:4px; }
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }

    .inline-edit { cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .inline-edit .inline-pencil {
        opacity:0; transition:opacity .15s; font-size:11px; color:#64748b;
    }
    .inline-edit:hover .inline-pencil { opacity:1; }
    .inline-edit.editing { display:inline-flex; gap:4px; }
    .inline-edit select { font-size:12px; padding:2px 6px; min-width:120px; }
    .inline-edit .inline-save  { font-size:11px; padding:2px 8px; cursor:pointer; background:#16a34a; color:white; border:0; border-radius:4px; }
    .inline-edit .inline-cancel { font-size:11px; padding:2px 8px; cursor:pointer; background:#e5e7eb; color:#374151; border:0; border-radius:4px; }

    .pill-incomplete.inline-action {
        cursor:pointer; border:none;
        background:#fee2e2; color:#991b1b;
        margin-left:6px; padding:2px 10px; border-radius:999px;
        font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.3px;
    }
    .pill-incomplete.inline-action:hover { background:#fecaca; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var STATUSES = @json($statusOptions);
    var ROUNDS   = @json($roundOptions);
    var WEEK_STEPS = @json($weekStepCanonical);
    var updateUrlTpl = "{{ url('admin/end-users') }}/__ID__";

    function inlineStop(e) { e.preventDefault(); e.stopPropagation(); }

    /* --------- inline status edit --------- */
    document.querySelectorAll('.inline-edit-status').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('editing')) return;
            inlineStop(e);
            var current = el.dataset.current;
            el.classList.add('editing');
            el.innerHTML =
                '<select>' + STATUSES.map(function (s) {
                    return '<option value="'+s+'"'+(s===current?' selected':'')+'>'+s+'</option>';
                }).join('') + '</select>' +
                '<button class="inline-save" type="button">Save</button>' +
                '<button class="inline-cancel" type="button">×</button>';

            var sel = el.querySelector('select');
            sel.addEventListener('click', inlineStop);
            sel.focus();

            el.querySelector('.inline-cancel').addEventListener('click', function (e2) {
                inlineStop(e2); window.location.reload();
            });
            el.querySelector('.inline-save').addEventListener('click', function (e2) {
                inlineStop(e2);
                var newStatus = sel.value;
                var fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', csrf);
                fd.append('status', newStatus);
                fetch(updateUrlTpl.replace('__ID__', el.dataset.id), {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) window.location.reload();
                    else alert('Could not save status.');
                });
            });
        });
    });

    /* --------- inline round edit --------- */
    document.querySelectorAll('.inline-edit-round').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('editing')) return;
            inlineStop(e);
            var current = [];
            try { current = JSON.parse(el.dataset.current || '[]'); } catch (_) {}
            el.classList.add('editing');
            el.innerHTML =
                '<select multiple size="3" style="min-width:140px;">' +
                ROUNDS.map(function (r) {
                    var sel = current.indexOf(r) !== -1 ? ' selected' : '';
                    return '<option value="'+r+'"'+sel+'>'+r+'</option>';
                }).join('') + '</select>' +
                '<button class="inline-save" type="button">Save</button>' +
                '<button class="inline-cancel" type="button">×</button>';

            var sel = el.querySelector('select');
            sel.addEventListener('click', inlineStop);
            sel.focus();

            el.querySelector('.inline-cancel').addEventListener('click', function (e2) {
                inlineStop(e2); window.location.reload();
            });
            el.querySelector('.inline-save').addEventListener('click', function (e2) {
                inlineStop(e2);
                var picked = Array.from(sel.selectedOptions).map(function (o) { return o.value; });
                var fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', csrf);
                fd.append('rounds_present', '1');
                picked.forEach(function (r) { fd.append('rounds[]', r); });
                fetch(updateUrlTpl.replace('__ID__', el.dataset.id), {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) window.location.reload();
                    else alert('Could not save rounds.');
                });
            });
        });
    });

    /* --------- quick-log modal --------- */
    window.openQuickLog = function (euId, name, missingWeek, currentRound) {
        document.getElementById('quickLogEndUserId').value = euId;
        document.getElementById('quickLogName').textContent = name;
        var weekSel = document.getElementById('quickLogWeek');
        var roundSel = document.getElementById('quickLogRound');
        var typeIn  = document.getElementById('quickLogStepType');
        weekSel.value = missingWeek;
        roundSel.value = Math.max(1, currentRound || 1);
        typeIn.value = WEEK_STEPS[missingWeek] || 'ex_tu_eq_letters_generated';
        weekSel.onchange = function () { typeIn.value = WEEK_STEPS[weekSel.value] || ''; };
        openModal('quickLogModal');
    };

    /* --------- quick-note modal --------- */
    window.openQuickNote = function (euId, name) {
        document.getElementById('quickNoteEndUserId').value = euId;
        document.getElementById('quickNoteName').textContent = name;
        openModal('quickNoteModal');
    };
})();
</script>
@endpush
@endsection
