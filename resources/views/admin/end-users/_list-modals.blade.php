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
                        @for ($r = 1; $r <= count(\App\Models\EndUser::ROUND_OPTIONS); $r++)
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


{{-- ===== Round picker (proper popup, replaces the inline multi-select) =====
     Toggles which rounds a client has reached. Saves to the same update
     endpoint as the client page, so the list, the header, and the overview
     always stay in sync. Supports all 15 rounds. --}}
<div id="roundPickerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Rounds — <span id="rpName">client</span></h3>
            <button type="button" class="modal-close" onclick="closeModal('roundPickerModal')">&times;</button>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            Turn on each round this client has reached. A round's start date fills in
            from its first logged step (or set it on the client's page). Changes here
            show everywhere for this client.
        </p>
        <div class="rp-grid">
            @foreach (\App\Models\EndUser::ROUND_OPTIONS as $i => $rpLabel)
                <button type="button" class="rp-pill" data-round="{{ $rpLabel }}" data-n="{{ $i + 1 }}">
                    <span class="rp-check">✓</span> R{{ $i + 1 }}
                </button>
            @endforeach
        </div>
        <div class="form-actions" style="margin-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('roundPickerModal')">Cancel</button>
            <button type="button" class="btn btn-primary" id="rpSave">Save</button>
        </div>
    </div>
</div>

@push('head')
<style>
    #roundPickerModal .rp-grid { display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; }
    #roundPickerModal .rp-pill {
        display:inline-flex; align-items:center; justify-content:center; gap:5px;
        padding:9px 6px; border-radius:10px; cursor:pointer; font-size:13px; font-weight:700;
        background:var(--surface-2); color:var(--muted); border:1.5px solid var(--border);
        transition:background .12s, color .12s, border-color .12s;
    }
    #roundPickerModal .rp-pill .rp-check { opacity:0; font-size:11px; transition:opacity .12s; }
    #roundPickerModal .rp-pill.on {
        background:#eef2ff; color:#4338ca; border-color:#c7d2fe;
    }
    #roundPickerModal .rp-pill.on .rp-check { opacity:1; }
    #roundPickerModal .rp-pill:hover { border-color:#4f46e5; }
    @media (max-width:520px){ #roundPickerModal .rp-grid { grid-template-columns:repeat(3, 1fr); } }
</style>
@endpush
