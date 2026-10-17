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

    /* ---- Add-client owner confirmation (big on purpose: it must be read) ---- */
    .oc-overlay { position:fixed; inset:0; z-index:10000; display:none; align-items:center; justify-content:center;
                  padding:16px; background:rgba(15,23,42,.62); backdrop-filter:blur(3px); }
    .oc-overlay.open { display:flex; }
    .oc-card { width:100%; max-width:560px; background:#fff; border-radius:20px; overflow:hidden;
               box-shadow:0 30px 80px rgba(15,23,42,.45); animation:ocIn .18s ease-out; font-family:inherit; }
    @keyframes ocIn { from { transform:translateY(12px) scale(.97); opacity:0; } to { transform:none; opacity:1; } }
    .oc-head { padding:16px 26px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff;
               font-size:13px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    .oc-body { padding:26px 26px 8px; text-align:center; color:#0f172a; }
    .oc-lead { font-size:15px; color:#475569; margin:0 0 6px; }
    .oc-client { font-size:26px; font-weight:800; margin:0 0 14px; word-break:break-word; }
    .oc-arrow { font-size:15px; color:#475569; margin:0 0 10px; }
    .oc-owner { display:inline-block; max-width:100%; padding:14px 26px; border-radius:14px; background:#eef2ff;
                border:3px solid #4f46e5; color:#1e1b4b; font-size:32px; font-weight:900; line-height:1.15; word-break:break-word; }
    .oc-q { margin:20px 0 4px; font-size:18px; font-weight:700; color:#0f172a; }
    .oc-warn { display:none; margin:16px 0 0; padding:12px 14px; border-radius:12px; text-align:left;
               background:#fffbeb; border:2px solid #f59e0b; color:#92400e; font-size:14px; font-weight:600; line-height:1.45; }
    .oc-warn.show { display:block; }
    .oc-actions { display:flex; gap:12px; padding:22px 26px 26px; }
    .oc-actions button { flex:1; padding:15px 16px; border-radius:12px; font-size:16px; font-weight:800; cursor:pointer; }
    .oc-cancel { background:#f1f5f9; border:2px solid #cbd5e1; color:#0f172a; }
    .oc-cancel:hover { background:#e2e8f0; }
    .oc-ok { background:#16a34a; border:2px solid #15803d; color:#fff; }
    .oc-ok:hover { background:#15803d; }
    @media (max-width:520px) { .oc-owner { font-size:24px; } .oc-client { font-size:21px; } .oc-actions { flex-direction:column-reverse; } }
</style>

<div class="oc-overlay" id="ownerConfirm" role="dialog" aria-modal="true" aria-labelledby="ocTitle">
    <div class="oc-card">
        <div class="oc-head" id="ocTitle">Confirm business owner</div>
        <div class="oc-body">
            <p class="oc-lead">You are adding</p>
            <p class="oc-client" id="ocClient"></p>
            <p class="oc-arrow">to business owner</p>
            <div class="oc-owner" id="ocOwner"></div>
            <p class="oc-q">Is it confirmed?</p>
            <div class="oc-warn" id="ocWarn"></div>
        </div>
        <div class="oc-actions">
            <button type="button" class="oc-cancel" id="ocCancel">Cancel</button>
            <button type="button" class="oc-ok" id="ocOk"></button>
        </div>
    </div>
</div>

<script>
    // Before saving, ask: Add "<Client Name>" to "<Business Owner>"? Is it confirmed?
    // Big and impossible to skim past; repeats any wrong-owner warning.
    (function () {
        var form = document.getElementById('addClientForm');
        var box  = document.getElementById('ownerConfirm');
        if (!form || !box) return;
        var owner = form.getAttribute('data-owner-name');
        var confirmed = false;

        function close() { box.classList.remove('open'); }

        form.addEventListener('submit', function (e) {
            if (confirmed) return;            // approved — let it through
            e.preventDefault();
            var first = (form.querySelector('[name="first_name"]').value || '').trim();
            var last  = (form.querySelector('[name="last_name"]').value || '').trim();
            document.getElementById('ocClient').textContent = (first + ' ' + last).trim() || 'this client';
            document.getElementById('ocOwner').textContent  = owner;
            document.getElementById('ocOk').textContent     = 'Yes, add to ' + owner;
            var warns = Array.prototype.map.call(form.querySelectorAll('.field-dup-error.is-warn'), function (el) {
                return el.style.display === 'none' ? '' : el.textContent;
            }).filter(Boolean);
            var w = document.getElementById('ocWarn');
            w.textContent = warns.join('\n');
            w.style.whiteSpace = 'pre-line';
            w.classList.toggle('show', warns.length > 0);
            box.classList.add('open');
            document.getElementById('ocCancel').focus();
        });

        document.getElementById('ocCancel').addEventListener('click', close);
        box.addEventListener('click', function (e) { if (e.target === box) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        document.getElementById('ocOk').addEventListener('click', function () {
            confirmed = true;
            this.disabled = true;
            this.textContent = 'Adding…';
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    })();
</script>
@endsection
