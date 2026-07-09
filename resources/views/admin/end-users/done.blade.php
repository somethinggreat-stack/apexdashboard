@extends('layouts.admin')

@section('title', 'Clients Done')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Clients Done <span class="done-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Finished clients. They still count for Payments — move one back to
                <strong>In Progress</strong> if there's more work to do.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Round</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td>{{ $eu->current_round }}{{ $eu->current_round == 1 ? 'st' : ($eu->current_round == 2 ? 'nd' : ($eu->current_round == 3 ? 'rd' : 'th')) }} Round</td>
                    <td><span class="pill pill-done">Done</span></td>
                    <td class="no-link">
                        <div class="row-actions">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                            <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}" style="display:inline"
                                  onsubmit="return confirm('Move {{ addslashes($eu->full_name) }} back to In Progress?')">
                                @csrf
                                <button class="btn btn-sm btn-back">Move to In Progress</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form" style="display:inline">
                                @csrf
                                <input type="hidden" name="note" value="">
                                <button type="button" class="btn btn-sm btn-toerror" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}" style="display:inline"
                                  onsubmit="return confirm('Delete {{ addslashes($eu->full_name) }} and all their documents? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No finished clients yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .done-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#dcfce7; color:#166534; font-size:13px; font-weight:700; vertical-align:middle; }
    .pill-done { background:#dcfce7; color:#166534; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; padding:5px 11px; font-size:12px; line-height:1.3; }
    .btn-back { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }
    .btn-back:hover { background:#bae6fd; }
    .btn-toerror { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .btn-toerror:hover { background:#fde68a; }
</style>
@endpush

@push('scripts')
<script>
window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?\n(This is shown in the Errors list.)', '');
    if (note === null) return;
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
@endsection
