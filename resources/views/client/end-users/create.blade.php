@extends('layouts.client')

@section('title', 'Add New Client')

@section('content')
<div class="card">
    <div class="card-header">
        <h2 style="margin:0;">Add New Client</h2>
        <a href="{{ route('client.new-clients') }}" class="btn btn-secondary">← Back</a>
    </div>
    <p class="muted" style="margin:8px 0 16px; font-size:13px;">
        This client will appear in <strong>New Clients</strong> and move into <strong>In Progress</strong> once our team reviews it.
    </p>

    <form method="POST" action="{{ route('client.end-users.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group" style="max-width:260px;">
            <label>Start Date *</label>
            <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
        </div>

        <h3 class="nc-section">Personal Information</h3>
        <div class="form-row">
            <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="{{ old('first_name') }}" required></div>
            <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="{{ old('last_name') }}" required></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Suffix *</label>
                <select name="suffix" required>
                    @foreach (['None','Jr.','Sr.','I','II','III','IV','V'] as $s)
                        <option value="{{ $s }}" @selected(old('suffix','None') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Email Address *</label><input type="email" name="email" value="{{ old('email') }}" required data-dup-check="{{ route('client.end-users.dup-check') }}" data-dup-field="email" autocomplete="off"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Phone Number *</label><input type="text" name="phone" value="{{ old('phone') }}" required></div>
            <div class="form-group"><label>Date of Birth *</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required></div>
        </div>
        <div class="form-group" style="max-width:320px;">
            <label>Social Security Number *</label>
            <input type="text" name="ssn" placeholder="9 digits, no dashes" required
                   data-dup-check="{{ route('client.end-users.dup-check') }}" data-dup-field="ssn" data-dup-digits="9">
        </div>

        <h3 class="nc-section">Current Address</h3>
        <div class="form-group"><label>Current Address *</label><input type="text" name="current_address" value="{{ old('current_address') }}" placeholder="Street address" required></div>
        <div class="form-row">
            <div class="form-group"><label>City *</label><input type="text" name="city" value="{{ old('city') }}" required></div>
            <div class="form-group"><label>State *</label><input type="text" name="state" value="{{ old('state') }}" required></div>
            <div class="form-group"><label>Zipcode *</label><input type="text" name="zipcode" value="{{ old('zipcode') }}" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Proof of Address *</label><input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
            <div class="form-group"><label>Driver's License *</label><input type="file" name="drivers_license" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
        </div>
        <div class="form-group"><label>Social Security Card *</label><input type="file" name="ssn_card" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>

        <h3 class="nc-section">Credit Monitoring</h3>
        <div class="form-group"><label>Service Name *</label><input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name') }}" placeholder="e.g. IdentityIQ, SmartCredit" required></div>
        <div class="form-row">
            <div class="form-group"><label>Username / Email *</label><input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username') }}" required></div>
            <div class="form-group"><label>Password *</label><input type="text" name="credit_monitoring_password" required></div>
        </div>
        <div class="form-group"><label>Security Question Answer <span class="muted">(optional)</span></label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}"></div>

        <div class="form-actions" style="margin-top:18px;">
            <a href="{{ route('client.new-clients') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Submit Client</button>
        </div>
    </form>
</div>

@push('head')
<style>
    .nc-section { font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:#2563eb; font-weight:700; margin:22px 0 12px; border-bottom:1px solid #e2e8f0; padding-bottom:6px; }
</style>
@endpush
@endsection
