@extends('layouts.admin')

@section('title', 'Users & Activity')

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Users</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                VAs can work on all business owners (New Clients, Errors, Clients, Messages, Today's Queue) but can't see payments or leads.
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')">+ Add User</button>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><strong>{{ $u->full_name }}</strong></td>
                    <td>{{ $u->email }}</td>
                    <td>
                        @if ($u->isSuper())
                            <span class="role-badge role-super">Super Admin</span>
                        @elseif ($u->isLeads())
                            <span class="role-badge role-leads">Leads Agent</span>
                        @else
                            <span class="role-badge role-va">VA</span>
                        @endif
                    </td>
                    <td class="no-link">
                        <div class="u-actions">
                            <form method="POST" action="{{ route('admin.users.password', $u->id) }}" class="pw-form">
                                @csrf @method('PUT')
                                <input type="hidden" name="password" value="">
                                <button type="button" class="btn btn-sm" onclick="resetPw(this, '{{ addslashes($u->full_name) }}')">Reset Password</button>
                            </form>
                            @if (!$u->isSuper() && $u->id !== Auth::guard('admin')->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($u->full_name) }}? They will no longer be able to log in.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No users yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Activity Log</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">Showing activity from the last 30 minutes.</p>
        </div>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr><th>When</th><th>User</th><th>Action</th><th>Business Owner</th><th>IP</th></tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="muted" style="white-space:nowrap;">{{ $log->created_at?->format('M j, Y g:ia') }}</td>
                    <td><strong>{{ $log->admin?->full_name ?? '—' }}</strong></td>
                    <td>{{ $log->description ?: $log->action }} <span class="muted" style="font-size:11px;">{{ $log->method }} {{ $log->path }}</span></td>
                    <td class="muted">{{ $log->subject ?: '—' }}</td>
                    <td class="muted">{{ $log->ip ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No activity yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

{{-- Add user --}}
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add User (VA)</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" value="{{ old('full_name') }}" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="form-group"><label>Password *</label><input type="text" name="password" minlength="10" required></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .role-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .role-super { background:#ede9fe; color:#5b21b6; }
    .role-va { background:#e0f2fe; color:#075985; }
    .role-leads { background:#dcfce7; color:#166534; }
    .u-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .u-actions form { display:inline; margin:0; }
    .u-actions .btn { white-space:nowrap; }
</style>
@endpush

@push('scripts')
<script>
window.resetPw = function (btn, name) {
    var pw = prompt('Set a new password for ' + name + ' (min 10 characters):', '');
    if (pw === null) return;
    if (pw.length < 10) { alert('Password must be at least 10 characters.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="password"]').value = pw;
    form.submit();
};
@if ($errors->any())
    if (typeof openModal === 'function') openModal('addUserModal');
@endif
</script>
@endpush
@endsection
