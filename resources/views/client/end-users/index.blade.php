@extends('layouts.client')

@section('title', 'My Clients')

@php
    $maxDob = now()->subDay()->toDateString();
    $hasErrors = $errors->any();
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <h2>My Credit Repair Clients</h2>
        <button class="btn btn-primary" onclick="openModal('createEndUserModal')">+ Add Client</button>
    </div>
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['active','paused','graduated','cancelled'] as $s)
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
                <th>Steps Logged</th>
                <th>Days Active</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td>
                        {{ $eu->full_name }}
                        @if ($eu->is_incomplete)
                            <span class="pill pill-incomplete" title="{{ $eu->incomplete_reason }}">Incomplete</span>
                        @endif
                    </td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}</td>
                    <td>{{ $eu->process_steps_count }}</td>
                    <td>{{ $eu->days_active }}</td>
                    <td><span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span></td>
                    <td><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No clients yet.</td></tr>
            @endforelse
        </tbody>
    </table>
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
                @if (old('first_name'))
                    <div style="margin-top:8px; font-size:12px;">
                        Your text fields were preserved. <strong>Files (Photo ID / Proof of Address / SSN Picture) need to be re-attached.</strong>
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('client.end-users.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-section">
                <h4>Status</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
                        @error('start_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group"></div>
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
        // Auto-reopen the modal when the server flashed validation errors,
        // and scroll the error block into view.
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
</style>
@endpush
@endsection
