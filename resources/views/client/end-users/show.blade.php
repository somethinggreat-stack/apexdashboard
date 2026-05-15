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
        <a href="{{ route('client.end-users.index') }}" class="btn btn-secondary">← My Clients</a>
    </div>
    <div class="info-grid">
        <div><label>Suffix</label><div>{{ $endUser->suffix && $endUser->suffix !== 'None' ? $endUser->suffix : '—' }}</div></div>
        <div><label>Email</label><div>{{ $endUser->email }}</div></div>
        <div><label>Phone</label><div>{{ $endUser->phone ?? '—' }}</div></div>
        <div><label>Days Active</label><div>{{ $endUser->days_active }}</div></div>
        <div><label>Total Deletions</label><div>{{ $endUser->total_deletions }}</div></div>
        <div><label>Status</label><div><span class="pill pill-{{ $endUser->status }}">{{ $endUser->status }}</span></div></div>
        <div><label>Started</label><div>{{ $endUser->start_date?->format('M d, Y') }}</div></div>
    </div>
</div>

<div class="tabs">
    <button class="tab active" data-target="tab-profile">Profile</button>
    <button class="tab" data-target="tab-timeline">Process Timeline ({{ $endUser->processSteps->count() }})</button>
    <button class="tab" data-target="tab-docs">All Documents ({{ $totalDocs }})</button>
    <button class="tab" data-target="tab-notes">Notes ({{ $endUser->notes->count() }})</button>
</div>

<div id="tab-profile" class="tab-panel active">
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
            <div><label>Password</label><div>{{ $endUser->credit_monitoring_password ? '•••••••••' : '—' }}</div></div>
            <div><label>Security Question Answer</label><div>{{ $endUser->credit_monitoring_security_answer ? '•••••••••' : '—' }}</div></div>
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
        <h3>Notes</h3>
        @forelse ($endUser->notes as $note)
            <div class="note-item">
                <div class="note-meta">
                    <strong>{{ $note->createdBy?->full_name ?? 'VA' }}</strong>
                    <span class="muted">· {{ $note->created_at?->format('M d, Y H:i') }}</span>
                </div>
                <div class="note-body">{{ $note->note_text }}</div>
            </div>
        @empty
            <div class="empty">No notes yet.</div>
        @endforelse
    </div>
</div>

@endsection
