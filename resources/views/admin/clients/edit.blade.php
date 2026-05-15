@extends('layouts.admin')

@section('title', 'Edit ' . $client->business_name)

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.clients.update', $client) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <h3 class="profile-section-head">Business Owner</h3>

        <div class="form-row">
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="business_name" value="{{ old('business_name', $client->business_name) }}" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Password (leave blank to keep)</label>
                <input type="password" name="password" minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $client->phone) }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Monthly Fee ($)</label>
                <input type="number" step="0.01" name="monthly_fee" value="{{ old('monthly_fee', $client->monthly_fee) }}" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active"   @selected(old('status', $client->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $client->status) === 'inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <h3 class="profile-section-head">Intake Form Branding</h3>
        <p class="muted" style="font-size:12px; margin-bottom:14px;">
            These fields control how the public intake form is presented to end clients. The business owner's name is never shown on the form — only the logo and the display name below.
        </p>

        <div class="form-row">
            <div class="form-group">
                <label>Display Name on Form</label>
                <input type="text" name="intake_display_name"
                       value="{{ old('intake_display_name', $client->intake_display_name) }}"
                       placeholder="Defaults to: {{ $client->business_name }}" maxlength="255">
                <small class="muted">Leave blank to use the business name. Shown directly under the logo.</small>
            </div>
            <div class="form-group">
                <label>Intake Logo</label>
                @if ($logoUrl = $client->intakeLogoUrl())
                    <div style="margin-bottom:8px;">
                        <img src="{{ $logoUrl }}" alt="" style="max-height:60px; max-width:200px; border:1px solid #E2E8F0; border-radius:6px; padding:6px; background:#fff;">
                    </div>
                @endif
                <input type="file" name="intake_logo" accept=".png,.jpg,.jpeg,.webp,.svg">
                <small class="muted">PNG / JPG / WebP / SVG. Max 2 MB. Replaces the current logo on save.</small>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
@endsection
