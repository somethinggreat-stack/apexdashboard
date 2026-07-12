@extends('layouts.admin-pro')

@section('title', 'New Clients')
@section('subtitle', 'Intake submissions waiting for review.')

@section('content')

{{-- Intake link + API key are super-admin only. VAs also render this view now
     (Controller::adminView), so the whole sensitive block is gated behind
     $isSuper. VAs still see the New-Clients review list further below. --}}
@php $isSuper = Auth::guard('admin')->user()?->isSuper(); @endphp
@if ($isSuper)
@if ($client->intake_external_url)
    <div class="pro-panel" style="margin-bottom:20px; padding:22px;">
        <div class="pro-panel-title" style="margin-bottom:6px;">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
            </span>
            <h2>External Intake (API)</h2>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            {{ $client->business_name }} collects clients on their own form
            (<a href="{{ $client->intake_external_url }}" target="_blank" rel="noopener">{{ $client->intake_external_url }}</a>),
            which posts to our API. Submissions appear below in <strong>New Clients</strong>.
        </p>
        <div class="api-field">
            <label>API Endpoint</label>
            <div class="api-row"><input type="text" value="{{ url('/api/intake') }}" readonly onclick="this.select();"></div>
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
    <div class="pro-panel" style="margin-bottom:20px; padding:22px;">
        <div class="pro-panel-title" style="margin-bottom:6px;">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
            </span>
            <h2>Secure Intake Link</h2>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            Share this private link with {{ $client->business_name }}. When a client fills it out they appear below in
            <strong>New Clients</strong> with everything they submitted — review, then move them to In Progress.
        </p>
        <div class="api-row">
            <input type="text" id="intakeLink" value="{{ $client->intakeUrl() }}" readonly onclick="this.select();">
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
    <div class="pro-panel" style="margin-bottom:20px; padding:22px;">
        <div class="pro-panel-title" style="margin-bottom:6px;">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <h2>API Access <span class="muted" style="font-size:13px; font-weight:500;">(optional — for a BO's own funnel)</span></h2>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            An external funnel can POST client submissions to this API and they land in <strong>New Clients</strong>.
            The secure intake link above stays active — a BO can use both at once.
        </p>
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
@endif {{-- $isSuper: intake link + API key --}}

<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </span>
            <h2>New Clients</h2>
            <span class="pro-panel-count danger">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
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
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#e0e7ff; color:#4338ca;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <div>
                                    <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                                    @if ($eu->intake_review_note)
                                        <div class="pro-note">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $eu->email }}</td>
                        <td>{{ $eu->phone ?: '—' }}</td>
                        <td>{{ $eu->intake_submitted_at?->format('M j, Y g:ia') ?: '—' }}</td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Review</a>

                                <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                      onsubmit="return confirm(@js('Are you sure you want to move ' . $eu->full_name . ' to In Progress?'))">
                                    @csrf
                                    <button class="pro-act done">Move to In Progress</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form">
                                    @csrf
                                    <input type="hidden" name="note" value="">
                                    <button type="button" class="pro-act warn" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu->id) }}"
                                      onsubmit="return confirm(@js('Delete ' . $eu->full_name . ' and all their uploaded documents? This cannot be undone.'))">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No new intake submissions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .api-field { margin-top:12px; }
    .api-field label { display:block; font-size:12px; font-weight:600; color:var(--pro-text-soft); margin-bottom:5px; }
    .api-field code { background:var(--pro-line-soft); padding:1px 5px; border-radius:4px; font-size:11.5px; }
    .api-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .api-row input {
        flex:1; min-width:280px; padding:11px 13px; border:1px solid var(--pro-line); border-radius:10px;
        font-family:Menlo,Consolas,monospace; font-size:12.5px; background:var(--pro-line-soft); color:var(--pro-text);
    }
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
        try {
            navigator.clipboard.writeText(f.value).then(function () {
                btn.textContent = 'Copied ✓';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            });
        } catch (e) { document.execCommand('copy'); }
    });
}
wireCopy('copyIntake', 'intakeLink');
wireCopy('copyKey', 'apiKey');

window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?\n(This is shown in the Errors list.)', '');
    if (note === null) return;
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
@endsection
