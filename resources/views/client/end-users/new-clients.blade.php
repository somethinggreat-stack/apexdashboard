@extends('layouts.client')

@section('title', 'New Clients')

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h2 style="margin:0;">New Clients</h2>
    </div>
    <p class="muted" style="margin:8px 0 0; font-size:13px;">
        @if ($client->intake_external_url)
            New clients arrive from your onboarding form
            (<a href="{{ $client->intake_external_url }}" target="_blank" rel="noopener">{{ $client->intake_external_url }}</a>).
        @else
            New clients who complete your secure intake form appear here.
        @endif
        Review each submission, then <strong>Approve</strong> to move them into My Clients.
    </p>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="muted">{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    <td>
                        <div class="nc-actions">
                            <a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">Review</a>
                            <form method="POST" action="{{ route('client.new-clients.approve', $eu->id) }}"
                                  onsubmit="return confirm('Approve {{ addslashes($eu->full_name) }} and move to My Clients?')">
                                @csrf
                                <button class="btn btn-sm btn-primary">Approve</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No new intake submissions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('head')
<style>
    .nc-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .nc-actions form { display:inline; margin:0; }
    .nc-actions .btn { white-space:nowrap; }
</style>
@endpush
@endsection
