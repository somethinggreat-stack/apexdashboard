@extends('layouts.admin')

@section('title', 'New Clients')

@section('content')
{{-- Intake link + API key are sensitive — super admin only. VAs still see/manage the New Clients list below. --}}
@php $isSuper = Auth::guard('admin')->user()?->isSuper(); @endphp
@if ($isSuper)
@if ($client->intake_external_url)
    <div class="card" style="margin-bottom:18px;">
        <div class="card-header">
            <div>
                <h2>External Intake (API)</h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">
                    {{ $client->business_name }} collects clients on their own form
                    (<a href="{{ $client->intake_external_url }}" target="_blank" rel="noopener">{{ $client->intake_external_url }}</a>),
                    which posts to our API. Submissions appear below in <strong>New Clients</strong>.
                </p>
            </div>
        </div>
        <div class="api-field">
            <label>API Endpoint</label>
            <div class="api-row">
                <input type="text" value="{{ url('/api/intake') }}" readonly onclick="this.select();">
            </div>
        </div>
        <div class="api-field">
            <label>API Key <span class="muted">(send as header <code>X-Intake-Key</code> — keep secret)</span></label>
            <div class="api-row">
                <input type="text" id="apiKey" value="{{ $client->intake_api_key }}" readonly onclick="this.select();">
                <button type="button" class="btn btn-primary" id="copyKey">Copy</button>
            </div>
        </div>
    </div>
@else
    <div class="card" style="margin-bottom:18px;">
        <div class="card-header">
            <div>
                <h2>Secure Intake Link</h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">
                    Share this private link with {{ $client->business_name }}. When a client fills it out, they appear below in
                    <strong>New Clients</strong> with everything they submitted — review, then Approve to move them into Clients.
                </p>
            </div>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="text" id="intakeLink" value="{{ $client->intakeUrl() }}" readonly
                   style="flex:1; min-width:280px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-family:Menlo,Consolas,monospace; font-size:12.5px; background:#f8fafc; color:#0f172a;"
                   onclick="this.select();">
            <button type="button" class="btn btn-primary" id="copyIntake">Copy</button>
            <a href="{{ $client->intakeUrl() }}" target="_blank" class="btn btn-secondary">Open</a>
            <form method="POST" action="{{ route('admin.new-clients.regenerate') }}" style="display:inline"
                  onsubmit="return confirm('Regenerate the link? The current link stops working immediately.')">
                @csrf
                <button type="submit" class="btn btn-danger">Regenerate</button>
            </form>
        </div>
    </div>
@endif

@unless ($client->intake_external_url)
    <div class="card" style="margin-bottom:18px;">
        <div class="card-header">
            <div>
                <h2>API Access <span class="muted" style="font-size:13px; font-weight:500;">(optional — for a BO's own funnel/site)</span></h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">
                    An external funnel can POST client submissions to this API and they land in <strong>New Clients</strong>.
                    The secure intake link above stays active — a BO can use both at once.
                </p>
            </div>
        </div>
        <div class="api-field">
            <label>API Endpoint <span class="muted">(POST · multipart/form-data)</span></label>
            <div class="api-row"><input type="text" value="{{ url('/api/intake') }}" readonly onclick="this.select();"></div>
        </div>
        @if ($client->intake_api_key)
            <div class="api-field">
                <label>API Key <span class="muted">(send as header <code>X-Intake-Key</code> — keep secret)</span></label>
                <div class="api-row">
                    <input type="text" id="apiKey" value="{{ $client->intake_api_key }}" readonly onclick="this.select();">
                    <button type="button" class="btn btn-primary" id="copyKey">Copy</button>
                    <form method="POST" action="{{ route('admin.new-clients.api-key') }}" style="display:inline"
                          onsubmit="return confirm('Regenerate the API key? The current key stops working immediately.')">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Regenerate</button>
                    </form>
                </div>
            </div>
        @else
            <div class="api-field">
                <form method="POST" action="{{ route('admin.new-clients.api-key') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Generate API Key</button>
                </form>
            </div>
        @endif
    </div>
@endunless
@endif {{-- $isSuper --}}

<div class="card">
    <div class="card-header">
        <h2>New Clients <span class="pending-badge">{{ $endUsers->count() }}</span></h2>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td>
                        <strong>{{ $eu->full_name }}</strong>
                        @if ($eu->intake_review_note)
                            <div class="review-note">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                        @endif
                    </td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="muted">{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Review</a>
                            <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                  onsubmit="return confirm(@js('Are you sure you want to move ' . $eu->full_name . ' to In Progress?'))">
                                @csrf
                                <button class="btn btn-sm btn-approve">Move to In Progress</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form">
                                @csrf
                                <input type="hidden" name="note" value="">
                                <button type="button" class="btn btn-sm btn-toerror" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                            </form>
                            <form method="POST" action="{{ route('admin.end-users.destroy', $eu->id) }}"
                                  data-confirm-delete
                                  data-confirm-title="Delete this client?"
                                  data-confirm-message="{{ $eu->full_name }} and all their uploaded documents will be moved to the Recycle Bin. This cannot be undone from here.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No new intake submissions yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .pending-badge { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:13px; font-weight:700; vertical-align:middle; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; padding:5px 11px; font-size:12px; line-height:1.3; }
    .btn-approve { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .btn-approve:hover { background:#a7f3d0; }
    .btn-toerror { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .btn-toerror:hover { background:#fde68a; }
    .review-note { margin-top:4px; font-size:12px; color:#b91c1c; font-weight:600; max-width:320px; }
    .api-field { margin-top:12px; }
    .api-field label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:5px; }
    .api-field code { background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:11.5px; }
    .api-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .api-row input { flex:1; min-width:280px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-family:Menlo,Consolas,monospace; font-size:12.5px; background:#f8fafc; color:#0f172a; }
</style>
@endpush

@push('scripts')
<script>
function wireCopy(btnId, inputId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
        var f = document.getElementById(inputId);
        f.select(); f.setSelectionRange(0, 99999);
        var after = function () {
            btn.textContent = 'Copied ✓';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            if (window.apexToast) window.apexToast('Copied to clipboard', 'success');
        };
        try {
            navigator.clipboard.writeText(f.value).then(after, function () { document.execCommand('copy'); after(); });
        } catch (e) { document.execCommand('copy'); after(); }
    });
}
wireCopy('copyIntake', 'intakeLink');
wireCopy('copyKey', 'apiKey');

window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?\n(This is shown in the Errors list.)', '');
    if (note === null) return;            // cancelled
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
@endsection
