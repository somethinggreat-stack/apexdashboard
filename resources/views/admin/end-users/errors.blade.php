@extends('layouts.admin')

@section('title', 'Errors')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Errors <span class="err-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Clients pulled out of the main list because something needs fixing (bad login, missing doc, etc.).
                Fix the issue, then <strong>Move to Clients</strong> to put them back.
            </p>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Error</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td class="err-note">{{ $eu->intake_review_note ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Review</a>
                            <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                  onsubmit="return confirm('Fixed? Move {{ addslashes($eu->full_name) }} back into Clients?')">
                                @csrf
                                <button class="btn btn-sm btn-fix">Move to Clients</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.to-new-clients', $eu->id) }}" style="display:inline">
                                @csrf
                                <button class="btn btn-sm btn-tonew">Move to New Clients</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                  onsubmit="return confirm('Delete {{ addslashes($eu->full_name) }} and all their documents? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No error clients — all good.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('head')
<style>
    .err-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:13px; font-weight:700; vertical-align:middle; }
    .err-note { max-width:360px; color:#b91c1c; font-weight:600; font-size:13px; white-space:pre-wrap; word-break:break-word; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; padding:5px 11px; font-size:12px; line-height:1.3; }
    .btn-fix { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .btn-fix:hover { background:#a7f3d0; }
    .btn-tonew { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }
    .btn-tonew:hover { background:#bae6fd; }
</style>
@endpush
@endsection
