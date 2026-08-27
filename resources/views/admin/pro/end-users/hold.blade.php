@extends('layouts.admin-pro')

@section('title', 'Hold / Pause')
@section('subtitle', 'Clients parked out of the workflow until you resume them.')

@section('content')
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#94a3b8,#64748b);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
            </span>
            <h2>Hold / Pause</h2>
            <span class="pro-panel-count">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        These clients are paused and hidden from New Clients, Errors, In Progress and Clients.
        Click <strong>Resume</strong> to drop them straight back into whichever list they came from.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Held Since</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#e2e8f0; color:#475569;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            </div>
                        </td>
                        <td>{{ $eu->held_at?->format('M j, Y g:ia') ?: '—' }}</td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ $eu->phone ?: '—' }}</td>
                        <td><span class="move-reason">{{ $eu->move_reason ?: '—' }}</span></td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                <form method="POST" action="{{ route('admin.end-users.resume', $eu->id) }}">
                                    @csrf
                                    <button class="pro-act done">Resume</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                      data-confirm-delete data-confirm-message="Delete {{ $eu->full_name }} and all their documents? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No clients on hold.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
