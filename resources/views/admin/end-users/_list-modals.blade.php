{{--
    Modals shared by the In Progress / Clients list — used by both the original
    view and the super-admin pro console, so the forms only exist in one place.
    Requires: $selectedClient, $errors.
--}}
@php $hasErrors = $errors->any(); @endphp

{{-- Quick-log step modal (opened from the Incomplete flag) --}}
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
                        @for ($r = 1; $r <= 8; $r++)
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

{{-- Quick-note modal --}}
<div id="quickNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Comment on <span id="quickNoteName">client</span></h3>
            <button class="modal-close" onclick="closeModal('quickNoteModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.notes.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" id="quickNoteEndUserId">
            <div class="form-group">
                <label>Comment</label>
                <textarea name="note_text" rows="4" required placeholder="Quick note about this client&hellip;"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('quickNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Comment</button>
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
                    Your text fields were preserved. <strong>The Collage file needs to be re-attached.</strong>
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
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                               data-dup-check="{{ route('admin.end-users.dup-check') }}" data-dup-field="email" autocomplete="off">
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
                               data-dup-check="{{ route('admin.end-users.dup-check') }}" data-dup-field="ssn" data-dup-digits="9">
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

{{-- Move a Clients-list client to Round Errors (asks for a type + reason) --}}
<div id="roundErrorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Move to Round Errors</h3>
            <button class="modal-close" onclick="closeModal('roundErrorModal')">&times;</button>
        </div>
        <form method="POST" id="roundErrorForm" action="">
            @csrf
            <p class="muted" id="roundErrorWho" style="margin:0 0 12px; font-size:13px;"></p>
            <div class="form-group">
                <label>Error Type *</label>
                <input type="text" name="error_type" maxlength="120" required
                       placeholder="e.g. Import failed, Login not working, Score not updated">
            </div>
            <div class="form-group">
                <label>Reason</label>
                <textarea name="reason" rows="3" maxlength="1000"
                          placeholder="What happened / what needs fixing (shown on their line)"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('roundErrorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Move to Round Errors</button>
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
