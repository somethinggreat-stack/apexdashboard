@extends('layouts.client')

@section('title', 'New Clients')

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h2 style="margin:0;">New Clients</h2>
        <a href="{{ route('client.end-users.create') }}" class="btn btn-primary">+ Add New Client</a>
    </div>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">
        New client submissions appear here for our team to review — or add one yourself.
        Once reviewed, they move into <strong>In Progress</strong>.
    </p>
</div>

<div class="card">
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Submitted</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td>
                        <strong>{{ $eu->full_name }}</strong>
                        @if ($eu->intake_review_note)
                            <div class="review-note">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                        @endif
                    </td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="muted">{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    <td><span class="pill-pending">Pending review</span></td>
                    <td class="no-link"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No new clients yet — add one to get started.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .pill-pending { display:inline-block; padding:2px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; }
    .review-note { margin-top:4px; font-size:12px; color:#b91c1c; font-weight:600; }
</style>
@endpush
@endsection
