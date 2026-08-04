@extends('layouts.client')

@section('title', $lead->name ?: 'Lead')

@section('topbar-content')
    <div class="page-actions">
        <a href="{{ route('client.leads.index') }}" class="btn btn-secondary page-action-btn">← Back</a>
    </div>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $lead->name ?: 'Lead' }}</h2>
        <span class="lead-status lead-status--{{ $lead->status }}">{{ $lead->statusLabel() }}</span>
    </div>
    <p class="muted" style="margin:6px 0 18px; font-size:13px;">
        Added {{ $lead->created_at?->format('M j, Y g:ia') }}@if($lead->updated_at && $lead->updated_at->ne($lead->created_at)) · updated {{ $lead->updated_at->format('M j, Y g:ia') }}@endif
    </p>

    {{-- Full view is an editable form: change any field or the status and save. --}}
    <form method="POST" action="{{ route('client.leads.update', $lead->id) }}">
        @csrf @method('PUT')
        <div class="info-grid">
            <div class="form-group"><label>Source</label><input type="text" name="source" value="{{ old('source', $lead->source) }}" maxlength="120"></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" value="{{ old('name', $lead->name) }}" maxlength="150"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="{{ old('email', $lead->email) }}" maxlength="255"></div>
            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="{{ old('phone', $lead->phone) }}" maxlength="40"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Models\BusinessLead::STATUSES as $k => $label)
                        <option value="{{ $k }}" @selected($lead->status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" rows="6" maxlength="5000">{{ old('notes', $lead->notes) }}</textarea></div>

        <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

    <div style="margin-top:18px; padding-top:16px; border-top:1px solid #eef1f6;">
        <form method="POST" action="{{ route('client.leads.destroy', $lead->id) }}"
              data-confirm-delete
              data-confirm-title="Delete this lead?"
              data-confirm-message="{{ $lead->name ?: 'This lead' }} will be permanently removed.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete Lead</button>
        </form>
    </div>
</div>

@push('head')
<style>
    .lead-status { padding:5px 12px; border-radius:999px; border:1px solid #e2e8f0; font-size:12.5px; font-weight:700; }
    .lead-status--new        { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .lead-status--contacted  { color:#4338ca; background:#eef2ff; border-color:#c7d2fe; }
    .lead-status--interested { color:#047857; background:#ecfdf5; border-color:#a7f3d0; }
    .lead-status--follow_up  { color:#b45309; background:#fffbeb; border-color:#fde68a; }
    .lead-status--converted  { color:#065f46; background:#d1fae5; border-color:#6ee7b7; }
    .lead-status--lost       { color:#64748b; background:#f1f5f9; border-color:#e2e8f0; }
    .btn-sm { padding:6px 12px; font-size:12.5px; }
</style>
@endpush
@endsection
