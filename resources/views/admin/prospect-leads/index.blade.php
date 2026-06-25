@extends('layouts.admin')

@section('title', 'Prospect Leads')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>Prospect Leads</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Quick list of leads — name, a verified WhatsApp number, and optional socials.
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('createLeadModal')">+ Add Lead</button>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>WhatsApp (Verified)</th>
                <th>Instagram</th>
                <th>Website</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td><strong>{{ $lead->name }}</strong></td>
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
                    <td>
                        @if ($lead->website)
                            <a href="{{ $lead->linkHref($lead->website) }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($lead->website, 36) }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="no-link muted">{{ $lead->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <button type="button" class="btn btn-sm"
                                onclick="openLeadEdit({{ $lead->id }}, @js($lead->name), @js($lead->whatsapp), @js($lead->instagram), @js($lead->website))">
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
                <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+1 469 905 8587">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Instagram Link <span class="muted">(optional)</span></label>
                    <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="instagram.com/handle">
                </div>
                <div class="form-group">
                    <label>Website Link <span class="muted">(optional)</span></label>
                    <input type="text" name="website" value="{{ old('website') }}" placeholder="example.com">
                </div>
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
                <input type="text" name="whatsapp" id="el-whatsapp" placeholder="+1 469 905 8587">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Instagram Link <span class="muted">(optional)</span></label>
                    <input type="text" name="instagram" id="el-instagram" placeholder="instagram.com/handle">
                </div>
                <div class="form-group">
                    <label>Website Link <span class="muted">(optional)</span></label>
                    <input type="text" name="website" id="el-website" placeholder="example.com">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .wa-link { color:#16a34a; font-weight:600; white-space:nowrap; }
    .wa-link:hover { text-decoration:underline; }
</style>
@endpush

@push('scripts')
<script>
window.openLeadEdit = function (id, name, whatsapp, instagram, website) {
    document.getElementById('editLeadForm').action = "{{ url('admin/prospect-leads') }}/" + id;
    document.getElementById('el-name').value = name || '';
    document.getElementById('el-whatsapp').value = whatsapp || '';
    document.getElementById('el-instagram').value = instagram || '';
    document.getElementById('el-website').value = website || '';
    openModal('editLeadModal');
};

// Re-open the add modal if a submission bounced back with validation errors.
@if ($errors->any())
    if (typeof openModal === 'function') openModal('createLeadModal');
@endif
</script>
@endpush
@endsection
