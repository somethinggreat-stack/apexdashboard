@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Edit ' . $client->business_name)

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.clients.update', $client) }}">
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
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600;">
                    <input type="checkbox" name="referred_by_chantal" value="1" style="width:18px; height:18px;" {{ old('referred_by_chantal', $client->referred_by_chantal) ? 'checked' : '' }}>
                    <span>Referred by Chantal <span class="muted" style="font-weight:400;">— she earns $5 for each client payment of this business owner</span></span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
@endsection
