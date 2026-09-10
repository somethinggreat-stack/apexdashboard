@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Credentials')

@section('content')

{{-- ============ CRM / software logins ============ --}}
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Credentials</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                {{ $client->business_name }}&rsquo;s CRM / software logins &mdash; GoHighLevel, dispute software, email inbox, and the like.
            </p>
        </div>
        <button class="btn btn-primary" onclick="credOpen()">+ Add Credential</button>
    </div>

    @if ($credentials->isEmpty())
        <p class="empty" style="padding:26px 0; text-align:center;">
            No logins saved yet. Click <strong>Add Credential</strong> to store this owner&rsquo;s first CRM / software login.
        </p>
    @else
        <div class="cred-grid">
            @foreach ($credentials as $c)
                <div class="cred-card">
                    <div class="cred-top">
                        <div class="cred-name">{{ $c->software_name }}</div>
                        <div class="cred-tools">
                            <button type="button" class="cred-icon" title="Edit"
                                onclick="credEdit({{ Illuminate\Support\Js::from([
                                    'id' => $c->id, 'software_name' => $c->software_name,
                                    'email' => $c->email, 'password' => $c->password, 'notes' => $c->notes,
                                ]) }})">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.credentials.destroy', $c->id) }}"
                                  data-confirm-delete data-confirm-message="Delete {{ $c->software_name }}? This can't be undone.">
                                @csrf @method('DELETE')
                                <button class="cred-icon cred-danger" title="Delete">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="cred-rows">
                        @if ($c->email)
                            <div class="cred-row"><span class="cred-k">Email</span><span class="cred-v">{{ $c->email }}</span><button type="button" class="cred-copy" onclick="credCopy(this, @js($c->email))">Copy</button></div>
                        @endif
                        @if ($c->password)
                            <div class="cred-row">
                                <span class="cred-k">Password</span>
                                <span class="cred-v cred-secret" data-shown="0"><span class="cred-dots">••••••••</span><span class="cred-plain" hidden>{{ $c->password }}</span></span>
                                <button type="button" class="cred-copy" onclick="credReveal(this)">Show</button>
                                <button type="button" class="cred-copy" onclick="credCopy(this, @js($c->password))">Copy</button>
                            </div>
                        @endif
                        @if ($c->notes)
                            <div class="cred-note">{{ $c->notes }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ============ Links & resources (name + link only) ============ --}}
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Links &amp; Resources</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Just a name and a link &mdash; Google Sheets, Jotform, shared folders. No login needed.
            </p>
        </div>
        <button class="btn btn-primary" onclick="linkOpen()">+ Add Link</button>
    </div>

    @if ($links->isEmpty())
        <p class="empty" style="padding:26px 0; text-align:center;">
            No links saved yet. Click <strong>Add Link</strong> to save a Google Sheet, Jotform, or any other link.
        </p>
    @else
        <div class="cred-grid">
            @foreach ($links as $l)
                <div class="cred-card">
                    <div class="cred-top">
                        <div class="cred-name">{{ $l->software_name }}</div>
                        <div class="cred-tools">
                            <button type="button" class="cred-icon" title="Edit"
                                onclick="linkEdit({{ Illuminate\Support\Js::from([
                                    'id' => $l->id, 'software_name' => $l->software_name, 'login_url' => $l->login_url, 'notes' => $l->notes,
                                ]) }})">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.credentials.destroy', $l->id) }}"
                                  data-confirm-delete data-confirm-message="Delete {{ $l->software_name }}? This can't be undone.">
                                @csrf @method('DELETE')
                                <button class="cred-icon cred-danger" title="Delete">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <a class="cred-link" href="{{ Str::startsWith($l->login_url, ['http://','https://']) ? $l->login_url : 'https://'.$l->login_url }}" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Open link
                    </a>

                    @if ($l->notes)
                        <div class="cred-rows"><div class="cred-note">{{ $l->notes }}</div></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Add / Edit Credential (CRM login) --}}
<div id="credModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="credModalTitle">Add Credential</h3>
            <button class="modal-close" onclick="closeModal('credModal')">&times;</button>
        </div>
        <form method="POST" id="credForm" action="{{ route('admin.credentials.store') }}">
            @csrf
            <input type="hidden" name="_method" id="credMethod" value="POST">
            <input type="hidden" name="type" value="credential">
            <div class="form-group">
                <label>CRM / Software Name *</label>
                <input type="text" name="software_name" id="credSoftware" maxlength="120" placeholder="GoHighLevel, Dispute Panda, Gmail inbox…" value="{{ old('type') !== 'link' ? old('software_name') : '' }}" required>
            </div>
            <div class="form-group"><label>Email</label><input type="text" name="email" id="credEmail" maxlength="255" value="{{ old('email') }}"></div>
            <div class="form-group"><label>Password</label><input type="text" name="password" id="credPassword" maxlength="1000" value="{{ old('password') }}"></div>
            <div class="form-group"><label>Notes (optional)</label><textarea name="notes" id="credNotes" rows="2" maxlength="2000" placeholder="2FA phone, security answers, anything else">{{ old('type') !== 'link' ? old('notes') : '' }}</textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('credModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Add / Edit Link (name + link only) --}}
<div id="linkModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="linkModalTitle">Add Link</h3>
            <button class="modal-close" onclick="closeModal('linkModal')">&times;</button>
        </div>
        <form method="POST" id="linkForm" action="{{ route('admin.credentials.store') }}">
            @csrf
            <input type="hidden" name="_method" id="linkMethod" value="POST">
            <input type="hidden" name="type" value="link">
            <div class="form-group">
                <label>Name / What it is *</label>
                <input type="text" name="software_name" id="linkName" maxlength="120" placeholder="Intake Google Sheet, Jotform, Shared Drive…" value="{{ old('type') === 'link' ? old('software_name') : '' }}" required>
            </div>
            <div class="form-group">
                <label>Link / URL *</label>
                <input type="text" name="login_url" id="linkUrl" maxlength="255" placeholder="https://…" value="{{ old('type') === 'link' ? old('login_url') : '' }}" required>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="linkNotes" rows="2" maxlength="2000" placeholder="What it's for (optional)">{{ old('type') === 'link' ? old('notes') : '' }}</textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('linkModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .cred-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:16px; margin-top:4px; }
    .cred-card { border:1px solid var(--pro-line, #e6ebf2); border-radius:14px; padding:16px 18px; background:var(--pro-surface, #fff); }
    .cred-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .cred-name { font-size:16px; font-weight:800; color:var(--pro-text, #0f172a); word-break:break-word; }
    .cred-tools { display:flex; gap:6px; flex-shrink:0; }
    .cred-tools form { margin:0; }
    .cred-icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; border:1px solid var(--pro-line,#e6ebf2); background:transparent; color:#64748b; cursor:pointer; }
    .cred-icon:hover { background:#f1f5f9; color:#0f172a; }
    .cred-danger:hover { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
    .cred-link { display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:13px; font-weight:700; color:#2563eb; text-decoration:none; }
    .cred-link:hover { text-decoration:underline; }
    .cred-rows { margin-top:12px; display:flex; flex-direction:column; gap:8px; }
    .cred-row { display:flex; align-items:center; gap:8px; font-size:13px; }
    .cred-k { flex:0 0 78px; color:#64748b; font-weight:700; text-transform:uppercase; font-size:11px; letter-spacing:.3px; }
    .cred-v { flex:1; color:var(--pro-text,#0f172a); word-break:break-all; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; }
    .cred-copy { border:1px solid var(--pro-line,#e6ebf2); background:transparent; color:#475569; border-radius:7px; padding:3px 9px; font-size:11px; font-weight:700; cursor:pointer; flex-shrink:0; }
    .cred-copy:hover { background:#f1f5f9; color:#0f172a; }
    .cred-note { padding:8px 10px; background:#f8fafc; border-radius:8px; font-size:12.5px; color:#475569; white-space:pre-wrap; }
    .cred-two { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:520px){ .cred-two { grid-template-columns:1fr; } }
    :root[data-theme="dark"] .cred-card { background:#10152a; }
    :root[data-theme="dark"] .cred-icon:hover { background:#1e293b; color:#e2e8f0; }
    :root[data-theme="dark"] .cred-copy:hover { background:#1e293b; color:#e2e8f0; }
    :root[data-theme="dark"] .cred-note { background:#0b1120; color:#94a3b8; }
</style>
@endpush

@push('scripts')
<script>
window.credOpen = function () {
    document.getElementById('credModalTitle').textContent = 'Add Credential';
    document.getElementById('credForm').action = @js(route('admin.credentials.store'));
    document.getElementById('credMethod').value = 'POST';
    ['credSoftware','credEmail','credPassword','credNotes'].forEach(function(id){ document.getElementById(id).value = ''; });
    openModal('credModal');
};
window.credEdit = function (c) {
    document.getElementById('credModalTitle').textContent = 'Edit Credential';
    document.getElementById('credForm').action = @js(url('admin/credentials')) + '/' + c.id;
    document.getElementById('credMethod').value = 'PUT';
    document.getElementById('credSoftware').value = c.software_name || '';
    document.getElementById('credEmail').value = c.email || '';
    document.getElementById('credPassword').value = c.password || '';
    document.getElementById('credNotes').value = c.notes || '';
    openModal('credModal');
};

window.linkOpen = function () {
    document.getElementById('linkModalTitle').textContent = 'Add Link';
    document.getElementById('linkForm').action = @js(route('admin.credentials.store'));
    document.getElementById('linkMethod').value = 'POST';
    ['linkName','linkUrl','linkNotes'].forEach(function(id){ document.getElementById(id).value = ''; });
    openModal('linkModal');
};
window.linkEdit = function (l) {
    document.getElementById('linkModalTitle').textContent = 'Edit Link';
    document.getElementById('linkForm').action = @js(url('admin/credentials')) + '/' + l.id;
    document.getElementById('linkMethod').value = 'PUT';
    document.getElementById('linkName').value = l.software_name || '';
    document.getElementById('linkUrl').value = l.login_url || '';
    document.getElementById('linkNotes').value = l.notes || '';
    openModal('linkModal');
};

window.credReveal = function (btn) {
    var box = btn.closest('.cred-row').querySelector('.cred-secret');
    var shown = box.getAttribute('data-shown') === '1';
    box.querySelector('.cred-dots').hidden = !shown;
    box.querySelector('.cred-plain').hidden = shown;
    box.setAttribute('data-shown', shown ? '0' : '1');
    btn.textContent = shown ? 'Show' : 'Hide';
};
window.credCopy = function (btn, value) {
    var done = function () { var t = btn.textContent; btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = t; }, 1200); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(function(){ window.prompt('Copy:', value); });
    } else {
        window.prompt('Copy:', value);
    }
};

@if ($errors->any())
    if (typeof openModal === 'function') openModal(@js(old('type') === 'link' ? 'linkModal' : 'credModal'));
@endif
</script>
@endpush
@endsection
