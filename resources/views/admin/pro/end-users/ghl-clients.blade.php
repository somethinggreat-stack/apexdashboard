@extends('layouts.admin-pro')

@section('title', 'GHL Clients')
@section('subtitle', 'Onboarding pulled from GoHighLevel, waiting for review.')

@section('content')

<div class="pro-panel" style="margin-bottom:20px; padding:22px;">
    <div class="ghl-head">
        <div>
            <div class="pro-panel-title" style="margin-bottom:6px;">
                <h2>GoHighLevel Onboarding</h2>
            </div>
            <p class="ghl-sub">
                When a client completes the Credit Repair Onboarding Form in
                {{ $client->business_name }}'s GoHighLevel account, press
                <strong>Sync now</strong> to pull them in — every answer they gave, plus
                their licence, proof of address and SSN card. Anyone already pulled is
                skipped, so it is safe to press as often as you like.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.ghl-clients.sync') }}" style="margin:0;" data-ghl-sync>
            @csrf
            <button class="pro-act done">Sync now</button>
        </form>
    </div>
</div>

<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </span>
            <h2>Awaiting review</h2>
            <span class="pro-panel-count danger">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Documents</th>
                    <th>Onboarded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    @php
                        $missing = collect([
                            'Licence'  => $eu->photo_id_path,
                            'Address'  => $eu->proof_of_address_path,
                            'SSN card' => $eu->ssn_picture_path,
                        ])->filter(fn ($path) => blank($path))->keys();
                    @endphp
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#ede9fe; color:#5b21b6;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <div>
                                    <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                                    <span class="ghl-tag">GHL</span>
                                    @if ($eu->intake_review_note)
                                        <div class="pro-note">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ $eu->phone ?: '—' }}</td>
                        <td>
                            @if ($missing->isEmpty())
                                <span class="docs-ok">All 3 received</span>
                            @else
                                <span class="docs-missing">Missing: {{ $missing->implode(', ') }}</span>
                            @endif
                        </td>
                        <td>{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Review</a>

                                <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                      data-confirm-action data-confirm-message="Are you sure you want to move {{ $eu->full_name }} to In Progress?">
                                    @csrf
                                    <button class="pro-act done">Move to In Progress</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form">
                                    @csrf
                                    <input type="hidden" name="note" value="">
                                    <button type="button" class="pro-act warn" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.hold', $eu->id) }}">
                                    @csrf
                                    <button class="pro-act hold">Hold/Pause</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Nothing waiting for review. Press <strong>Sync now</strong> to check GoHighLevel for new onboarding submissions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($recent->isNotEmpty())
<div class="pro-panel" style="margin-top:20px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <h2>Pull history</h2>
            <span class="pro-panel-count">{{ $recent->count() }}</span>
        </div>
    </div>
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Client Name</th><th>Email</th><th>Status</th><th>Onboarded</th><th>Pulled</th></tr>
            </thead>
            <tbody>
                @foreach ($recent as $eu)
                    <tr>
                        <td><a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a></td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', (string) $eu->intake_status)) }}</td>
                        <td>{{ $eu->intake_submitted_at?->format('M j, Y') ?: '—' }}</td>
                        <td>{{ $eu->ghl_synced_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@include('admin.end-users._ghl-sync-modal')

@endsection

@push('head')
<style>
    .ghl-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; flex-wrap:wrap; }
    .ghl-sub { margin:0; font-size:13px; color:var(--pro-text-soft); max-width:70ch; line-height:1.55; }
    .ghl-tag { display:inline-block; margin-left:6px; padding:1px 6px; border-radius:4px;
               background:#ede9fe; color:#5b21b6; font-size:10.5px; font-weight:700; letter-spacing:.03em; }
    .docs-ok { color:#047857; font-weight:600; font-size:12.5px; }
    .docs-missing { color:#b45309; font-weight:600; font-size:12.5px; }
</style>
@endpush

@push('scripts')
<script>
window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?', '');
    if (note === null) return;            // cancelled
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
