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
                <label>Status</label>
                <select name="status">
                    <option value="active"   @selected(old('status', $client->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $client->status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Round Timeline</label>
                <select name="round_cycle_days">
                    <option value="30" @selected(old('round_cycle_days', $client->round_cycle_days ?? 30) == 30)>30-Day Rounds (standard)</option>
                    <option value="20" @selected(old('round_cycle_days', $client->round_cycle_days ?? 30) == 20)>20-Day Rounds (accelerated)</option>
                </select>
                <small class="muted" style="display:block; margin-top:4px;">Applies to all of this owner's clients — next-round dates, days-left and step reminders adjust immediately.</small>
            </div>
            @include('admin.clients._referral-fields', ['refClient' => $client])
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
@endsection
