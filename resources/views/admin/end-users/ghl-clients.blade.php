@extends('layouts.admin')

@section('title', 'GHL Clients')

@section('content')

<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div>
            <h2>GoHighLevel Onboarding</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                When a client completes the Credit Repair Onboarding Form in
                {{ $client->business_name }}'s GoHighLevel account, press
                <strong>Sync now</strong> to pull them in with all of their data and
                documents. Anyone already pulled is skipped, so it is safe to press twice.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.ghl-clients.sync') }}" style="margin:0;" data-ghl-sync>
            @csrf
            <button class="btn btn-primary">Sync now</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Awaiting review <span class="pending-badge">{{ $endUsers->count() }}</span></h2>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
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
                    $docs = collect([
                        'Licence' => $eu->photo_id_path,
                        'Address' => $eu->proof_of_address_path,
                        'SSN card' => $eu->ssn_picture_path,
                    ]);
                    $missing = $docs->filter(fn ($p) => blank($p))->keys();
                @endphp
                <tr>
                    <td>
                        <strong>{{ $eu->full_name }}</strong>
                        <span class="ghl-tag">GHL</span>
                        @if ($eu->intake_review_note)
                            <div class="review-note">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                        @endif
                    </td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td>
                        @if ($missing->isEmpty())
                            <span class="docs-ok">All 3</span>
                        @else
                            <span class="docs-missing">Missing: {{ $missing->implode(', ') }}</span>
                        @endif
                    </td>
                    <td class="muted">{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Review</a>
                            <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                  data-confirm-action data-confirm-message="Are you sure you want to move {{ $eu->full_name }} to In Progress?">
                                @csrf
                                <button class="btn btn-sm btn-approve">Move to In Progress</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form">
                                @csrf
                                <input type="hidden" name="note" value="">
                                <button type="button" class="btn btn-sm btn-toerror" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Nothing waiting for review. Press <strong>Sync now</strong> to check GoHighLevel.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@if ($recent->isNotEmpty())
<div class="card" style="margin-top:18px;">
    <div class="card-header">
        <div>
            <h2>Pull history</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Everyone pulled from GoHighLevel so far. These are skipped on future syncs.
            </p>
        </div>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Status</th><th>Onboarded</th><th>Pulled</th></tr>
        </thead>
        <tbody>
            @foreach ($recent as $eu)
                <tr>
                    <td><a href="{{ route('admin.end-users.show', $eu) }}"><strong>{{ $eu->full_name }}</strong></a></td>
                    <td>{{ $eu->email }}</td>
                    <td class="muted">{{ ucfirst(str_replace('_', ' ', (string) $eu->intake_status)) }}</td>
                    <td class="muted">{{ $eu->intake_submitted_at?->format('M j, Y') ?: '—' }}</td>
                    <td class="muted">{{ $eu->ghl_synced_at?->format('M j, Y g:ia') ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
</div>
@endif

@include('admin.end-users._ghl-sync-modal')

@endsection

@push('head')
<style>
    .card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
    .pending-badge { display:inline-block; min-width:22px; padding:1px 7px; border-radius:999px;
                     background:#ede9fe; color:#5b21b6; font-size:13px; font-weight:700; text-align:center; }
    .ghl-tag { display:inline-block; margin-left:6px; padding:1px 6px; border-radius:4px;
               background:#ede9fe; color:#5b21b6; font-size:11px; font-weight:700; letter-spacing:.02em; }
    .docs-ok { color:#065f46; font-weight:600; font-size:13px; }
    .docs-missing { color:#b45309; font-weight:600; font-size:13px; }
    .review-note { margin-top:4px; font-size:12px; color:#b45309; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { margin:0; }
    .btn-approve { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .btn-approve:hover { background:#a7f3d0; }
    .empty { text-align:center; padding:28px 12px; color:#6b7280; }
    @media (max-width:640px) {
        .card-header { flex-direction:column; }
    }
</style>
@endpush

@push('scripts')
<script>
window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?' + String.fromCharCode(10) + '(This is shown in the Errors list.)', '');
    if (note === null) return;            // cancelled
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
