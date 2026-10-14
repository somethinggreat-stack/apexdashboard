@extends('layouts.admin-pro')

@section('title', 'Inactive')
@section('subtitle', 'Clients who are gone or not paying — kept out of the workflow and the EOD.')

@section('content')
<div class="pro-panel" data-page-refresh>
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#a8a29e,#57534e);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
            </span>
            <h2>Inactive</h2>
            <span class="pro-panel-count">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        Gone or not-paying clients. They're hidden from every other list and never appear in the EOD.
        Click <strong>Reactivate</strong> if they come back — they return to whichever list they came from.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Inactive Since</th>
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
                                <span class="pro-avatar" style="background:#e7e5e4; color:#57534e;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <div>
                                    <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                                    @include('admin.end-users.partials.round-limit-badge')
                                </div>
                            </div>
                        </td>
                        <td>{{ $eu->inactive_at?->format('M j, Y g:ia') ?: '—' }}</td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ $eu->phone ?: '—' }}</td>
                        <td><span class="move-reason">{{ $eu->move_reason ?: '—' }}</span></td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                <form method="POST" data-inplace action="{{ route('admin.end-users.reactivate', $eu->id) }}"
                                      data-confirm-action
                                      data-confirm-title="Reactivate?"
                                      data-confirm-message="{{ $eu->full_name }} goes back into whichever list they came from and shows in the EOD again."
                                      data-confirm-ok="Reactivate">
                                    @csrf
                                    <button class="pro-act done">Reactivate</button>
                                </form>

                                <form method="POST" data-inplace action="{{ route('admin.end-users.destroy', $eu) }}"
                                      data-confirm-delete data-confirm-message="Delete {{ $eu->full_name }} and all their documents? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No inactive clients.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
