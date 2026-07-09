@extends('layouts.admin-pro')

@section('title', 'Errors')
@section('subtitle', 'Clients pulled out of the main list because something needs fixing.')

@section('content')
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#f87171,#ef4444);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </span>
            <h2>Errors</h2>
            <span class="pro-panel-count danger">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        Bad login, missing document, billing, and so on. Fix the issue, then
        <strong>Move to In Progress</strong> to put them back — or send them to
        <strong>New Clients</strong> for a fresh review.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Error</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#fee2e2; color:#b91c1c;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            </div>
                        </td>
                        <td><span style="color:#dc2626; font-weight:600;">{{ $eu->intake_review_note ?: '—' }}</span></td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ $eu->phone ?: '—' }}</td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Review</a>

                                <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                      onsubmit="return confirm(@js('Are you sure you want to move ' . $eu->full_name . ' to In Progress?'))">
                                    @csrf
                                    <button class="pro-act done">Move to In Progress</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.to-new-clients', $eu->id) }}">
                                    @csrf
                                    <button class="pro-act move">Move to New Clients</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                      onsubmit="return confirm(@js('Delete ' . $eu->full_name . ' and all their documents? This cannot be undone.'))">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
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
</div>
@endsection
