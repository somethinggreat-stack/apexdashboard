@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'My Profile')

@php
    $roleLabel = $admin->isSuper() ? 'Super Admin' : ($admin->isLeads() ? 'Leads Agent' : 'VA');
    $roleClass = $admin->isSuper() ? 'role-super' : ($admin->isLeads() ? 'role-leads' : 'role-va');
    $parts = preg_split('/\s+/', trim($admin->full_name));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
@endphp

@section('content')
<div class="pf-wrap">
    <div class="pf-hero card">
        <div class="pf-avatar">{{ $initials ?: 'A' }}</div>
        <div>
            <h2 class="pf-name">{{ $admin->full_name }}</h2>
            <div class="pf-meta">
                <span class="role-badge {{ $roleClass }}">{{ $roleLabel }}</span>
                <span class="pf-email">{{ $admin->email }}</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.profile.update') }}" class="card pf-card" autocomplete="off">
        @csrf @method('PUT')

        <div class="pf-section">
            <h3>Account Details</h3>
            <p class="muted">Your name and the email you sign in with.</p>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" value="{{ old('full_name', $admin->full_name) }}" required maxlength="255">
                @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="{{ old('email', $admin->email) }}" required maxlength="255">
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="pf-section">
            <h3>Change Password</h3>
            <p class="muted">Leave these blank to keep your current password.</p>

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" autocomplete="current-password" placeholder="Only needed to set a new password">
                @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" minlength="10" autocomplete="new-password" placeholder="At least 10 characters">
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" placeholder="Re-type new password">
                </div>
            </div>
        </div>

        <div class="form-actions pf-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

@push('head')
<style>
    .pf-wrap { max-width: 720px; }
    .pf-hero { display:flex; align-items:center; gap:16px; padding:20px 22px; margin-bottom:16px; }
    .pf-avatar {
        width:58px; height:58px; border-radius:16px; flex:0 0 auto;
        display:flex; align-items:center; justify-content:center;
        font-weight:800; font-size:20px; color:#fff; letter-spacing:.5px;
        background:linear-gradient(135deg,#6366f1,#4338ca);
        box-shadow:0 6px 16px rgba(67,56,202,.35);
    }
    .pf-name { margin:0 0 6px; font-size:20px; }
    .pf-meta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .pf-email { color:var(--pro-muted, #64748b); font-size:13px; }
    .pf-card { padding:6px 22px 20px; }
    .pf-section { padding:18px 0; border-bottom:1px solid var(--pro-line, #e6ebf2); }
    .pf-section:last-of-type { border-bottom:none; }
    .pf-section h3 { margin:0 0 2px; font-size:15px; }
    .pf-section .muted { margin:0 0 14px; font-size:12.5px; }
    .pf-actions { padding-top:6px; }
    .role-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .role-super { background:#ede9fe; color:#5b21b6; }
    .role-va { background:#e0f2fe; color:#075985; }
    .role-leads { background:#dcfce7; color:#166534; }
    .field-error { display:block; margin-top:5px; color:#dc2626; font-size:12px; }
    :root[data-theme="dark"] .pf-email { color:#94a3b8; }
    :root[data-theme="dark"] .pf-section { border-color:var(--pro-line); }
</style>
@endpush
@endsection
