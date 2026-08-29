@extends($adminLayout ?? 'layouts.admin')

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
    $weeks = App\Models\ProcessStep::weeks($endUser->roundCycleDays());
    $stepTypesByWeek = App\Models\ProcessStep::stepTypesByWeek($endUser->roundCycleDays());
    $documentsByCategory = $endUser->documents->groupBy('category');
    $identityDocs = collect([
        ['type' => 'collage',          'label' => 'Collage',             'url' => $endUser->collage_url,          'path' => $endUser->collage_path],
        ['type' => 'photo_id',         'label' => 'Government Photo ID',  'url' => $endUser->photo_id_url,         'path' => $endUser->photo_id_path],
        ['type' => 'proof_of_address', 'label' => 'Proof of Address',    'url' => $endUser->proof_of_address_url, 'path' => $endUser->proof_of_address_path],
        ['type' => 'ssn_picture',      'label' => 'SSN Picture',         'url' => $endUser->ssn_picture_url,      'path' => $endUser->ssn_picture_path],
    ])->filter(fn ($d) => !empty($d['path']));
    $totalDocs = $endUser->documents->count() + $identityDocs->count();
@endphp

@section('topbar-content')
    <div class="page-actions">
        <a href="{{ route('admin.end-users.index') }}" class="btn btn-secondary page-action-btn">← All Clients</a>
        <form method="POST" action="{{ route('admin.end-users.destroy', $endUser) }}"
              data-confirm-delete
              data-confirm-title="Delete this client?"
              data-confirm-message="{{ $endUser->full_name }} and ALL their documents, notes, and process steps will be moved to the Recycle Bin. This cannot be undone from here.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger page-action-btn">Delete Client</button>
        </form>
    </div>
@endsection

@section('content')
@if ($endUser->needs_round_closeout)
    <div style="display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; padding:14px 16px;
                background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #dc2626;
                border-radius:12px; color:#991b1b; font-size:13.5px; line-height:1.55;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:none; margin-top:1px;">
            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12" y2="17"/>
        </svg>
        <div>
            <strong>Round {{ $endUser->current_round }} is past its 30 days.</strong>
            Worth pulling the latest report and recording deletions for this client.
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header client-header-name">
        <h2>{{ $endUser->full_name }}</h2>
    </div>
    <div class="info-grid client-header-row">
        <div><label>Business Owner</label><div>{{ $endUser->client?->business_name }}</div></div>
        <div><label>Email</label><div title="{{ $endUser->email }}">{{ $endUser->email }}</div></div>
        <div><label>Phone</label><div>{{ $endUser->phone ?? '—' }}</div></div>
        <div><label>Days Active</label><div>{{ $endUser->ever_started ? $endUser->days_active : 'Not started' }}</div></div>
        <div>
            <label>Status</label>
            <div>
                <span class="inline-edit inline-edit-status"
                      data-id="{{ $endUser->id }}"
                      data-current="{{ $endUser->status }}">
                    <span class="pill pill-{{ $endUser->status }}">{{ $endUser->status }}</span>
                    <span class="inline-pencil" aria-hidden="true">✎</span>
                </span>
            </div>
        </div>
        <div>
            <label>Round</label>
            <div class="round-hero-cell">
                <span class="round-hero-edit" onclick="openModal('roundScheduleModal')" role="button" tabindex="0" title="Manage rounds — start the next round or fix dates">
                    <span class="round-hero-text">{{ $endUser->started_rounds_short ?: 'Not started' }}</span>
                    <span class="rhe-pencil" aria-hidden="true">✎</span>
                </span>
            </div>
        </div>
    </div>
    @push('head')
    <style>
        /* Round field in the header — click to open the round editor */
        .round-hero-edit { cursor:pointer; display:inline-flex; align-items:center; gap:6px; border-radius:6px; padding:1px 5px; margin:-1px -5px; transition:background .15s; flex-wrap:wrap; }
        .round-hero-edit:hover { background:var(--selected); }
        /* Let the round list wrap instead of truncating with an ellipsis. */
        .round-hero-cell { white-space:normal !important; overflow:visible !important; text-overflow:clip !important; }
        .round-hero-text { white-space:normal; }
        .rhe-pencil { opacity:0; font-size:11px; color:#4f46e5; transition:opacity .15s; }
        .round-hero-edit:hover .rhe-pencil { opacity:1; }

        /* Comments tab — pulse amber→red when the client has comments */
        .tab.tab-has-notes { color:#b45309 !important; font-weight:800; animation: notesPulse 1.25s ease-in-out infinite; }
        .tab.tab-has-notes.active { color:#b91c1c !important; }
        .notes-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#dc2626;
            margin-left:6px; vertical-align:middle; animation: notesDot 1.25s ease-in-out infinite; }
        @keyframes notesPulse { 0%,100% { color:#b45309; } 50% { color:#dc2626; } }
        @keyframes notesDot { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.35; transform:scale(.8); } }
        @media (prefers-reduced-motion: reduce) {
            .tab.tab-has-notes { animation:none; color:#b91c1c !important; }
            .notes-dot { animation:none; }
        }

        /* Topbar action buttons — equal width/height */
        .page-actions { display: flex; gap: 10px; align-items: center; }
        .page-actions form { margin: 0; padding: 0; }
        .page-actions .page-action-btn {
            min-width: 140px;
            height: 38px;
            padding: 0 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            line-height: 1;
        }

        /* Card-header name (now the single source of the client's name) */
        .client-header-name { display: flex; align-items: center; padding-bottom: 12px; }
        .client-header-name h2 { margin: 0; font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }

        /* Single-row, premium header info strip — scoped only to the client detail page */
        .info-grid.client-header-row {
            display: grid !important;
            grid-template-columns: 1.4fr 1.6fr 1fr .8fr .9fr 1fr;
            gap: 18px 28px;
            align-items: start;
            padding: 6px 2px 2px;
        }
        .info-grid.client-header-row > div {
            min-width: 0;
        }
        .info-grid.client-header-row label {
            display: block;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .9px;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-grid.client-header-row > div > div {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.35;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .info-grid.client-header-row .pill {
            font-size: 10.5px;
        }
        .client-header-row .inline-edit { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .client-header-row .inline-edit .inline-pencil {
            opacity: 0; transition: opacity .15s; font-size: 11px; color: var(--muted);
        }
        .client-header-row .inline-edit:hover .inline-pencil { opacity: 1; }
        .client-header-row .inline-edit select { font-size: 12px; padding: 2px 6px; min-width: 120px; }
        .client-header-row .inline-edit .inline-save  { font-size: 11px; padding: 3px 9px; cursor: pointer; background: #16a34a; color: white; border: 0; border-radius: 4px; }
        .client-header-row .inline-edit .inline-cancel { font-size: 11px; padding: 3px 9px; cursor: pointer; background: var(--surface-2); color: var(--text-soft); border: 0; border-radius: 4px; }
        @media (max-width: 1180px) {
            .info-grid.client-header-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 14px 24px;
            }
        }
        @media (max-width: 720px) {
            .info-grid.client-header-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
    @endpush
</div>

<div class="tabs">
    <button class="tab active" data-target="tab-overview">Overview</button>
    <button class="tab" data-target="tab-profile">Profile</button>
    <button class="tab" data-target="tab-timeline">Process Timeline ({{ $endUser->processSteps->count() }})</button>
    <button class="tab" data-target="tab-docs">All Documents ({{ $totalDocs }})</button>
    @php $noteCount = $endUser->notes->count(); @endphp
    <button class="tab {{ $noteCount ? 'tab-has-notes' : '' }}" data-target="tab-notes">
        Comments ({{ $noteCount }})@if ($noteCount)<span class="notes-dot" aria-hidden="true"></span>@endif
    </button>
    <button class="tab" data-target="tab-status-report">Status Report</button>
    @if ($endUser->client?->resultsTrackingEnabled())
        <button class="tab" data-target="tab-results">Results ({{ $endUser->negativeItems->count() }})</button>
    @endif
    @if (!is_null($activity ?? null))
        <button class="tab" data-target="tab-activity">Activity ({{ count($activity) }})</button>
    @endif
</div>

@include('admin.end-users.partials.overview', ['endUser' => $endUser, 'totalDocs' => $totalDocs])

@if ($endUser->client?->resultsTrackingEnabled())
<div id="tab-results" class="tab-panel">
    @include('admin.end-users.partials.negative-items', ['endUser' => $endUser])
</div>
@endif

@if (!is_null($activity ?? null))
<div id="tab-activity" class="tab-panel">
    @include('admin.end-users.partials.activity', ['activity' => $activity, 'endUser' => $endUser])
</div>
@endif

<div id="tab-profile" class="tab-panel">
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
            <div><label>SSN</label><div>{{ $endUser->formatted_ssn ?? '—' }}</div></div>
        </div>

        <h4 class="profile-section-head">Current Address</h4>
        <div class="info-grid">
            <div><label>Current Address</label><div>{{ $endUser->current_address ?? '—' }}</div></div>
            <div><label>City</label><div>{{ $endUser->city ?? '—' }}</div></div>
            <div><label>State</label><div>{{ $endUser->state ?? '—' }}</div></div>
            <div><label>Zipcode</label><div>{{ $endUser->zipcode ?? '—' }}</div></div>
        </div>

        <h4 class="profile-section-head">Identity Document</h4>
        <div class="info-grid">
            <div>
                <label>Collage</label>
                <div>
                    @if ($endUser->collage_url)
                        <a href="{{ $endUser->collage_url }}" target="_blank" class="btn btn-sm">View File</a>
                    @else
                        <span class="muted">Not uploaded</span>
                    @endif
                </div>
            </div>
            @if ($endUser->photo_id_url)
                <div><label>Government Photo ID <span class="muted">(legacy)</span></label><div><a href="{{ $endUser->photo_id_url }}" target="_blank" class="btn btn-sm">View File</a></div></div>
            @endif
            @if ($endUser->proof_of_address_url)
                <div><label>Proof of Address <span class="muted">(legacy)</span></label><div><a href="{{ $endUser->proof_of_address_url }}" target="_blank" class="btn btn-sm">View File</a></div></div>
            @endif
            @if ($endUser->ssn_picture_url)
                <div><label>SSN Picture <span class="muted">(legacy)</span></label><div><a href="{{ $endUser->ssn_picture_url }}" target="_blank" class="btn btn-sm">View File</a></div></div>
            @endif
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
            @if ($endUser->credit_monitoring_security_question)
                <div>
                    <label>Security Question</label>
                    <div>{{ $endUser->credit_monitoring_security_question }}</div>
                </div>
            @endif
            <div>
                <label>Security {{ $endUser->credit_monitoring_security_question ? 'Answer' : 'Question Answer' }}</label>
                <div class="password-cell">
                    <span class="password-mask" data-secret="{{ $endUser->credit_monitoring_security_answer }}">
                        @if ($endUser->credit_monitoring_security_answer) ••••••••• @else — @endif
                    </span>
                    @if ($endUser->credit_monitoring_security_answer)
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                    @endif
                </div>
            </div>
            @if ($endUser->credit_monitoring_pin)
                <div>
                    <label>4-digit PIN</label>
                    <div class="password-cell">
                        <span class="password-mask" data-secret="{{ $endUser->credit_monitoring_pin }}">•••••••••</span>
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                    </div>
                </div>
            @endif
        </div>

        <h4 class="profile-section-head">Universal CFPB Logins</h4>
        <div class="info-grid">
            <div>
                <label>CFPB Login Email</label>
                <div class="password-cell">
                    <span>{{ $endUser->cfpb_email ?? '—' }}</span>
                    @if ($endUser->cfpb_email)
                        <button type="button" class="btn btn-sm" data-copy="{{ $endUser->cfpb_email }}" data-copy-msg="CFPB email copied">Copy</button>
                    @endif
                </div>
            </div>
            <div>
                <label>CFPB Password</label>
                <div class="password-cell">
                    <span class="password-mask" data-secret="{{ $endUser->cfpb_password }}">
                        @if ($endUser->cfpb_password) ••••••••• @else — @endif
                    </span>
                    @if ($endUser->cfpb_password)
                        <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                        <button type="button" class="btn btn-sm" data-copy="{{ $endUser->cfpb_password }}" data-copy-msg="CFPB password copied">Copy</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Per-round CFPB credentials — shown for each round the client has reached. --}}
        @foreach ($endUser->reachedRoundNumbers() as $rn)
            @php $rc = $endUser->cfpbForRound($rn); @endphp
            @if ($rc['email'] || $rc['password'])
                <h4 class="profile-section-head">{{ \App\Models\EndUser::ROUND_OPTIONS[$rn - 1] ?? ($rn.'th Round') }} CFPB Credentials</h4>
                <div class="info-grid">
                    <div>
                        <label>CFPB Login Email</label>
                        <div class="password-cell">
                            <span>{{ $rc['email'] ?? '—' }}</span>
                            @if ($rc['email'])
                                <button type="button" class="btn btn-sm" data-copy="{{ $rc['email'] }}" data-copy-msg="CFPB email copied">Copy</button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label>CFPB Password</label>
                        <div class="password-cell">
                            <span class="password-mask" data-secret="{{ $rc['password'] }}">
                                @if ($rc['password']) ••••••••• @else — @endif
                            </span>
                            @if ($rc['password'])
                                <button type="button" class="btn btn-sm" onclick="togglePassword(this)">Show</button>
                                <button type="button" class="btn btn-sm" data-copy="{{ $rc['password'] }}" data-copy-msg="CFPB password copied">Copy</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>

<div id="tab-timeline" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3>Process Timeline</h3>
            <button class="btn btn-primary" onclick="openModal('addStepModal')">+ Add Process Step</button>
        </div>
        <div class="timeline">
            @php
                // Show the timeline in true process order: Round → Week → step
                // sequence within that week (not the order rows were logged in).
                $stepSeq = [];
                foreach ($stepTypesByWeek as $wk => $types) {
                    $i = 0;
                    foreach (array_keys($types) as $t) {
                        $stepSeq[$wk . '|' . $t] = $i++;
                    }
                }
                $orderedSteps = $endUser->processSteps
                    ->sortBy(fn ($s) => ($s->round * 10000) + ($s->week * 100) + ($stepSeq[$s->week . '|' . $s->step_type] ?? 99))
                    ->values();
            @endphp
            @forelse ($orderedSteps as $step)
                <div class="timeline-item">
                    <div class="timeline-marker">R{{ $step->round }}·W{{ $step->week }}</div>
                    <div class="timeline-body">
                        <div class="timeline-head">
                            <span class="badge">Round {{ $step->round }}</span>
                            <span class="badge">Week {{ $step->week }}</span>
                            <span class="badge step-badge">{{ $step->step_type_label }}</span>
                            <span class="timeline-date">{{ $step->step_date?->format('M d, Y') }}</span>
                            <form method="POST" action="{{ route('admin.process-steps.destroy', $step->id) }}" class="timeline-delete" data-confirm-delete data-confirm-message="Delete this process step?">
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

        <div class="upcard" id="bulkDropzone" tabindex="0" role="button" aria-label="Drag and drop documents here, or click to choose a file">
            <input type="file" id="bulkFileInput" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.mp3,.wav,.doc,.docx,.xls,.xlsx,.csv,.txt">
            <div class="upcard-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </div>
            <div class="upcard-title">Drag &amp; Drop</div>
            <div class="upcard-sub">or <button type="button" class="upcard-link" id="bulkBrowse">choose a file</button></div>
            <div class="upcard-hint">PDF, image, audio &amp; office files — multiple at once</div>
        </div>
        <div class="dz-list" id="bulkList" aria-live="polite"></div>

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
                            <a href="{{ $idoc['url'] }}" download class="btn btn-sm" title="Download"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
                            <form method="POST" action="{{ route('admin.end-users.identity.destroy', [$endUser->id, $idoc['type']]) }}" style="display:inline" data-confirm-delete data-confirm-message="Delete this identity document? This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Delete">×</button>
                            </form>
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
                                <a href="{{ $doc->url }}" download class="btn btn-sm" title="Download"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
                                <form method="POST" action="{{ route('admin.documents.destroy', $doc->id) }}" style="display:inline" data-confirm-delete data-confirm-message="Delete this document?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Delete">×</button>
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
            <h3>Comments</h3>
            <button class="btn btn-primary" onclick="openModal('addNoteModal')">+ Add Comment</button>
        </div>
        @forelse ($endUser->notes as $note)
            <div class="note-item">
                <div class="note-meta">
                    <strong>{{ $note->createdBy?->full_name ?? 'VA' }}</strong>
                    <span class="muted">· {{ $note->created_at?->format('M d, Y H:i') }}</span>
                </div>
                <div class="note-body">{{ $note->note_text }}</div>
                <form method="POST" action="{{ route('admin.notes.destroy', $note->id) }}" data-confirm-delete data-confirm-message="Delete this comment?">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        @empty
            <div class="empty">No comments yet.</div>
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
            <h3>Add Process Step(s)</h3>
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
                <div class="msel2-head">
                    <label>Step Type(s)</label>
                    <span class="msel2-count" id="stepMselCount">0 steps selected</span>
                </div>
                <div class="msel2" id="stepMsel">
                    <input type="text" class="msel2-search" id="stepMselSearch" placeholder="Search process steps&hellip;" autocomplete="off">
                    <div class="msel2-actions">
                        <button type="button" class="msel2-btn" id="stepMselAll">Select All</button>
                        <button type="button" class="msel2-btn" id="stepMselClear">Clear All</button>
                    </div>
                    <div class="msel2-list" id="stepMselOptions" role="listbox" aria-multiselectable="true"></div>
                </div>
                <div id="stepMselInputs"></div>
                <small class="msel-hint" id="stepMselHint">Pick one or more. Each becomes its own timeline entry for the chosen round &amp; week.</small>
            </div>
            <div class="form-group"><label>Date</label><input type="date" name="step_date" id="stepDateInput" value="{{ now()->timezone('America/New_York')->toDateString() }}" required></div>
            <div id="w4s2-fields" class="w4s2-fields" hidden>
                <div class="bureau-block">
                    <h4>Round Results</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Total Deletions</label><input type="number" name="total_deletions" min="0"></div>
                        <div class="form-group"><label>Updated to Positive</label><input type="number" name="updated_to_positive" min="0"></div>
                    </div>
                    <div class="bureau-row">
                        <div class="form-group"><label>Updated to Negative</label><input type="number" name="updated_to_negative" min="0"></div>
                        <div class="form-group"><label>Added</label><input type="number" name="items_added" min="0"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>Experian</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Score Before</label><input type="number" name="experian_score_before" min="300" max="850"></div>
                        <div class="form-group"><label>Score Now</label><input type="number" name="experian_score_now" min="300" max="850"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>TransUnion</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Score Before</label><input type="number" name="transunion_score_before" min="300" max="850"></div>
                        <div class="form-group"><label>Score Now</label><input type="number" name="transunion_score_now" min="300" max="850"></div>
                    </div>
                </div>
                <div class="bureau-block">
                    <h4>Equifax</h4>
                    <div class="bureau-row">
                        <div class="form-group"><label>Score Before</label><input type="number" name="equifax_score_before" min="300" max="850"></div>
                        <div class="form-group"><label>Score Now</label><input type="number" name="equifax_score_now" min="300" max="850"></div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStepModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Step(s)</button>
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
            <h3>Add Comment</h3>
            <button class="modal-close" onclick="closeModal('addNoteModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.notes.store') }}">
            @csrf
            <input type="hidden" name="end_user_id" value="{{ $endUser->id }}">
            <div class="form-group"><label>Comment</label><textarea name="note_text" rows="4" required></textarea></div>
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
        {{-- Plain (non-multipart) POST: the collage upload moved to the All
             Documents tab, so this form only carries text fields. data-guard-unsaved
             warns the VA before closing with pending edits. --}}
        <form method="POST" action="{{ route('admin.end-users.update', $endUser) }}" data-guard-unsaved>
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
                    <div class="form-group"><label>SSN <span class="muted">(leave blank to keep current)</span></label><input type="text" name="ssn" placeholder="9 digits, no dashes" autocomplete="off" data-dup-digits="9"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Current Address</h4>
                <div class="form-row">
                    <div class="form-group"><label>Current Address</label><input type="text" name="current_address" value="{{ old('current_address', $endUser->current_address) }}" maxlength="255" placeholder="Street address"></div>
                    <div class="form-group"><label>City</label><input type="text" name="city" value="{{ old('city', $endUser->city) }}" maxlength="120"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>State</label><input type="text" name="state" value="{{ old('state', $endUser->state) }}" maxlength="120"></div>
                    <div class="form-group"><label>Zipcode</label><input type="text" name="zipcode" value="{{ old('zipcode', $endUser->zipcode) }}" maxlength="20"></div>
                </div>
            </div>

            <div class="form-section">
                <h4>Identity Document</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Collage</label>
                        @if ($endUser->collage_url)
                            <div class="muted small"><a href="{{ $endUser->collage_url }}" target="_blank">Current file</a></div>
                        @else
                            <div class="muted small">Not uploaded.</div>
                        @endif
                        <div class="muted small">To add or replace identity files, use the <strong>All Documents</strong> tab (its uploader isn't affected by this).</div>
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
                <h4>Universal CFPB Logins <span class="muted">(optional)</span></h4>
                <div class="form-row">
                    <div class="form-group"><label>CFPB Login Email</label><input type="email" name="cfpb_email" value="{{ old('cfpb_email', $endUser->cfpb_email) }}" autocomplete="off"></div>
                    <div class="form-group"><label>CFPB Password <span class="muted">(leave blank to keep current)</span></label><input type="text" name="cfpb_password" autocomplete="off"></div>
                </div>
            </div>

            {{-- Per-round CFPB credentials — one block per round the client has
                 reached (the box appears once they're moved into that round). --}}
            @foreach ($endUser->reachedRoundNumbers() as $rn)
                @php $rc = $endUser->cfpbForRound($rn); @endphp
                <div class="form-section">
                    <h4>{{ \App\Models\EndUser::ROUND_OPTIONS[$rn - 1] ?? ($rn.'th Round') }} CFPB Credentials <span class="muted">(optional)</span></h4>
                    <div class="form-row">
                        <div class="form-group"><label>CFPB Login Email</label><input type="email" name="cfpb_rounds[{{ $rn }}][email]" value="{{ old('cfpb_rounds.'.$rn.'.email', $rc['email']) }}" autocomplete="off"></div>
                        <div class="form-group"><label>CFPB Password <span class="muted">(leave blank to keep current)</span></label><input type="text" name="cfpb_rounds[{{ $rn }}][password]" autocomplete="off"></div>
                    </div>
                </div>
            @endforeach

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

    /* ===== Inline Status edit on the header row ===== */
    (function () {
        var STATUSES = ['active','paused','graduated','cancelled'];
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
        var updateUrl = @json(route('admin.end-users.update', $endUser));

        document.querySelectorAll('.client-header-row .inline-edit-status').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.classList.contains('editing')) return;
                e.preventDefault(); e.stopPropagation();
                var current = el.dataset.current;
                el.classList.add('editing');
                el.innerHTML =
                    '<select>' + STATUSES.map(function (s) {
                        return '<option value="'+s+'"'+(s===current?' selected':'')+'>'+s+'</option>';
                    }).join('') + '</select>' +
                    '<button class="inline-save" type="button">Save</button>' +
                    '<button class="inline-cancel" type="button">×</button>';
                var sel = el.querySelector('select');
                sel.focus();
                sel.addEventListener('click', function (ev) { ev.stopPropagation(); });
                el.querySelector('.inline-cancel').addEventListener('click', function (ev) {
                    ev.preventDefault(); ev.stopPropagation();
                    window.location.reload();
                });
                el.querySelector('.inline-save').addEventListener('click', function (ev) {
                    ev.preventDefault(); ev.stopPropagation();
                    var fd = new FormData();
                    fd.append('_method', 'PUT');
                    fd.append('_token', csrf);
                    fd.append('status', sel.value);
                    fetch(updateUrl, {
                        method: 'POST', body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    }).then(function (r) {
                        if (r.ok) window.location.reload();
                        else alert('Could not save status.');
                    });
                });
            });
        });
    })();

    /* ===== Multi-select Process Step Type ===== */
    const stepTypesByWeek = @json($stepTypesByWeek);
    const existingCombos = @json($endUser->processSteps->map(fn ($s) => $s->round . '-' . $s->week . '-' . $s->step_type)->values());
    const existingSet = new Set(existingCombos);

    /* Stamp the step Date with "today" in US Eastern every time the Add Step
       modal opens — so each step dates to when it was actually marked, not the
       stale page-load date and not UTC's next-day rollover. VA can still change
       it for back-dated entries. */
    (function () {
        function estToday() {
            return new Date().toLocaleDateString('en-CA', { timeZone: 'America/New_York' }); // YYYY-MM-DD
        }
        function stampStepDate() {
            var inp = document.getElementById('stepDateInput');
            if (inp) inp.value = estToday();
        }
        document.querySelectorAll('[onclick*="addStepModal"]').forEach(function (btn) {
            btn.addEventListener('click', stampStepDate);
        });
    })();

    @php
        $pastStepsByKey = $endUser->processSteps
            ->sortByDesc('round')
            ->groupBy(fn ($s) => $s->week . '-' . $s->step_type)
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'round'                         => $first->round,
                    'experian_accounts_disputed'    => $first->experian_accounts_disputed,
                    'experian_inquiries_disputed'   => $first->experian_inquiries_disputed,
                    'transunion_accounts_disputed'  => $first->transunion_accounts_disputed,
                    'transunion_inquiries_disputed' => $first->transunion_inquiries_disputed,
                    'equifax_accounts_disputed'     => $first->equifax_accounts_disputed,
                    'equifax_inquiries_disputed'    => $first->equifax_inquiries_disputed,
                    'previous_credit_score'         => $first->previous_credit_score,
                    'credit_score_now'              => $first->credit_score_now,
                ];
            });
    @endphp
    /* Past-step lookup for "copy from last round" prefill.
       Keyed by week-step_type, returns the most recent round's bureau counts. */
    const pastStepsByKey = @json($pastStepsByKey);

    function prefillFromLastRound() {
        if (currentWeek() !== 4 || !selected.has('record_deletions')) return;
        const key = '4-record_deletions';
        const prior = pastStepsByKey[key];
        if (!prior || prior.round >= currentRound()) return;
        const fields = [
            'experian_accounts_disputed', 'experian_inquiries_disputed',
            'transunion_accounts_disputed', 'transunion_inquiries_disputed',
            'equifax_accounts_disputed', 'equifax_inquiries_disputed',
            'previous_credit_score', 'credit_score_now',
        ];
        fields.forEach(function (f) {
            var el = document.querySelector('#addStepModal [name="' + f + '"]');
            if (el && (el.value === '' || el.value == null) && prior[f] != null) {
                el.value = prior[f];
                el.classList.add('prefilled');
            }
        });
    }

    const roundSel  = document.getElementById('stepRound');
    const weekSel   = document.getElementById('stepWeek');
    const w4s2Fields = document.getElementById('w4s2-fields');

    const mselSearch  = document.getElementById('stepMselSearch');
    const mselOptions = document.getElementById('stepMselOptions');
    const mselInputs  = document.getElementById('stepMselInputs');
    const mselHint    = document.getElementById('stepMselHint');
    const mselCount   = document.getElementById('stepMselCount');
    const mselAllBtn  = document.getElementById('stepMselAll');
    const mselClrBtn  = document.getElementById('stepMselClear');

    let selected = new Set(); // step_type keys selected for current week

    function currentWeek()  { return parseInt(weekSel.value, 10); }
    function currentRound() { return parseInt(roundSel.value, 10); }
    function currentOpts()  { return stepTypesByWeek[currentWeek()] || {}; }

    function isExisting(key) {
        return existingSet.has(currentRound() + '-' + currentWeek() + '-' + key);
    }

    function refreshTrackingFields() {
        if (!w4s2Fields) return;
        // Round-outcome metrics apply whenever the report is pulled or deletions
        // are recorded — any round, any week.
        var show = (selected.has('record_deletions') || selected.has('pull_latest_report'));
        w4s2Fields.hidden = !show;
        // Always optional — never block saving a step on these numbers.
        w4s2Fields.querySelectorAll('input[type="number"]').forEach(function (el) {
            el.removeAttribute('required');
        });
        if (show) prefillFromLastRound();
    }

    var checkMark = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

    function renderOptions() {
        const opts = currentOpts();
        const q = (mselSearch.value || '').toLowerCase();
        mselOptions.innerHTML = '';
        const entries = Object.entries(opts).filter(function (e) {
            return e[1].toLowerCase().includes(q);
        });
        if (entries.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'msel2-empty';
            empty.textContent = 'No matching steps for this week.';
            mselOptions.appendChild(empty);
            return;
        }
        entries.forEach(function ([key, label]) {
            const existing = isExisting(key);
            const isSel = selected.has(key);
            const row = document.createElement('div');
            row.className = 'msel2-opt'
                + (isSel ? ' is-selected' : '')
                + (existing ? ' is-existing' : '');
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', isSel ? 'true' : 'false');
            if (existing) row.setAttribute('aria-disabled', 'true');

            const box = document.createElement('span');
            box.className = 'msel2-box';
            box.innerHTML = isSel ? checkMark : '';

            const txt = document.createElement('span');
            txt.className = 'msel2-txt';
            txt.textContent = label;

            row.appendChild(box);
            row.appendChild(txt);

            if (existing) {
                const badge = document.createElement('span');
                badge.className = 'msel2-badge';
                badge.textContent = 'Exists';
                row.appendChild(badge);
            }

            if (!existing) {
                row.addEventListener('click', function () {
                    if (selected.has(key)) selected.delete(key); else selected.add(key);
                    sync();
                });
            }
            mselOptions.appendChild(row);
        });
    }

    function renderInputs() {
        mselInputs.innerHTML = '';
        selected.forEach(function (key) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'step_types[]';
            inp.value = key;
            mselInputs.appendChild(inp);
        });
    }

    function renderCount() {
        const n = selected.size;
        mselCount.textContent = n + (n === 1 ? ' step selected' : ' steps selected');
        mselCount.classList.toggle('has', n > 0);
    }

    function renderHint() {
        mselHint.className = 'msel-hint';
        mselHint.textContent = 'Each selected step becomes its own timeline entry for the chosen round & week. Steps marked "Exists" are already logged and can’t be re-added.';
    }

    function sync() {
        renderOptions();
        renderInputs();
        renderCount();
        renderHint();
        refreshTrackingFields();
    }

    function rebuildForWeek() {
        const opts = currentOpts();
        selected.forEach(function (k) { if (!(k in opts)) selected.delete(k); });
        mselSearch.value = '';
        sync();
    }

    mselSearch.addEventListener('input', renderOptions);
    mselAllBtn.addEventListener('click', function () {
        Object.keys(currentOpts()).forEach(function (k) {
            if (!isExisting(k)) selected.add(k);
        });
        sync();
    });
    mselClrBtn.addEventListener('click', function () {
        selected.clear();
        sync();
    });
    roundSel.addEventListener('change', function () { sync(); });
    weekSel.addEventListener('change', rebuildForWeek);

    // Guard: block submit if nothing selected.
    const stepForm = document.querySelector('#addStepModal form');
    if (stepForm) {
        stepForm.addEventListener('submit', function (e) {
            if (selected.size === 0) {
                e.preventDefault();
                mselHint.className = 'msel-hint warn';
                mselHint.textContent = 'Select at least one process step before saving.';
                mselSearch.focus();
            }
        });
    }

    sync();

    /* ===== Bulk drag-and-drop document upload ===== */
    (function () {
        var zone  = document.getElementById('bulkDropzone');
        var input = document.getElementById('bulkFileInput');
        var list  = document.getElementById('bulkList');
        if (!zone || !input) return;

        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var endpoint = "{{ route('admin.documents.bulk') }}";
        var endUserId = {{ $endUser->id }};

        var browseBtn = document.getElementById('bulkBrowse');

        zone.addEventListener('click', function (e) {
            if (e.target === input) return;
            input.click();
        });
        if (browseBtn) {
            browseBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                input.click();
            });
        }
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
                row.innerHTML =
                      '<span class="dz-ico"><span class="dz-spin" aria-hidden="true"></span></span>'
                    + '<span class="dz-name">' + escapeHtml(f.name) + '</span>'
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

            var checkSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            var errSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

            xhr.onload = function () {
                var ok = xhr.status >= 200 && xhr.status < 300;
                if (ok) {
                    rows.forEach(function (r) {
                        var s = r.querySelector('.dz-state');
                        var bar = r.querySelector('.dz-bar span');
                        var ico = r.querySelector('.dz-ico');
                        s.className = 'dz-state done'; s.textContent = 'Done';
                        if (bar) bar.style.width = '100%';
                        if (ico) { ico.className = 'dz-ico ok'; ico.innerHTML = checkSvg; }
                    });
                    // Come back on the All Documents tab so they can upload
                    // another one right away, instead of resetting to Overview.
                    setTimeout(function () {
                        try { history.replaceState(null, '', '#tab-docs'); } catch (e) {}
                        window.location.reload();
                    }, 900);
                } else {
                    markFailed(rows, extractError(xhr));
                }
            };
            xhr.onerror = function () {
                markFailed(rows, 'The upload was blocked before it reached the server (network, firewall/WAF, or a size limit).');
            };
            xhr.send(fd);

            // Pull the real reason out of the server's response so the row can
            // show WHY it failed instead of a bare "Failed".
            function extractError(xhr) {
                var msg = '';
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j) {
                        if (j.errors && typeof j.errors === 'object') {
                            var parts = [];
                            Object.keys(j.errors).forEach(function (k) {
                                parts.push([].concat(j.errors[k]).join(' '));
                            });
                            msg = parts.join(' ');
                        }
                        if (!msg && j.message && j.message !== 'The given data was invalid.') {
                            msg = j.message;
                        }
                    }
                } catch (e) { /* non-JSON (HTML error page) — fall through */ }
                if (!msg) {
                    if (xhr.status === 413) msg = 'File is too large for the server.';
                    else if (xhr.status === 419) msg = 'Your session expired — reload the page and sign in again.';
                    else if (xhr.status === 0) msg = 'No response from the server (connection dropped or blocked).';
                    else msg = 'Server responded with HTTP ' + (xhr.status || '?') + '.';
                }
                return msg;
            }

            function markFailed(rowEls, message) {
                rowEls.forEach(function (r) {
                    var s = r.querySelector('.dz-state');
                    var ico = r.querySelector('.dz-ico');
                    var bar = r.querySelector('.dz-bar');
                    if (s) { s.className = 'dz-state error'; s.textContent = 'Failed'; }
                    if (ico) { ico.className = 'dz-ico bad'; ico.innerHTML = errSvg; }
                    if (bar) bar.style.visibility = 'hidden';
                    var err = r.querySelector('.dz-err');
                    if (!err) { err = document.createElement('span'); err.className = 'dz-err'; r.appendChild(err); }
                    err.textContent = message;
                });
            }
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
