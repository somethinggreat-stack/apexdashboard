@extends('layouts.client')

@section('title', 'Errors Resolved by You')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Errors Resolved by You <span class="re-count re-count-green">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                You've updated the login for these — they're now with our team to finish the round. No further action
                needed from you; they'll move back into <strong>Done Clients</strong> once processed.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Error Type</th>
                <th>Resolved On</th>
                <th>Status</th>
                <th>Email</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td><span class="re-type">{{ $eu->error_type ?: '—' }}</span></td>
                    <td class="muted">{{ $eu->error_resolved_by_client_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    <td><span class="re-resolved">✓ With our team</span></td>
                    <td>{{ $eu->email }}</td>
                    <td class="no-link" style="text-align:right;"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Nothing here yet. When you resolve a round error, it shows up here while our team finishes it.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .re-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#ffedd5; color:#c2410c; font-size:13px; font-weight:700; vertical-align:middle; }
    .re-count-green { background:#dcfce7; color:#166534; }
    .re-type { display:inline-block; padding:2px 9px; border-radius:999px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12.5px; font-weight:600; }
    .re-resolved { display:inline-block; padding:2px 10px; border-radius:999px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-size:12.5px; font-weight:700; }
</style>
@endpush
@endsection
