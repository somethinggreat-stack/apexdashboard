@extends('layouts.admin')

@section('title', 'New Clients')

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div>
            <h2>Secure Intake Link</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Share this private link with {{ $client->business_name }}. When a client fills it out, they appear below in
                <strong>New Clients</strong> with everything they submitted — review, then Approve to move them into Clients.
            </p>
        </div>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="text" id="intakeLink" value="{{ $client->intakeUrl() }}" readonly
               style="flex:1; min-width:280px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-family:Menlo,Consolas,monospace; font-size:12.5px; background:#f8fafc; color:#0f172a;"
               onclick="this.select();">
        <button type="button" class="btn btn-primary" id="copyIntake">Copy</button>
        <a href="{{ $client->intakeUrl() }}" target="_blank" class="btn btn-secondary">Open</a>
        <form method="POST" action="{{ route('admin.new-clients.regenerate') }}" style="display:inline"
              onsubmit="return confirm('Regenerate the link? The current link stops working immediately.')">
            @csrf
            <button type="submit" class="btn btn-danger">Regenerate</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>New Clients <span class="pending-badge">{{ $endUsers->count() }}</span></h2>
    </div>

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
                    <td class="no-link">
                        <div class="row-actions">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Review</a>
                            <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                  onsubmit="return confirm('Approve {{ addslashes($eu->full_name) }} and move to Clients?')">
                                @csrf
                                <button class="btn btn-sm btn-approve">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.destroy', $eu->id) }}"
                                  onsubmit="return confirm('Delete {{ addslashes($eu->full_name) }} and all their uploaded documents? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
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
    .pending-badge { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:13px; font-weight:700; vertical-align:middle; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; padding:5px 11px; font-size:12px; line-height:1.3; }
    .btn-approve { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .btn-approve:hover { background:#a7f3d0; }
</style>
@endpush

@push('scripts')
<script>
document.getElementById('copyIntake')?.addEventListener('click', function () {
    var f = document.getElementById('intakeLink');
    f.select(); f.setSelectionRange(0, 99999);
    var btn = this;
    try {
        navigator.clipboard.writeText(f.value).then(function () {
            btn.textContent = 'Copied ✓';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
        });
    } catch (e) { document.execCommand('copy'); }
});
</script>
@endpush
@endsection
