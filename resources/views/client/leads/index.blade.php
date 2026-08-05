@extends('layouts.client')

@section('title', 'New Leads')

@section('topbar-action')
    <button type="button" class="btn btn-primary" onclick="openModal('addLeadModal')">+ Add Lead</button>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h2>New Leads</h2>
    </div>
    <p class="muted" style="margin:8px 0 16px; font-size:13px;">
        Track your own leads here — add a lead, update its status as you work it, and open any lead for the full details. Only you can see these.
    </p>

    @if ($leads->isEmpty())
        <div class="empty">No leads yet — click <strong>Add Lead</strong> to add your first one.</div>
    @else
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Source</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leads as $lead)
                        <tr>
                            <td><strong>{{ $lead->name ?: '—' }}</strong></td>
                            <td>{{ $lead->source ?: '—' }}</td>
                            <td>{{ $lead->email ?: '—' }}</td>
                            <td>{{ $lead->phone ?: '—' }}</td>
                            <td>
                                {{-- Inline status change — updates immediately. --}}
                                <form method="POST" action="{{ route('client.leads.update', $lead->id) }}" style="margin:0;">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="_status_only" value="1">
                                    <select name="status" class="lead-status lead-status--{{ $lead->status }}" onchange="this.form.submit()">
                                        @foreach (\App\Models\BusinessLead::STATUSES as $k => $label)
                                            <option value="{{ $k }}" @selected($lead->status === $k)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="muted">{{ $lead->created_at?->format('M j, Y') }}</td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="{{ route('client.leads.show', $lead->id) }}" class="btn btn-sm">View</a>
                                <form method="POST" action="{{ route('client.leads.destroy', $lead->id) }}" style="display:inline;"
                                      data-confirm-delete
                                      data-confirm-title="Delete this lead?"
                                      data-confirm-message="{{ $lead->name ?: 'This lead' }} will be permanently removed.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Add Lead modal --}}
<div id="addLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Lead</h3>
            <button type="button" class="modal-close" onclick="closeModal('addLeadModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('client.leads.store') }}">
            @csrf
            <div class="form-group"><label>Source</label><input type="text" name="source" maxlength="120" placeholder="e.g. Referral, Instagram, Walk-in"></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" maxlength="150"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" maxlength="255"></div>
            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" maxlength="40"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Models\BusinessLead::STATUSES as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" rows="4" maxlength="5000"></textarea></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Lead</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .lead-status { padding:5px 10px; border-radius:999px; border:1px solid #e2e8f0; font-size:12.5px; font-weight:700; cursor:pointer; background:#fff; }
    .lead-status--new          { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .lead-status--message_sent { color:#0369a1; background:#e0f2fe; border-color:#bae6fd; }
    .lead-status--contacted    { color:#4338ca; background:#eef2ff; border-color:#c7d2fe; }
    .lead-status--interested { color:#047857; background:#ecfdf5; border-color:#a7f3d0; }
    .lead-status--follow_up  { color:#b45309; background:#fffbeb; border-color:#fde68a; }
    .lead-status--converted  { color:#065f46; background:#d1fae5; border-color:#6ee7b7; }
    .lead-status--lost       { color:#64748b; background:#f1f5f9; border-color:#e2e8f0; }
    .btn-sm { padding:6px 12px; font-size:12.5px; }
</style>
@endpush
@endsection
