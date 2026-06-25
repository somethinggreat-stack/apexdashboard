@extends('layouts.admin')

@section('title', 'Prospects')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>Prospects</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Prospective business owners who aren't signed yet — track who you've talked to and where the conversation stands.
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('createProspectModal')">+ Add Prospect</button>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>WhatsApp</th>
                <th>Status</th>
                <th>Discussion / Notes</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prospects as $prospect)
                <tr>
                    <td>
                        <strong>{{ $prospect->name }}</strong>
                        @if ($prospect->referred_by)
                            <div class="muted" style="font-size:12px; margin-top:2px;">Referred by {{ $prospect->referred_by }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($prospect->phone)
                            <a href="tel:{{ $prospect->phone }}">{{ $prospect->phone }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($prospect->whatsapp_digits)
                            <a href="https://wa.me/{{ $prospect->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->whatsapp }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td><span class="prospect-pill prospect-pill-{{ $prospect->status }}">{{ $prospect->status_label }}</span></td>
                    <td class="prospect-notes">{{ $prospect->notes ?: '—' }}</td>
                    <td class="no-link muted">{{ $prospect->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <button type="button" class="btn btn-sm"
                                onclick="openProspectEdit({{ $prospect->id }}, @js($prospect->name), @js($prospect->phone), @js($prospect->whatsapp), @js($prospect->referred_by), @js($prospect->status), @js($prospect->notes))">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" style="display:inline"
                              onsubmit="return confirm('Remove {{ addslashes($prospect->name) }} from prospects?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No prospects yet — add the first one to start tracking.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add prospect --}}
<div id="createProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Prospect</h3>
            <button class="modal-close" onclick="closeModal('createProspectModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.prospects.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="(555) 123-4567">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+1 469 905 8587">
                </div>
                <div class="form-group">
                    <label>Referred By <span class="muted">(optional)</span></label>
                    <input type="text" name="referred_by" value="{{ old('referred_by') }}" placeholder="Who referred them?">
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', 'new') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Discussion / Notes</label>
                <textarea name="notes" rows="4" placeholder="What was discussed, objections, next steps…">{{ old('notes') }}</textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProspectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Prospect</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit prospect (populated via JS) --}}
<div id="editProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Prospect</h3>
            <button class="modal-close" onclick="closeModal('editProspectModal')">&times;</button>
        </div>
        <form method="POST" id="editProspectForm">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="ep-name" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="ep-phone" placeholder="(555) 123-4567">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" id="ep-whatsapp" placeholder="+1 469 905 8587">
                </div>
                <div class="form-group">
                    <label>Referred By <span class="muted">(optional)</span></label>
                    <input type="text" name="referred_by" id="ep-referred_by" placeholder="Who referred them?">
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="ep-status">
                    @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Discussion / Notes</label>
                <textarea name="notes" id="ep-notes" rows="5" placeholder="What was discussed, objections, next steps…"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProspectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .prospect-notes {
        max-width: 360px; white-space: pre-wrap; word-break: break-word;
        font-size: 13px; color: #475569; line-height: 1.45;
    }
    .prospect-pill {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700; letter-spacing: .2px; white-space: nowrap;
    }
    .prospect-pill-new           { background: #e0f2fe; color: #075985; }
    .prospect-pill-contacted     { background: #ede9fe; color: #5b21b6; }
    .prospect-pill-in_discussion { background: #fef3c7; color: #92400e; }
    .prospect-pill-follow_up     { background: #fae8ff; color: #86198f; }
    .prospect-pill-won           { background: #d1fae5; color: #065f46; }
    .prospect-pill-lost          { background: #fee2e2; color: #991b1b; }
    .wa-link { color: #16a34a; font-weight: 600; white-space: nowrap; }
    .wa-link:hover { text-decoration: underline; }
</style>
@endpush

@push('scripts')
<script>
window.openProspectEdit = function (id, name, phone, whatsapp, referredBy, status, notes) {
    document.getElementById('editProspectForm').action = "{{ url('admin/prospects') }}/" + id;
    document.getElementById('ep-name').value = name || '';
    document.getElementById('ep-phone').value = phone || '';
    document.getElementById('ep-whatsapp').value = whatsapp || '';
    document.getElementById('ep-referred_by').value = referredBy || '';
    document.getElementById('ep-status').value = status || 'new';
    document.getElementById('ep-notes').value = notes || '';
    openModal('editProspectModal');
};

// Re-open the add modal if a submission bounced back with validation errors.
@if ($errors->any())
    if (typeof openModal === 'function') openModal('createProspectModal');
@endif
</script>
@endpush
@endsection
