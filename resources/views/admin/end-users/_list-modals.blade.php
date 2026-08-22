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


{{-- Move a Clients-list client to Round Errors (asks for a type + reason) --}}
{{-- Reason prompt for Hold/Pause and Move to New Clients --}}
<div id="moveReasonModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="moveReasonTitle">Reason</h3>
            <button class="modal-close" onclick="closeModal('moveReasonModal')">&times;</button>
        </div>
        <form method="POST" id="moveReasonForm" action="">
            @csrf
            <p class="muted" id="moveReasonWho" style="margin:0 0 12px; font-size:13px;"></p>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" id="moveReasonText" rows="3" maxlength="1000" required
                          placeholder="Why is this client being moved? (shown on the list)"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('moveReasonModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="moveReasonSubmit">Move</button>
            </div>
        </form>
    </div>
</div>

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

