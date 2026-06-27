@extends('layouts.admin')

@section('title', 'Prospect Leads')

@section('content')
@php
    $flaggedCount = $leads->filter(fn ($l) => $l->whatsapp_digits && in_array($l->whatsapp_digits, $dupNumbers, true))->count();
@endphp
<div class="card">
    <div class="card-header">
        <div>
            <h2>Prospect Leads <span class="lead-count-badge">{{ $leads->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Quick list of leads — name, a verified WhatsApp number, and optional socials.
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('createLeadModal')">+ Add Lead</button>
    </div>

    <div class="lead-stats">
        <div class="lead-stat">
            <div class="lead-stat-num">{{ $leads->count() }}</div>
            <div class="lead-stat-label">Total Leads</div>
        </div>
        <div class="lead-stat">
            <div class="lead-stat-num">{{ $leads->where('created_at', '>=', now()->startOfMonth())->count() }}</div>
            <div class="lead-stat-label">Added This Month</div>
        </div>
        <div class="lead-stat">
            <div class="lead-stat-num">{{ $leads->where('created_at', '>=', now()->startOfDay())->count() }}</div>
            <div class="lead-stat-label">Added Today</div>
        </div>
        <div class="lead-stat {{ $flaggedCount > 0 ? 'lead-stat-warn' : '' }}">
            <div class="lead-stat-num">{{ $flaggedCount }}</div>
            <div class="lead-stat-label">Duplicate Numbers</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>WhatsApp (Verified)</th>
                <th>Instagram</th>
                <th>Hot Lead</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                @php $isDup = $lead->whatsapp_digits && in_array($lead->whatsapp_digits, $dupNumbers, true); @endphp
                <tr class="{{ $isDup ? 'lead-row-dup' : '' }}">
                    <td>
                        <strong>{{ $lead->name }}</strong>
                        @if ($isDup)
                            <span class="dup-flag" title="This WhatsApp number is on more than one lead">⚠ Duplicate</span>
                        @endif
                    </td>
                    <td>
                        @if ($lead->whatsapp_digits)
                            <a href="https://wa.me/{{ $lead->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $lead->whatsapp }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($lead->instagram)
                            <a href="{{ $lead->linkHref($lead->instagram) }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($lead->instagram, 36) }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="no-link">
                        <form method="POST" action="{{ route('admin.prospect-leads.toggle-hot', $lead) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="hot-flag {{ $lead->hot_lead ? 'hot-on' : 'hot-off' }}"
                                    title="Click to toggle Hot Lead">
                                {{ $lead->hot_lead ? '🔥 Hot Lead' : 'Mark Hot' }}
                            </button>
                        </form>
                    </td>
                    <td class="no-link muted">{{ $lead->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick="openLeadMove({{ $lead->id }}, @js($lead->name), @js($lead->whatsapp))">
                            Move &rarr;
                        </button>
                        <button type="button" class="btn btn-sm"
                                onclick="openLeadEdit({{ $lead->id }}, @js($lead->name), @js($lead->whatsapp), @js($lead->instagram))">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.prospect-leads.destroy', $lead) }}" style="display:inline"
                              onsubmit="return confirm('Remove {{ addslashes($lead->name) }} from prospect leads?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No prospect leads yet — add the first one.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add lead --}}
<div id="createLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Prospect Lead</h3>
            <button class="modal-close" onclick="closeModal('createLeadModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.prospect-leads.store') }}">
            @csrf
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label>WhatsApp Number (Verified)</label>
                <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="WhatsApp number">
            </div>
            <div class="form-group">
                <label>Instagram Link <span class="muted">(optional)</span></label>
                <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="instagram.com/handle">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Lead</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit lead (populated via JS) --}}
<div id="editLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Prospect Lead</h3>
            <button class="modal-close" onclick="closeModal('editLeadModal')">&times;</button>
        </div>
        <form method="POST" id="editLeadForm">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="el-name" required>
            </div>
            <div class="form-group">
                <label>WhatsApp Number (Verified)</label>
                <input type="text" name="whatsapp" id="el-whatsapp" placeholder="WhatsApp number">
            </div>
            <div class="form-group">
                <label>Instagram Link <span class="muted">(optional)</span></label>
                <input type="text" name="instagram" id="el-instagram" placeholder="instagram.com/handle">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Move lead -> Prospects in Contact --}}
<div id="moveLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Move to Prospects in Contact</h3>
            <button class="modal-close" onclick="closeModal('moveLeadModal')">&times;</button>
        </div>
        <form method="POST" id="moveLeadForm">
            @csrf
            <p class="muted" style="font-size:13px; margin:0 0 14px;">
                Moving <strong id="ml-name"></strong> (<span id="ml-whatsapp"></span>) into your active pipeline.
                Their Instagram link is saved into the discussion comments.
            </p>
            <div class="form-group">
                <label>Reached Out Via <span class="muted">(WhatsApp number you used)</span></label>
                <input type="text" name="outreach_whatsapp" id="ml-outreach" placeholder="+1 469 905 8587">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="ml-status">
                    @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($key === 'contacted')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Discussion / Comments</label>
                <textarea name="notes" id="ml-notes" rows="4" placeholder="What was discussed, next steps…"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('moveLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Move to Prospects</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .wa-link { color:#16a34a; font-weight:600; white-space:nowrap; }
    .wa-link:hover { text-decoration:underline; }
    .lead-count-badge {
        display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px;
        background:#dbeafe; color:#1e40af; font-size:13px; font-weight:700; vertical-align:middle;
    }
    .lead-stats { display:flex; gap:12px; flex-wrap:wrap; margin:14px 0 4px; }
    .lead-stat {
        flex:1; min-width:140px; background:#f8fafc; border:1px solid #e2e8f0;
        border-radius:10px; padding:12px 16px;
    }
    .lead-stat-num { font-size:24px; font-weight:800; color:#0f172a; line-height:1.1; }
    .lead-stat-label { font-size:12px; color:#64748b; font-weight:600; margin-top:2px; }
    .lead-stat-warn { background:#fef2f2; border-color:#fecaca; }
    .lead-stat-warn .lead-stat-num { color:#b91c1c; }
    .dup-flag {
        display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px;
        background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; white-space:nowrap;
    }
    .lead-row-dup { background:#fff7f7; }
    .hot-flag { border:0; cursor:pointer; border-radius:999px; font-size:11px; font-weight:700; padding:3px 10px; white-space:nowrap; }
    .hot-on  { background:#fee2e2; color:#b91c1c; }
    .hot-on:hover  { background:#fecaca; }
    .hot-off { background:#f1f5f9; color:#64748b; }
    .hot-off:hover { background:#e2e8f0; }
</style>
@endpush

@push('scripts')
<script>
window.openLeadMove = function (id, name, whatsapp) {
    document.getElementById('moveLeadForm').action = "{{ url('admin/prospect-leads') }}/" + id + "/move";
    document.getElementById('ml-name').textContent = name || '';
    document.getElementById('ml-whatsapp').textContent = whatsapp || 'no WhatsApp on file';
    document.getElementById('ml-outreach').value = '';
    document.getElementById('ml-status').value = 'contacted';
    document.getElementById('ml-notes').value = '';
    openModal('moveLeadModal');
};

window.openLeadEdit = function (id, name, whatsapp, instagram) {
    document.getElementById('editLeadForm').action = "{{ url('admin/prospect-leads') }}/" + id;
    document.getElementById('el-name').value = name || '';
    document.getElementById('el-whatsapp').value = whatsapp || '';
    document.getElementById('el-instagram').value = instagram || '';
    openModal('editLeadModal');
};

// Re-open the add modal if a submission bounced back with validation errors.
@if ($errors->any())
    if (typeof openModal === 'function') openModal('createLeadModal');
@endif
</script>
@endpush
@endsection
