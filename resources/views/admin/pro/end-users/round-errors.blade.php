@extends('layouts.admin-pro')

@section('title', 'Round Errors')
@section('subtitle', 'Clients past round 1, pulled out with an import / later-round problem.')

@section('content')
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#fb923c,#ea580c);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12a9.5 9.5 0 1 0 2.8-6.7"/><polyline points="2.5 4 2.5 8 6.5 8"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="15.5" x2="12" y2="15.5"/></svg>
            </span>
            <h2>Round Errors</h2>
            <span class="pro-panel-count danger">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        These clients hit a problem after their 1st round (e.g. an import error when starting a later round).
        Fix the issue, then <strong>Resolve</strong> to send them back into the Clients list.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Error Type</th>
                    <th>Reason</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#ffedd5; color:#c2410c;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            </div>
                        </td>
                        <td><span class="re-type">{{ $eu->error_type ?: '—' }}</span></td>
                        <td><span class="re-reason">{{ $eu->intake_review_note ?: '—' }}</span></td>
                        <td>{{ $eu->email }}</td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                <form method="POST" action="{{ route('admin.end-users.resolve-round-error', $eu->id) }}"
                                      onsubmit="return confirm('Resolve {{ addslashes($eu->full_name) }} and move them back to the Clients list?')">
                                    @csrf
                                    <button class="pro-act done">Resolve → Clients</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                      onsubmit="return confirm(@js('Delete client ' . $eu->full_name . ' and all their documents? This cannot be undone.'))">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No round errors — all clients are on track.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .re-type { display:inline-block; padding:3px 10px; border-radius:999px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12.5px; font-weight:600; }
    .re-reason { display:inline-block; max-width:360px; color:#b45309; font-size:13px; white-space:pre-wrap; word-break:break-word; }
</style>
@endpush
@endsection
