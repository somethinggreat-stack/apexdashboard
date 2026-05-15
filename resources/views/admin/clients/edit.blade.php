@extends('layouts.admin')

@section('title', 'Edit ' . $client->business_name)

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.clients.update', $client) }}">
        @csrf @method('PUT')
        <div class="form-group">
            <label>Business Name</label>
            <input type="text" name="business_name" value="{{ old('business_name', $client->business_name) }}" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email', $client->email) }}" required>
        </div>
        <div class="form-group">
            <label>Password (leave blank to keep)</label>
            <input type="password" name="password" minlength="6">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $client->phone) }}">
        </div>
        <div class="form-group">
            <label>Monthly Fee ($)</label>
            <input type="number" step="0.01" name="monthly_fee" value="{{ old('monthly_fee', $client->monthly_fee) }}" required>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" @selected(old('status', $client->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $client->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="form-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
@endsection
