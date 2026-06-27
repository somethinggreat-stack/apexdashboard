@extends('layouts.admin')

@php
    $isIg         = $channel === 'instagram';
    $channelLabel = \App\Models\ProspectLead::CHANNELS[$channel] ?? 'WhatsApp';
    $numberLabel  = $channel === 'phone' ? 'Phone Number' : 'WhatsApp Number (Verified)';
    $numberCol    = $channel === 'phone' ? 'Phone' : 'WhatsApp (Verified)';
    $keyOf        = fn ($lead) => $isIg ? $lead->instagram_key : $lead->whatsapp_digits;
    $flaggedCount = $leads->filter(fn ($l) => $keyOf($l) && in_array((string) $keyOf($l), $dupKeys, true))->count();
    $dupStatLabel = $isIg ? 'Duplicate Links' : 'Duplicate Numbers';
    $cols         = $isIg ? 5 : 6;
@endphp

@section('title', 'Prospect ' . $channelLabel . ' Leads')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>Prospect {{ $channelLabel }} Leads <span class="lead-count-badge">{{ $leads->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                @if ($isIg)
                    Quick list of {{ $channelLabel }} leads — name and Instagram link.
                @else
                    Quick list of {{ $channelLabel }} leads — name, a verified {{ $channelLabel }} number, and optional Instagram.
                @endif
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
            <div class="lead-stat-label">{{ $dupStatLabel }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                @unless ($isIg)<th>{{ $numberCol }}</th>@endunless
                <th>Instagram</th>
                <th>Hot Lead</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                @php $isDup = $keyOf($lead) && in_array((string) $keyOf($lead), $dupKeys, true); @endphp
                <tr class="{{ $isDup ? 'lead-row-dup' : '' }}">
                    <td>
                        <strong>{{ $lead->name }}</strong>
                        @if ($isDup)
                            <span class="dup-flag" title="This {{ $isIg ? 'Instagram link' : 'number' }} is on more than one lead">⚠ Duplicate</span>
                        @endif
                    </td>
                    @unless ($isIg)
                        <td>
                            @if ($lead->whatsapp_digits)
                                @if ($channel === 'phone')
                                    <a href="tel:+{{ $lead->whatsapp_digits }}" class="wa-link">{{ $lead->whatsapp }}</a>
                                @else
                                    <a href="https://wa.me/{{ $lead->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $lead->whatsapp }}</a>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    @endunless
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
                            <button type="submit" class="hot-flag {{ $lead->hot_lead ? 'hot-on' : 'hot-off' }}" title="Click to toggle Hot Lead">
                                {{ $lead->hot_lead ? '🔥 Hot Lead' : 'Mark Hot' }}
                            </button>
                        </form>
                    </td>
                    <td class="no-link muted">{{ $lead->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick="openLeadMove({{ $lead->id }}, @js($lead->name))">
                            Move &rarr;
                        </button>
                        <button type="button" class="btn btn-sm"
                                onclick="openLeadEdit({{ $lead->id }}, @js($lead->name), @js($lead->whatsapp), @js($lead->instagram))">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.prospect-leads.destroy', $lead) }}" style="display:inline"
                              onsubmit="return confirm('Remove {{ addslashes($lead->name) }} from leads?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="empty">No {{ $channelLabel }} leads yet — add the first one.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Keys already on file, for the live add-form duplicate check --}}
<script id="existingKeys" type="application/json">@json($existingKeys)</script>

{{-- Add lead --}}
<div id="createLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add {{ $channelLabel }} Lead</h3>
            <button class="modal-close" onclick="closeModal('createLeadModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.prospect-leads.store') }}">
            @csrf
            <input type="hidden" name="channel" value="{{ $channel }}">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            @if ($isIg)
                <div class="form-group">
                    <label>Instagram Link *</label>
                    <input type="text" name="instagram" id="add-key-input" value="{{ old('instagram') }}" placeholder="instagram.com/handle" required>
                    <div id="add-dup-warning" class="dup-warning" style="display:none;">⚠ This Instagram link already exists — duplicate not allowed.</div>
                </div>
            @else
                <div class="form-group">
                    <label>{{ $numberLabel }}</label>
                    <input type="text" name="whatsapp" id="add-key-input" value="{{ old('whatsapp') }}" placeholder="{{ $numberLabel }}">
                    <div id="add-dup-warning" class="dup-warning" style="display:none;">⚠ This number already exists in WhatsApp/Phone leads — duplicate not allowed.</div>
                </div>
                <div class="form-group">
                    <label>Instagram Link <span class="muted">(optional)</span></label>
                    <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="instagram.com/handle">
                </div>
            @endif
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="add-submit">Add Lead</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit lead --}}
<div id="editLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit {{ $channelLabel }} Lead</h3>
            <button class="modal-close" onclick="closeModal('editLeadModal')">&times;</button>
        </div>
        <form method="POST" id="editLeadForm">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="el-name" required>
            </div>
            @unless ($isIg)
                <div class="form-group">
                    <label>{{ $numberLabel }}</label>
                    <input type="text" name="whatsapp" id="el-number" placeholder="{{ $numberLabel }}">
                </div>
            @endunless
            <div class="form-group">
                <label>Instagram Link @unless ($isIg)<span class="muted">(optional)</span>@else *@endunless</label>
                <input type="text" name="instagram" id="el-instagram" placeholder="instagram.com/handle" @if ($isIg) required @endif>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Move lead -> in Contact (same channel) --}}
<div id="moveLeadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Move to {{ $channelLabel }} in Contact</h3>
            <button class="modal-close" onclick="closeModal('moveLeadModal')">&times;</button>
        </div>
        <form method="POST" id="moveLeadForm">
            @csrf
            <p class="muted" style="font-size:13px; margin:0 0 14px;">
                Moving <strong id="ml-name"></strong> into your {{ $channelLabel }} pipeline.
            </p>
            @unless ($isIg)
                <div class="form-group">
                    <label>Reached Out Via <span class="muted">({{ $channelLabel }} number you used)</span></label>
                    <input type="text" name="outreach_whatsapp" id="ml-outreach" placeholder="{{ $numberLabel }}">
                </div>
            @endunless
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
                <button type="submit" class="btn btn-primary">Move to {{ $channelLabel }} in Contact</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .wa-link { color:#16a34a; font-weight:600; white-space:nowrap; }
    .wa-link:hover { text-decoration:underline; }
    .lead-count-badge { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#dbeafe; color:#1e40af; font-size:13px; font-weight:700; vertical-align:middle; }
    .lead-stats { display:flex; gap:12px; flex-wrap:wrap; margin:14px 0 4px; }
    .lead-stat { flex:1; min-width:140px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; }
    .lead-stat-num { font-size:24px; font-weight:800; color:#0f172a; line-height:1.1; }
    .lead-stat-label { font-size:12px; color:#64748b; font-weight:600; margin-top:2px; }
    .lead-stat-warn { background:#fef2f2; border-color:#fecaca; }
    .lead-stat-warn .lead-stat-num { color:#b91c1c; }
    .dup-flag { display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; white-space:nowrap; }
    .lead-row-dup { background:#fff7f7; }
    .hot-flag { border:0; cursor:pointer; border-radius:999px; font-size:11px; font-weight:700; padding:3px 10px; white-space:nowrap; }
    .hot-on  { background:#fee2e2; color:#b91c1c; }
    .hot-on:hover  { background:#fecaca; }
    .hot-off { background:#f1f5f9; color:#64748b; }
    .hot-off:hover { background:#e2e8f0; }
    .dup-warning { color:#b91c1c; font-size:12px; margin-top:5px; font-weight:600; }
    .input-dup { border-color:#ef4444 !important; background:#fff5f5; }
</style>
@endpush

@push('scripts')
<script>
var LEAD_CHANNEL = @json($channel);

window.openLeadMove = function (id, name) {
    document.getElementById('moveLeadForm').action = "{{ url('admin/prospect-leads') }}/" + id + "/move";
    document.getElementById('ml-name').textContent = name || '';
    var out = document.getElementById('ml-outreach'); if (out) out.value = '';
    document.getElementById('ml-status').value = 'contacted';
    document.getElementById('ml-notes').value = '';
    openModal('moveLeadModal');
};

window.openLeadEdit = function (id, name, whatsapp, instagram) {
    document.getElementById('editLeadForm').action = "{{ url('admin/prospect-leads') }}/" + id;
    document.getElementById('el-name').value = name || '';
    var num = document.getElementById('el-number'); if (num) num.value = whatsapp || '';
    document.getElementById('el-instagram').value = instagram || '';
    openModal('editLeadModal');
};

// Live duplicate check on the Add form.
(function () {
    var existing = [];
    try { existing = JSON.parse(document.getElementById('existingKeys').textContent) || []; } catch (e) {}
    var input = document.getElementById('add-key-input');
    var warn = document.getElementById('add-dup-warning');
    var submit = document.getElementById('add-submit');
    if (!input) return;
    function keyOf(v) {
        v = v || '';
        if (LEAD_CHANNEL === 'instagram') {
            return v.toLowerCase().trim().replace(/\?.*$/, '').replace(/\/+$/, '');
        }
        return v.replace(/\D/g, '');
    }
    input.addEventListener('input', function () {
        var k = keyOf(input.value);
        var dup = k !== '' && existing.indexOf(k) !== -1;
        warn.style.display = dup ? '' : 'none';
        input.classList.toggle('input-dup', dup);
        if (submit) submit.disabled = dup;
    });
})();

// Re-open the add modal if a submission bounced back with validation errors.
@if ($errors->any())
    if (typeof openModal === 'function') openModal('createLeadModal');
@endif
</script>
@endpush
@endsection
