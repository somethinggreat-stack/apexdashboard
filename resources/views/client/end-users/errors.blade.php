@extends('layouts.client')

@section('title', 'New Client Errors')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">New Client Errors <span class="err-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                New clients our team pulled out because something needs fixing (bad login, security question, billing, etc.).
                Many of these you can fix yourself — click <strong>Resolve Error</strong> and update the credit-monitoring login.
                Once saved it moves to <strong>Errors Resolved by You for New Clients</strong> and our team takes it from there.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Error</th>
                <th>Email</th>
                <th>Phone</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td class="err-note">{{ $eu->intake_review_note ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="no-link" style="text-align:right; white-space:nowrap;">
                        <a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a>
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick="openResolve(this)"
                                data-action="{{ route('client.errors.resolve', $eu->id) }}"
                                data-name="{{ $eu->full_name }}"
                                data-cm-name="{{ $eu->credit_monitoring_name }}"
                                data-cm-username="{{ $eu->credit_monitoring_username }}"
                                data-cm-question="{{ $eu->credit_monitoring_security_question }}">
                            Resolve Error
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No errors — all your clients are in good standing.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

{{-- Resolve modal — edit the credit-monitoring login only. --}}
<div id="resolveErrorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="resolveTitle">Resolve Error</h3>
            <button type="button" class="modal-close" onclick="closeModal('resolveErrorModal')">&times;</button>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            Update the credit-monitoring login below. Every field is optional — leave one blank to keep the current value.
            Saving sends this client to our team to continue their setup.
        </p>
        <form method="POST" id="resolveForm" action="">
            @csrf @method('PUT')
            <div class="form-group"><label>Service Name <span class="muted">(optional)</span></label><input type="text" name="credit_monitoring_name" id="rf_name" maxlength="100"></div>
            <div class="form-group"><label>Username / Login Email <span class="muted">(optional)</span></label><input type="text" name="credit_monitoring_username" id="rf_username" maxlength="255" autocomplete="off"></div>
            <div class="form-group"><label>Password <span class="muted">(leave blank to keep current)</span></label><input type="text" name="credit_monitoring_password" autocomplete="off"></div>
            <div class="form-group"><label>Security Question <span class="muted">(leave blank to keep current)</span></label><input type="text" name="credit_monitoring_security_question" id="rf_question" maxlength="255"></div>
            <div class="form-group"><label>Security Answer <span class="muted">(leave blank to keep current)</span></label><input type="text" name="credit_monitoring_security_answer" autocomplete="off"></div>
            <div class="form-group"><label>PIN <span class="muted">(4 digits — leave blank to keep current)</span></label><input type="text" name="credit_monitoring_pin" inputmode="numeric" maxlength="4" autocomplete="off"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resolveErrorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save &amp; Send to Team</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .err-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:13px; font-weight:700; vertical-align:middle; }
    .err-note { max-width:360px; color:#b91c1c; font-weight:600; font-size:13px; white-space:pre-wrap; word-break:break-word; }
</style>
@endpush
@push('scripts')
<script>
function openResolve(btn) {
    var f = document.getElementById('resolveForm');
    f.action = btn.getAttribute('data-action');
    document.getElementById('resolveTitle').textContent = 'Resolve Error — ' + (btn.getAttribute('data-name') || '');
    document.getElementById('rf_name').value = btn.getAttribute('data-cm-name') || '';
    document.getElementById('rf_username').value = btn.getAttribute('data-cm-username') || '';
    document.getElementById('rf_question').value = btn.getAttribute('data-cm-question') || '';
    // Secrets always start blank (leave blank to keep).
    f.querySelector('[name="credit_monitoring_password"]').value = '';
    f.querySelector('[name="credit_monitoring_security_answer"]').value = '';
    f.querySelector('[name="credit_monitoring_pin"]').value = '';
    openModal('resolveErrorModal');
}
window.openResolve = openResolve;
</script>
@endpush
@endsection
