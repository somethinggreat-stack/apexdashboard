@extends($adminLayout ?? 'layouts.admin')

@php
    $isIg         = $channel === 'instagram';
    $channelLabel = \App\Models\Prospect::CHANNELS[$channel] ?? 'WhatsApp';
    $numberLabel  = $channel === 'phone' ? 'Phone Number' : 'WhatsApp Number';
    $clientCol    = $channel === 'phone' ? 'Client Phone' : 'Client WhatsApp';
    $cols         = $isIg ? 6 : 7;
@endphp

@section('title', 'Prospect ' . $channelLabel . ' in Contact')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>{{ $channelLabel }} Leads in Contact <span class="lead-count-badge">{{ $prospects->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                {{ $channelLabel }} leads you're actively talking to â€” track where each conversation stands.
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('createProspectModal')">+ Add Lead</button>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                @if ($isIg)
                    <th>Instagram</th>
                @else
                    <th>{{ $clientCol }}</th>
                    <th>Reached Out Via</th>
                @endif
                <th>Status</th>
                <th>Discussion / Comments</th>
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
                    @if ($isIg)
                        <td>
                            @if ($prospect->instagram)
                                <a href="{{ \Illuminate\Support\Str::startsWith($prospect->instagram, ['http']) ? $prospect->instagram : 'https://' . ltrim($prospect->instagram, '/') }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($prospect->instagram, 36) }}</a>
                            @else
                                <span class="muted">â€”</span>
                            @endif
                        </td>
                    @else
                        <td>
                            @if ($prospect->whatsapp_digits)
                                @if ($channel === 'phone')
                                    <a href="tel:+{{ $prospect->whatsapp_digits }}" class="wa-link">{{ $prospect->whatsapp }}</a>
                                @else
                                    <a href="https://wa.me/{{ $prospect->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->whatsapp }}</a>
                                @endif
                            @else
                                <span class="muted">â€”</span>
                            @endif
                        </td>
                        <td>
                            @if ($prospect->outreach_whatsapp_digits)
                                @if ($channel === 'phone')
                                    <a href="tel:+{{ $prospect->outreach_whatsapp_digits }}" class="wa-link">{{ $prospect->outreach_whatsapp }}</a>
                                @else
                                    <a href="https://wa.me/{{ $prospect->outreach_whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->outreach_whatsapp }}</a>
                                @endif
                            @else
                                <span class="muted">â€”</span>
                            @endif
                        </td>
                    @endif
                    <td><span class="prospect-pill prospect-pill-{{ $prospect->status }}">{{ $prospect->status_label }}</span></td>
                    <td class="prospect-notes">{{ $prospect->notes ?: 'â€”' }}</td>
                    <td class="no-link muted">{{ $prospect->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <button type="button" class="btn btn-sm"
                                    onclick="openProspectEdit({{ $prospect->id }}, @js($prospect->name), @js($prospect->whatsapp), @js($prospect->outreach_whatsapp), @js($prospect->instagram), @js($prospect->referred_by), @js($prospect->status), @js($prospect->notes))">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.prospects.mark-interested', $prospect) }}"
                                  onsubmit="return confirm('Move {{ addslashes($prospect->name) }} to Interested Leads?')">
                                @csrf
                                <button class="btn btn-sm btn-interested" title="Move to Interested Leads">Interested</button>
                            </form>
                            <form method="POST" action="{{ route('admin.prospects.mark-lost', $prospect) }}"
                                  onsubmit="return confirm('Move {{ addslashes($prospect->name) }} to Lost Leads?')">
                                @csrf
                                <button class="btn btn-sm btn-lost" title="Move to Lost Leads">Lost</button>
                            </form>
                            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}"
                                  onsubmit="return confirm('Remove {{ addslashes($prospect->name) }} from leads?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="empty">No {{ $channelLabel }} leads in contact yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

{{-- Add prospect --}}
<div id="createProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add {{ $channelLabel }} Lead</h3>
            <button class="modal-close" onclick="closeModal('createProspectModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.prospects.store') }}">
            @csrf
            <input type="hidden" name="channel" value="{{ $channel }}">
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                @if ($isIg)
                    <div class="form-group">
                        <label>Instagram Link</label>
                        <input type="text" name="instagram" value="{{ old('instagram') }}" placeholder="instagram.com/handle">
                    </div>
                @else
                    <div class="form-group">
                        <label>Client {{ $numberLabel }}</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="{{ $numberLabel }}">
                    </div>
                @endif
            </div>
            <div class="form-row">
                @unless ($isIg)
                    <div class="form-group">
                        <label>{{ $channelLabel }} Number Used to Reach Out</label>
                        <input type="text" name="outreach_whatsapp" value="{{ old('outreach_whatsapp') }}" placeholder="{{ $numberLabel }}">
                    </div>
                @endunless
                <div class="form-group">
                    <label>Referred By <span class="muted">(optional)</span></label>
                    <input type="text" name="referred_by" value="{{ old('referred_by') }}" placeholder="Who referred them?">
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', 'contacted') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Discussion / Comments</label>
                <textarea name="notes" rows="4" placeholder="What was discussed, objections, next stepsâ€¦">{{ old('notes') }}</textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProspectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Prospect</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit prospect --}}
<div id="editProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit {{ $channelLabel }} Lead</h3>
            <button class="modal-close" onclick="closeModal('editProspectModal')">&times;</button>
        </div>
        <form method="POST" id="editProspectForm">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="ep-name" required>
                </div>
                @if ($isIg)
                    <div class="form-group">
                        <label>Instagram Link</label>
                        <input type="text" name="instagram" id="ep-instagram" placeholder="instagram.com/handle">
                    </div>
                @else
                    <div class="form-group">
                        <label>Client {{ $numberLabel }}</label>
                        <input type="text" name="whatsapp" id="ep-whatsapp" placeholder="{{ $numberLabel }}">
                    </div>
                @endif
            </div>
            <div class="form-row">
                @unless ($isIg)
                    <div class="form-group">
                        <label>{{ $channelLabel }} Number Used to Reach Out</label>
                        <input type="text" name="outreach_whatsapp" id="ep-outreach_whatsapp" placeholder="{{ $numberLabel }}">
                    </div>
                @endunless
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
                <label>Discussion / Comments</label>
                <textarea name="notes" id="ep-notes" rows="5" placeholder="What was discussed, objections, next stepsâ€¦"></textarea>
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
    .lead-count-badge { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#dbeafe; color:#1e40af; font-size:13px; font-weight:700; vertical-align:middle; }
    .prospect-notes { max-width: 360px; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #475569; line-height: 1.45; }
    .prospect-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.2px; white-space:nowrap; }
    .prospect-pill-new           { background:#e0f2fe; color:#075985; }
    .prospect-pill-contacted     { background:#ede9fe; color:#5b21b6; }
    .prospect-pill-in_discussion { background:#fef3c7; color:#92400e; }
    .prospect-pill-follow_up     { background:#fae8ff; color:#86198f; }
    .prospect-pill-interested    { background:#ccfbf1; color:#0f766e; }
    .prospect-pill-won           { background:#d1fae5; color:#065f46; }
    .prospect-pill-lost          { background:#fee2e2; color:#991b1b; }
    .wa-link { color:#16a34a; font-weight:600; white-space:nowrap; }
    .wa-link:hover { text-decoration:underline; }
    .btn-lost { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .btn-lost:hover { background:#fde68a; }
    .btn-interested { background:#ccfbf1; color:#0f766e; border:1px solid #99f6e4; }
    .btn-interested:hover { background:#99f6e4; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; padding:5px 11px; font-size:12px; line-height:1.3; }
</style>
@endpush

@push('scripts')
<script>
window.openProspectEdit = function (id, name, whatsapp, outreachWhatsapp, instagram, referredBy, status, notes) {
    document.getElementById('editProspectForm').action = "{{ url('admin/prospects') }}/" + id;
    document.getElementById('ep-name').value = name || '';
    var wa = document.getElementById('ep-whatsapp'); if (wa) wa.value = whatsapp || '';
    var ow = document.getElementById('ep-outreach_whatsapp'); if (ow) ow.value = outreachWhatsapp || '';
    var ig = document.getElementById('ep-instagram'); if (ig) ig.value = instagram || '';
    document.getElementById('ep-referred_by').value = referredBy || '';
    document.getElementById('ep-status').value = status || 'new';
    document.getElementById('ep-notes').value = notes || '';
    openModal('editProspectModal');
};

@if ($errors->any())
    if (typeof openModal === 'function') openModal('createProspectModal');
@endif
</script>
@endpush
@endsection
