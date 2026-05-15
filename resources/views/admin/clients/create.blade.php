@extends('layouts.admin')

@section('title', 'Add Business Client')

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.clients.store') }}">
        @csrf
        <div class="form-group">
            <label>Business Name</label>
            <input type="text" name="business_name" value="{{ old('business_name') }}" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}">
        </div>
        <div class="form-group">
            <label>Monthly Fee ($)</label>
            <input type="number" step="0.01" name="monthly_fee" value="{{ old('monthly_fee', '149.00') }}">
        </div>
        <div class="form-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create</button>
        </div>
    </form>
</div>
@endsection
