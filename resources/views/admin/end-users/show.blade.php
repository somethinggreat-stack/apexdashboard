@extends('layouts.admin')

@section('title', $endUser->full_name)

@php
    $documentCategories = [
        'credit_report' => 'Credit Report',
        'dispute_letter_experian' => 'Dispute Letter — Experian',
        'dispute_letter_equifax' => 'Dispute Letter — Equifax',
        'dispute_letter_transunion' => 'Dispute Letter — TransUnion',
        'dispute_letter_innovis' => 'Dispute Letter — Innovis',
        'cfpb_complaint_experian' => 'CFPB Complaint — Experian',
        'cfpb_complaint_equifax' => 'CFPB Complaint — Equifax',
        'cfpb_complaint_transunion' => 'CFPB Complaint — TransUnion',
        'cfpb_complaint_innovis' => 'CFPB Complaint — Innovis',
        'ftc_complaint' => 'FTC Complaint',
        'bureau_response' => 'Bureau Response',
        'escalation_letter' => 'Escalation Letter',
        'call_recording' => 'Call Recording',
        'call_notes' => 'Call Notes',
        'tracking_receipt' => 'Tracking Receipt',
        'other' => 'Other',
    ];
    $rounds = App\Models\ProcessStep::rounds();
    $weeks = App\Models\ProcessStep::weeks();
    $stepTypesByWeek = App\Models\ProcessStep::stepTypesByWeek();
    $documentsByCategory = $endUser->documents->groupBy('category');
    $identityDocs = collect([
        ['type' => 'photo_id',         'label' => 'Government Photo ID', 'url' => $endUser->photo_id_url,         'path' => $endUser->photo_id_path],
        ['type' => 'proof_of_address', 'label' => 'Proof of Address',    'url' => $endUser->proof_of_address_url, 'path' => $endUser->proof_of_address_path],
        ['type' => 'ssn_picture',      'label' => 'SSN Picture',         'url' => $endUser->ssn_picture_url,      'path' => $endUser->ssn_picture_path],
    ])->filter(fn ($d) => !empty($d['path']));
    $totalDocs = $endUser->documents->count() + $identityDocs->count();
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $endUser->full_name }}</h2>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.end-users.index') }}" class="btn btn-secondary">← All Clients</a>
            <form method="POST" action="{{ route('admin.end-users.destroy', $endUser) }}"
                  onsubmit="return confirm('Delete client {{ $endUser->full_name }} and ALL their documents, notes, and process steps? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete Client</button>
            </form>
        </div>
    </div>
    <div class="info-grid">
        <div><label>Business Owner</label><div>{{ $endUser->client?->business_name }}</div></div>
        <div><label>Suffix</label><div>{{ $endUser->suffix && $endUser->suffix !== 'None' ? $endUser->suffix : '—' }}</div></div>
        <div><label>Email</label><div>{{ $endUser->email }}</div></div>
        <div><label>Phone</label><div>{{ $endUser->phone ?? '—' }}</div></div>
        <div><label>Days Active</label><div>{{ $endUser->days_active }}</div></div>
        <div><label>Total Deletions</label><div>{{ $endUser->total_deletions }}</div></div>
        <div><label>Status</label><div><span class="pill pill-{{ $endUser->status }}">{{ $endUser->status }}</span></div></div>
        <div><label>Round</label><div>{{ !empty($endUser->rounds) ? implode(', ', $endUser->rounds) : '—' }}</div></div>
    </div>
    <form method="POST" action="{{ route('admin.end-users.update', $endUser) }}" class="inline-update">
        @csrf @method('PUT')
        <label>Status</label>
        <select name="status">
            @foreach (['active','paused','graduated','cancelled'] as $s)
                <option value="{{ $s }}" @selected($endUser->status === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
</div>

<div class="tabs">
    <button class="tab active" data-target="tab-profile">Profile</button>
    <button class="tab" data-target="tab-timeline">Process Timeline ({{ $endUser->processSteps->count() }})</button>
    <button class="tab" data-target="tab-docs">All Documents ({{ $totalDocs }})</button>
    <button class="tab" data-target="tab-notes">Notes ({{ $endUser->notes->count() }})</button>
    <button class="tab" data-target="tab-status-report">Status Report</button>
</div>

<div id="tab-profile" class="tab-panel active">
    <div class="card">
        <div class="card-header">
            <h3>Profile Information</h3>
            <button class="btn btn-primary" onclick="openModal('editProfileModal')">Edit Profile</button>
        </div>

        <h4 class="profile-section-head">Personal</h4>
        <div class="info-grid">
            <div><label>First Name</label><div>{{ $endUser->first_name }}</div></div>
            <div><label>Last Name</label><div>{{ $endUser->last_name }}</div></div>
            <div><label>Suffix</label><div>{{ $endUser->suffix && $endUser->suffix !== 'None' ? $endUser->suffix : '—' }}</div></div>
            <div><label>Email Address</label><div>{{ $endUser->email }}</div></div>
            <div><label>Phone Number</label><div>{{ $endUser->phone ?? '—' }}</div></div>
            <div><label>Date of Birth</label><div>{{ $endUser->date_of_birth?->format('M d, Y') ?? '—' }}</div></div>
            <div><label>SSN</label><div>{{ $endUser->ssn ?? '—' }}</div></div>
        </div>

        <h4 class="profile-section-head">Identity Documents</h4>
        <div class="info-grid">
            <div>
                <label>Government Photo ID</label>
                <div>
                    @if ($endUser->photo_id_url)
                        <a href="{{ $endUser->photo_id_url }}" target="_blank" class="btn btn-sm">View File</a>
                    @else
                        <span class="muted">Not uploaded</span>
                    @endif
                </div>
            </div>
            <div>
                <label>Proof of Address</label>
                <div>
                    @if ($endUser->proof_of_address_url)
                        <a href="{{ $endUser->proof_of_address_url }}" target="_blank" class="btn btn-sm">View File</a>
                    @else
                        <span class="muted">Not uploaded</span>
                    @endif
                </div>
            </div>
            <div>
                <label>SSN Picture</label>
                <div>
                    @if ($endUser->ssn_picture_url)
                        <a href="{{ $endUser->ssn_picture_url }}" target="_blank" class="btn btn-sm">View File</a>
                    @else
                        <span class="muted">Not uploaded</span>
                    @endif
                </div>
            </div>
        </div>

        <h4 class="profile-section-head">Credit Monitoring</h4>
        <div class="info-grid">
            <div><label>Service Name</label><div>{{ $endUser->credit_monitoring_name ?? '—' }}</div></div>
            <div><label>Username / Email</label><div>{{ $endUser->credit_monitoring_username ?? '—' }}</div></div>
            <div>
                <label>Password</label>
                <div class="password-cell">
                    <span class="password-mask" data-secret="{{ $endUser->credit_monitoring_password }}">
                        @if ($endUser->credit_monitoring_password) ••••••••• @else — @endif
                    </span>
                    @if ($endUser->credit_monitoring_password)
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                    @endif
                </div>
            </div>
            <div>
                <label>Security Question Answer</label>
                <div class="password-cell">
                    <span class="password-mask" data-secret="{{ $endUser->credit_monitoring_security_answer }}">
                        @if ($endUser->credit_monitoring_security_answer) ••••••••• @else — @endif
                    </span>
                    @if ($endUser->credit_monitoring_security_answer)
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                    @endif
                </div>
            </div>
        </div>

        <h4 class="profile-section-head">CFPB</h4>
        <div class="info-grid">
            <div><label>CFPB Login Email</label><div>{{ $endUser->cfpb_email ?? '—' }}</div></div>
            <div>
                <label>CFPB Password</label>
                <div class="password-cell">
                    <span class="password-mask" data-secret="{{ $endUser->cfpb_password }}">
                        @if ($endUser->cfpb_password) ••••••••• @else — @endif
                    </span>
                    @if ($endUser->cfpb_password)
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div id="tab-timeline" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3>Process Timeline</h3>
            <button class="btn btn-primary" onclick="openModal('addStepModal')">+ Add Process Step</button>
        </div>
        <div class="timeline">
            @forelse ($endUser->processSteps as $step)
                <div class="timeline-item">
                    <div class="timeline-marker">R{{ $step->round }}·W{{ $step->week }}</div>
                    <div class="timeline-body">
                        <div class="timeline-head">
                            <span class="badge">Round {{ $step->round }}</span>
                            <span class="badge">Week {{ $step->week }}</span>
                            <span class="badge step-badge">{{ $step->step_type_label }}</span>
                            <span class="timeline-date">{{ $step->step_date?->format('M d, Y') }}</span>
                        </div>
                        @include('admin.end-users.partials.step-metrics', ['step' => $step])
                        @if ($step->documents->count())
                            <div class="step-docs">
                                <strong>{{ $step->documents->count() }} documents:</strong>
                                @foreach ($step->documents as $doc)
                                    <a href="{{ $doc->url }}" target="_blank" class="doc-chip" title="{{ $doc->description }}">
                                        {{ $documentCategories[$doc->category] ?? $doc->category }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <div class="step-actions">
                            <button class="btn btn-sm" onclick="openUploadModal({{ $step->id }})">+ Upload Document</button>
                            <form method="POST" action="{{ route('admin.process-steps.destroy', $step->id) }}" style="display:inline" onsubmit="return confirm('Delete this step and its documents?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty">No process steps logged yet. Click "Add Process Step" to begin Round 1, Week 1.</div>
            @endforelse
        </div>
    </div>
</div>

<div id="tab-docs" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3>All Documents</h3>
            <button class="btn btn-secondary" onclick="openUploadModal(null)">+ Single (categorised)</button>
        </div>

        <div class="dropzone" id="bulkDropzone" tabindex="0" role="button" aria-label="Bulk upload documents">
            <input type="file" id="bulkFileInput" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.mp3,.wav,.doc,.docx,.xls,.xlsx,.csv,.txt">
            <div class="dropzone-icon">&#8682;</div>
            <div class="dropzone-title">Drag &amp; drop files here</div>
            <div class="dropzone-sub">Drop any number of files at once — or click to browse. No need to pick a type; everything uploads instantly and you can categorise later if needed.</div>
            <div class="dz-list" id="bulkList"></div>
        </div>

        @if ($identityDocs->isNotEmpty())
            <h4 class="doc-cat-head">Identity Documents ({{ $identityDocs->count() }})</h4>
            <div class="doc-grid">
                @foreach ($identityDocs as $idoc)
                    @php $ext = strtoupper(pathinfo($idoc['path'], PATHINFO_EXTENSION) ?: 'FILE'); @endphp
                    <div class="doc-card">
                        <div class="doc-icon">{{ $ext }}</div>
                        <div class="doc-name" title="{{ $idoc['label'] }}">{{ $idoc['label'] }}</div>
                        <div class="doc-desc"><span class="muted">Uploaded with profile</span></div>
                        <div class="doc-actions">
                            <a href="{{ $idoc['url'] }}" target="_blank" class="btn btn-sm">Open</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($documentsByCategory->isEmpty() && $identityDocs->isEmpty())
            <div class="empty">No documents uploaded yet.</div>
        @elseif ($documentsByCategory->isNotEmpty())
            @foreach ($documentsByCategory as $cat => $docs)
                <h4 class="doc-cat-head">{{ $documentCategories[$cat] ?? $cat }} ({{ $docs->count() }})</h4>
                <div class="doc-grid">
                    @foreach ($docs as $doc)
                        <div class="doc-card">
                            <div class="doc-icon">{{ strtoupper($doc->file_type) }}</div>
                            <div class="doc-name" title="{{ $doc->file_name }}">{{ \Illuminate\Support\Str::limit($doc->file_name, 32) }}</div>
                            @if ($doc->description) <div class="doc-desc">{{ $doc->description }}</div> @endif
                            <div class="doc-date">{{ $doc->created_at?->format('M d, Y') }}</div>
                            <div class="doc-actions">
                                <a href="{{ $doc->url }}" target="_blank" class="btn btn-sm">Open</a>
                                <form method="POST" action="{{ route('admin.documents.destroy', $doc->id) }}" style="display:inline" onsubmit="return confirm('Delete this document?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">×</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</div>

<div id="tab-notes" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3>Notes</h3>
            <button class="btn btn-primary" onclick="openModal('addNoteModal')">+ Add Note</button>
        </div>
        @forelse ($endUser->notes as $note)
            <div class="note-item">
                <div class="note-meta">
                    <strong>{{ $note->createdBy?->full_name ?? 'VA' }}</strong>
                    <span class="muted">· {{ $note->created_at?->format('M d, Y H:i') }}</span>
                </div>
                <div class="note-body">{{ $note->note_text }}</div>
                <form method="POST" action="{{ route('admin.notes.destroy', $note->id) }}" onsubmit="return confirm('Delete this note?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        @empty
            <div class="empty">No notes yet.</div>
        @endforelse
    </div>
</div>

<div id="tab-status-report" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3>Status Report</h3>
            <button class="btn btn-secondary" onclick="window.print()">Print / Save PDF</button>
        </div>
        @include('partials.status-report', ['endUser' => $endUser])
    </div>
</div>

<div id="addStepModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Process Step</h3>
            <button class="modal-close" onclick="closeModal('addStepModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.process-steps.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" value="{{ $endUser->id }}">
            <div class="form-group">
                <label>Round</label>
                <select name="round" id="stepRound" required>
                    @foreach ($rounds as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Week</label>
                <select name="week" id="stepWeek" required>
                    @foreach ($weeks as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Step Type</label>
                <select name="step_type" id="stepType" required></select>
            </div>
            <div class="form-group"><label>Date</label><input type="date" name="step_date" value="{{ now()->toDateString() }}" required></div>
            <div id="w4s2-fields" class="w4s2-fields" hidden>
                <div class="bureau-block">
                    <h4>Experian</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Accounts Disputed</label><input type="number" name="experian_accounts_disputed" min="0"></div>
                        <div class="form-group"><label>Inquiries Disputed</label><input type="number" name="experian_inquiries_disputed" min="0"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>TransUnion</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Accounts Disputed</label><input type="number" name="transunion_accounts_disputed" min="0"></div>
                        <div class="form-group"><label>Inquiries Disputed</label><input type="number" name="transunion_inquiries_disputed" min="0"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>Equifax</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Accounts Disputed</label><input type="number" name="equifax_accounts_disputed" min="0"></div>
                        <div class="form-group"><label>Inquiries Disputed</label><input type="number" name="equifax_inquiries_disputed" min="0"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>Credit Scores</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Previous Credit Score</label><input type="number" name="previous_credit_score" min="300" max="850"></div>
                        <div class="form-group"><label>Credit Score Now</label><input type="number" name="credit_score_now" min="300" max="850"></div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStepModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Step</button>
            </div>
        </form>
    </div>
</div>

<div id="uploadDocModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Document</h3>
            <button class="modal-close" onclick="closeModal('uploadDocModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="end_user_id" value="{{ $endUser->id }}">
            <input type="hidden" name="process_step_id" id="uploadStepId">
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    @foreach ($documentCategories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Description (optional)</label><input type="text" name="description" placeholder="e.g. Experian dispute letter — 8 accounts"></div>
            <div class="form-group"><label>File (pdf/img/audio, max 10MB)</label><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.mp3,.wav" required></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<div id="addNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Note</h3>
            <button class="modal-close" onclick="closeModal('addNoteModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.notes.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" value="{{ $endUser->id }}">
            <div class="form-group"><label>Note</label><textarea name="note_text" rows="4" required></textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="editProfileModal" class="modal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h3>Edit Profile — {{ $endUser->full_name }}</h3>
            <button class="modal-close" onclick="closeModal('editProfileModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.end-users.update', $endUser) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-section">
                <h4>Personal Information</h4>
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="{{ old('first_name', $endUser->first_name) }}" required maxlength="100"></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $endUser->last_name) }}" required maxlength="100"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Suffix</label>
                        <select name="suffix">
                            @foreach (['None','Jr.','Sr.','I','II','III','IV','V'] as $opt)
                                <option value="{{ $opt }}" @selected(old('suffix', $endUser->suffix ?? 'None') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" value="{{ old('email', $endUser->email) }}" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="{{ old('phone', $endUser->phone) }}"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $endUser->date_of_birth?->toDateString()) }}"></div>
                    <div class="form-group"><label>SSN <span class="muted">(leave blank to keep current)</span></label><input type="text" name="ssn" placeholder="XXX-XX-XXXX" autocomplete="off"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Identity Documents</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Government-Issued Photo ID</label>
                        @if ($endUser->photo_id_url)
                            <div class="muted small"><a href="{{ $endUser->photo_id_url }}" target="_blank">Current file</a> — uploading replaces it</div>
                        @endif
                        <input type="file" name="photo_id" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group">
                        <label>Proof of Address</label>
                        @if ($endUser->proof_of_address_url)
                            <div class="muted small"><a href="{{ $endUser->proof_of_address_url }}" target="_blank">Current file</a> — uploading replaces it</div>
                        @endif
                        <input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SSN Picture</label>
                        @if ($endUser->ssn_picture_url)
                            <div class="muted small"><a href="{{ $endUser->ssn_picture_url }}" target="_blank">Current file</a> — uploading replaces it</div>
                        @endif
                        <input type="file" name="ssn_picture" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Credit Monitoring</h4>
                <div class="form-row">
                    <div class="form-group"><label>Service Name</label><input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name', $endUser->credit_monitoring_name) }}"></div>
                    <div class="form-group"><label>Username / Email</label><input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username', $endUser->credit_monitoring_username) }}" autocomplete="off"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Password <span class="muted">(leave blank to keep current)</span></label><input type="text" name="credit_monitoring_password" autocomplete="off"></div>
                    <div class="form-group"><label>Security Question Answer <span class="muted">(leave blank to keep current)</span></label><input type="text" name="credit_monitoring_security_answer" autocomplete="off"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>CFPB</h4>
                <div class="form-row">
                    <div class="form-group"><label>CFPB Login Email</label><input type="email" name="cfpb_email" value="{{ old('cfpb_email', $endUser->cfpb_email) }}" autocomplete="off"></div>
                    <div class="form-group"><label>CFPB Password <span class="muted">(leave blank to keep current)</span></label><input type="text" name="cfpb_password" autocomplete="off"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Status</h4>
                <input type="hidden" name="rounds_present" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="{{ old('start_date', $endUser->start_date?->toDateString()) }}" required></div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            @foreach (['active','paused','graduated','cancelled'] as $s)
                                <option value="{{ $s }}" @selected($endUser->status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Round <span class="muted">(hold Ctrl / Cmd to pick multiple)</span></label>
                        @php $selectedRounds = old('rounds', $endUser->rounds ?? []); @endphp
                        <select name="rounds[]" multiple size="5">
                            @foreach (\App\Models\EndUser::ROUND_OPTIONS as $round)
                                <option value="{{ $round }}" @selected(in_array($round, $selectedRounds, true))>{{ $round }}</option>
                            @endforeach
                        </select>
                        @error('rounds')<small class="field-error">{{ $message }}</small>@enderror
                        @error('rounds.*')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProfileModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Profile</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openUploadModal(stepId) {
        document.getElementById('uploadStepId').value = stepId ?? '';
        openModal('uploadDocModal');
    }

    function togglePassword(btn) {
        const span = btn.previousElementSibling;
        if (!span) return;
        const secret = span.dataset.secret || '';
        if (btn.textContent.trim() === 'Show') {
            span.textContent = secret;
            btn.textContent = 'Hide';
        } else {
            span.textContent = '•••••••••';
            btn.textContent = 'Show';
        }
    }
    window.togglePassword = togglePassword;

    const stepTypesByWeek = @json($stepTypesByWeek);
    const weekSel = document.getElementById('stepWeek');
    const stepSel = document.getElementById('stepType');
    const w4s2Fields = document.getElementById('w4s2-fields');

    function isW4S2() {
        return weekSel && stepSel
            && parseInt(weekSel.value, 10) === 4
            && stepSel.value === 'record_deletions';
    }

    function refreshTrackingFields() {
        if (!w4s2Fields) return;
        w4s2Fields.hidden = !isW4S2();
    }

    function refreshStepTypes() {
        const week = parseInt(weekSel.value, 10);
        const opts = stepTypesByWeek[week] || {};
        stepSel.innerHTML = '';
        for (const [key, label] of Object.entries(opts)) {
            const o = document.createElement('option');
            o.value = key;
            o.textContent = label;
            stepSel.appendChild(o);
        }
        refreshTrackingFields();
    }
    if (weekSel && stepSel) {
        weekSel.addEventListener('change', refreshStepTypes);
        stepSel.addEventListener('change', refreshTrackingFields);
        refreshStepTypes();
    }

    @if (session('new_step_id'))
        window.addEventListener('load', () => openUploadModal({{ session('new_step_id') }}));
    @endif

    /* ===== Bulk drag-and-drop document upload ===== */
    (function () {
        var zone  = document.getElementById('bulkDropzone');
        var input = document.getElementById('bulkFileInput');
        var list  = document.getElementById('bulkList');
        if (!zone || !input) return;

        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var endpoint = "{{ route('admin.documents.bulk') }}";
        var endUserId = {{ $endUser->id }};

        zone.addEventListener('click', function (e) {
            if (e.target === input) return;
            input.click();
        });
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault(); e.stopPropagation();
                zone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault(); e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });

        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                uploadFiles(e.dataTransfer.files);
            }
        });
        input.addEventListener('change', function () {
            if (input.files.length) uploadFiles(input.files);
        });

        function uploadFiles(fileList) {
            var files = Array.prototype.slice.call(fileList);
            list.innerHTML = '';
            var rows = files.map(function (f) {
                var row = document.createElement('div');
                row.className = 'dz-item';
                row.innerHTML = '<span class="dz-name">' + escapeHtml(f.name) + '</span>'
                    + '<span class="dz-bar"><span></span></span>'
                    + '<span class="dz-state uploading">Uploading…</span>';
                list.appendChild(row);
                return row;
            });

            var fd = new FormData();
            fd.append('end_user_id', endUserId);
            files.forEach(function (f) { fd.append('files[]', f); });

            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) return;
                var pct = Math.round((e.loaded / e.total) * 100);
                rows.forEach(function (r) {
                    var bar = r.querySelector('.dz-bar span');
                    if (bar) bar.style.width = pct + '%';
                });
            });

            xhr.onload = function () {
                var ok = xhr.status >= 200 && xhr.status < 300;
                rows.forEach(function (r) {
                    var s = r.querySelector('.dz-state');
                    var bar = r.querySelector('.dz-bar span');
                    if (ok) { s.className = 'dz-state done'; s.textContent = 'Uploaded'; if (bar) bar.style.width = '100%'; }
                    else    { s.className = 'dz-state error'; s.textContent = 'Failed'; }
                });
                if (ok) { setTimeout(function () { window.location.reload(); }, 700); }
            };
            xhr.onerror = function () {
                rows.forEach(function (r) {
                    var s = r.querySelector('.dz-state');
                    s.className = 'dz-state error'; s.textContent = 'Failed';
                });
            };
            xhr.send(fd);
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (c) {
                return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
            });
        }
    })();
</script>
@endpush
@endsection
