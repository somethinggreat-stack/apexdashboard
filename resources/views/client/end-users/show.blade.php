@extends('layouts.client')

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
        <a href="{{ $endUser->intake_status === 'done' ? route('client.client-list') : route('client.end-users.index') }}" class="btn btn-secondary page-action-btn">← Back</a>
    </div>
@endsection

@section('content')
<div class="card">
    <div class="card-header client-header-name">
        <h2>{{ $endUser->full_name }}</h2>
    </div>
    <div class="info-grid client-header-row">
        <div><label>Business Owner</label><div>{{ $endUser->client?->business_name }}</div></div>
        <div><label>Email</label><div title="{{ $endUser->email }}">{{ $endUser->email }}</div></div>
        <div><label>Phone</label><div>{{ $endUser->phone ?? '—' }}</div></div>
        <div><label>Days Active</label><div>{{ $endUser->days_active }}</div></div>
        <div><label>Status</label><div><span class="pill pill-{{ $endUser->status }}">{{ $endUser->status }}</span></div></div>
        <div><label>Round</label><div>{{ !empty($endUser->rounds) ? implode(', ', $endUser->rounds) : '—' }}</div></div>
    </div>
    @push('head')
    <style>
        /* Topbar action buttons — equal width/height */
        .page-actions { display: flex; gap: 10px; align-items: center; }
        .page-actions form { margin: 0; padding: 0; }
        .page-actions .page-action-btn {
            min-width: 140px; height: 38px; padding: 0 18px;
            font-size: 13px; font-weight: 600; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            box-sizing: border-box; line-height: 1;
        }

        /* Card-header name */
        .client-header-name { display: flex; align-items: center; padding-bottom: 12px; }
        .client-header-name h2 { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; letter-spacing: -.3px; }

        /* Single-row header info strip */
        .info-grid.client-header-row {
            display: grid !important;
            grid-template-columns: 1.4fr 1.6fr 1fr .8fr .9fr 1fr;
            gap: 18px 28px;
            align-items: start;
            padding: 6px 2px 2px;
        }
        .info-grid.client-header-row > div { min-width: 0; }
        .info-grid.client-header-row label {
            display: block;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .9px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-grid.client-header-row > div > div {
            font-size: 14px; font-weight: 600; color: #0f172a;
            line-height: 1.35;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .info-grid.client-header-row .pill { font-size: 10.5px; }
        @media (max-width: 1180px) {
            .info-grid.client-header-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 14px 24px;
            }
        }
        @media (max-width: 720px) {
            .info-grid.client-header-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    @endpush
</div>

<div class="tabs">
    <button class="tab active" data-target="tab-overview">Overview</button>
    <button class="tab" data-target="tab-profile">Profile</button>
    <button class="tab" data-target="tab-timeline">Process Timeline ({{ $endUser->processSteps->count() }})</button>
    <button class="tab" data-target="tab-docs">All Documents ({{ $totalDocs }})</button>
    <button class="tab" data-target="tab-notes">Comments ({{ $endUser->notes->count() }})</button>
    <button class="tab" data-target="tab-status-report">Status Report</button>
</div>

@include('admin.end-users.partials.overview', ['endUser' => $endUser, 'totalDocs' => $totalDocs, 'portal' => 'client'])

<div id="tab-profile" class="tab-panel">
    <div class="card">
        <h3>Profile Information</h3>

        <h4 class="profile-section-head">Personal</h4>
        <div class="info-grid">
            <div><label>First Name</label><div>{{ $endUser->first_name }}</div></div>
            <div><label>Last Name</label><div>{{ $endUser->last_name }}</div></div>
            <div><label>Suffix</label><div>{{ $endUser->suffix && $endUser->suffix !== 'None' ? $endUser->suffix : '—' }}</div></div>
            <div><label>Email Address</label><div>{{ $endUser->email }}</div></div>
            <div><label>Phone Number</label><div>{{ $endUser->phone ?? '—' }}</div></div>
            <div><label>Date of Birth</label><div>{{ $endUser->date_of_birth?->format('M d, Y') ?? '—' }}</div></div>
            <div><label>SSN</label><div>{{ $endUser->masked_ssn ?? '—' }}</div></div>
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
            <div><label>Password</label><div>{{ $endUser->credit_monitoring_password ? '•••••••••' : '—' }}</div></div>
            @if ($endUser->credit_monitoring_security_question)
                <div><label>Security Question</label><div>{{ $endUser->credit_monitoring_security_question }}</div></div>
            @endif
            <div><label>Security {{ $endUser->credit_monitoring_security_question ? 'Answer' : 'Question Answer' }}</label><div>{{ $endUser->credit_monitoring_security_answer ? '•••••••••' : '—' }}</div></div>
            @if ($endUser->credit_monitoring_pin)
                <div><label>4-digit PIN</label><div>•••••••••</div></div>
            @endif
        </div>

        <h4 class="profile-section-head">CFPB</h4>
        <div class="info-grid">
            <div><label>CFPB Login Email</label><div>{{ $endUser->cfpb_email ?? '—' }}</div></div>
            <div><label>CFPB Password</label><div>{{ $endUser->cfpb_password ? '•••••••••' : '—' }}</div></div>
        </div>
    </div>
</div>

<div id="tab-timeline" class="tab-panel">
    <div class="card">
        <h3>Process Timeline</h3>
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
                    </div>
                </div>
            @empty
                <div class="empty">No process steps logged yet.</div>
            @endforelse
        </div>
    </div>
</div>

<div id="tab-docs" class="tab-panel">
    <div class="card">
        <h3>All Documents</h3>

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
                            <div class="doc-actions"><a href="{{ $doc->url }}" target="_blank" class="btn btn-sm">Open</a></div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</div>

<div id="tab-notes" class="tab-panel">
    <div class="card">
        <h3>Comments</h3>
        @forelse ($endUser->notes as $note)
            <div class="note-item">
                {{-- Never expose which admin/VA authored a comment to the business
                     owner — show a generic team label only. --}}
                <div class="note-meta">
                    <strong>Apex Growth Team</strong>
                    <span class="muted">· {{ $note->created_at?->format('M d, Y H:i') }}</span>
                </div>
                <div class="note-body">{{ $note->note_text }}</div>
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
            <a href="{{ route('client.end-users.status-report', $endUser) }}" target="_blank" class="btn btn-secondary">Print / Save PDF</a>
        </div>
        @include('partials.status-report', ['endUser' => $endUser])
    </div>
</div>

@endsection
