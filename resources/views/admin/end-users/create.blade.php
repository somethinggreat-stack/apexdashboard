@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Add Client')
@section('subtitle', "New client for {$selectedClient->business_name}.")

@section('content')
<div class="card" style="max-width:960px;">
    <div class="card-header">
        <h2 style="margin:0;">Add Client — {{ $selectedClient->business_name }}</h2>
    </div>

    @if ($errors->any())
        <div class="alert alert-error" style="margin:14px 18px;">
            <strong>Please fix the issues below:</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <div style="margin-top:8px; font-size:12px;">Your text fields were preserved. <strong>The Collage file needs to be re-attached.</strong></div>
        </div>
    @endif

    {{-- The owner this client goes to — impossible to miss. --}}
    <div class="add-owner-banner">
        <span class="add-owner-label">Adding to</span>
        <span class="add-owner-name">{{ $selectedClient->business_name }}</span>
        <span class="add-owner-hint">Wrong business owner? Cancel and use “Switch Business Owner” first.</span>
    </div>

    <form method="POST" action="{{ route('admin.end-users.store') }}" enctype="multipart/form-data" style="padding:4px 18px 18px;"
          id="addClientForm"
          data-confirm-action
          data-confirm-title="Confirm business owner"
          data-confirm-ok="Yes, add client"
          data-confirm-message="Add this client to {{ $selectedClient->business_name }}? Is it confirmed?"
          data-owner-name="{{ $selectedClient->business_name }}">
        @csrf
        {{-- Saves to THIS owner even if "Working on" is switched in another tab. --}}
        <input type="hidden" name="client_id" value="{{ $selectedClient->id }}">
        <div class="form-section">
            <h4>Business Owner &amp; Status</h4>
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
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100"
                           data-dup-check="{{ route('admin.end-users.dup-check') }}" data-dup-field="name"
                           data-dup-with="first_name" data-dup-owner="{{ $selectedClient->id }}" autocomplete="off">
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
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                           data-dup-check="{{ route('admin.end-users.dup-check') }}" data-dup-field="email"
                           data-dup-owner="{{ $selectedClient->id }}" autocomplete="off">
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
                    <input type="text" name="date_of_birth" value="{{ old('date_of_birth') }}" required
                           placeholder="MM/DD/YYYY" inputmode="numeric" autocomplete="off">
                    @error('date_of_birth')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>Social Security Number *</label>
                    <input type="text" name="ssn" value="{{ old('ssn') }}" required placeholder="9 digits, no dashes" autocomplete="off"
                           data-dup-check="{{ route('admin.end-users.dup-check') }}" data-dup-field="ssn" data-dup-digits="9"
                           data-dup-owner="{{ $selectedClient->id }}">
                    @error('ssn')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <h4>Current Address</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>Current Address *</label>
                    <input type="text" name="current_address" value="{{ old('current_address') }}" required maxlength="255" placeholder="Street address">
                    @error('current_address')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" value="{{ old('city') }}" required maxlength="120">
                    @error('city')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>State *</label>
                    <input type="text" name="state" value="{{ old('state') }}" required maxlength="120">
                    @error('state')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>Zipcode *</label>
                    <input type="text" name="zipcode" value="{{ old('zipcode') }}" required maxlength="20">
                    @error('zipcode')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <h4>Identity Document</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>Collage <span class="muted">(optional)</span></label>
                    <input type="file" name="collage" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="muted small">A single file (image or PDF) containing the Photo ID, Proof of Address and SSN.</div>
                    @error('collage')<small class="field-error">{{ $message }}</small>@enderror
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
            <h4>Universal CFPB Logins <span class="muted">(optional)</span></h4>
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

        @if ($selectedClient->resultsTrackingEnabled())
            @include('admin.end-users.partials.negative-items-entry')
        @endif

        <div class="form-actions" style="margin-top:8px;">
            <a href="{{ url()->previous() }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Client</button>
        </div>
    </form>
</div>

<style>
    .add-owner-banner {
        display:flex; flex-wrap:wrap; align-items:center; gap:6px 12px;
        margin:14px 18px 6px; padding:14px 18px; border-radius:12px;
        background:#eef2ff; border:2px solid #6366f1;
    }
    .add-owner-label { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#4338ca; }
    .add-owner-name  { font-size:20px; font-weight:800; color:#1e1b4b; }
    .add-owner-hint  { flex-basis:100%; font-size:12.5px; color:#4b5563; }
</style>
<script>
    // "Add <Client Name> to <Business Owner>? Is it confirmed?" — kept current as
    // the VA types, and repeats any wrong-owner warning so it can't be missed.
    (function () {
        var form = document.getElementById('addClientForm');
        if (!form) return;
        var owner = form.getAttribute('data-owner-name');
        function refresh() {
            var first = (form.querySelector('[name="first_name"]').value || '').trim();
            var last  = (form.querySelector('[name="last_name"]').value || '').trim();
            var name  = (first + ' ' + last).trim() || 'this client';
            var msg   = 'Add "' + name + '" to "' + owner + '"? Is it confirmed?';
            var warns = Array.prototype.map.call(form.querySelectorAll('.field-dup-error.is-warn'), function (el) {
                return el.style.display === 'none' ? '' : el.textContent;
            }).filter(Boolean);
            if (warns.length) msg += '\n\n' + warns.join('\n');
            form.setAttribute('data-confirm-message', msg);
        }
        form.addEventListener('input', refresh);
        form.addEventListener('focusout', function () { setTimeout(refresh, 600); });
        // Capture phase on the button runs before the document-level confirm handler.
        var btn = form.querySelector('[type="submit"]');
        if (btn) btn.addEventListener('click', refresh, true);
        refresh();
    })();
</script>
@endsection
