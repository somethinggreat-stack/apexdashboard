@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Credentials')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Credentials</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                {{ $client->business_name }}&rsquo;s CRM / software logins and resource links (Google Sheets, Jotform, dispute software, email&hellip;).
                Add a name for what it is, a link, and any login details.
            </p>
        </div>
        <button class="btn btn-primary" onclick="credOpen()">+ Add Credential</button>
    </div>

    @if ($credentials->isEmpty())
        <p class="empty" style="padding:30px 0; text-align:center;">
            Nothing saved yet. Click <strong>Add Credential</strong> to store this owner&rsquo;s first login or link.
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
                                    'id' => $c->id,
                                    'software_name' => $c->software_name,
                                    'login_url' => $c->login_url,
                                    'username' => $c->username,
                                    'email' => $c->email,
                                    'password' => $c->password,
                                    'notes' => $c->notes,
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

                    @if ($c->login_url)
                        <a class="cred-link" href="{{ Str::startsWith($c->login_url, ['http://','https://']) ? $c->login_url : 'https://'.$c->login_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            Open link
                        </a>
                    @endif

                    <div class="cred-rows">
                        @if ($c->username)
                            <div class="cred-row"><span class="cred-k">Username</span><span class="cred-v">{{ $c->username }}</span><button type="button" class="cred-copy" onclick="credCopy(this, @js($c->username))">Copy</button></div>
                        @endif
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

{{-- Add / Edit modal (shared) --}}
<div id="credModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="credModalTitle">Add Credential</h3>
            <button class="modal-close" onclick="closeModal('credModal')">&times;</button>
        </div>
        <form method="POST" id="credForm" action="{{ route('admin.credentials.store') }}">
            @csrf
            <input type="hidden" name="_method" id="credMethod" value="POST">
            <div class="form-group">
                <label>Name / What it is *</label>
                <input type="text" name="software_name" id="credSoftware" maxlength="120" placeholder="GoHighLevel, Dispute Panda, Intake Google Sheet, Jotform…" value="{{ old('software_name') }}" required>
            </div>
            <div class="form-group">
                <label>Link / URL</label>
                <input type="text" name="login_url" id="credUrl" maxlength="255" placeholder="https://…" value="{{ old('login_url') }}">
            </div>
            <div class="cred-two">
                <div class="form-group"><label>Username</label><input type="text" name="username" id="credUsername" maxlength="255" value="{{ old('username') }}"></div>
                <div class="form-group"><label>Email</label><input type="text" name="email" id="credEmail" maxlength="255" value="{{ old('email') }}"></div>
            </div>
            <div class="form-group"><label>Password</label><input type="text" name="password" id="credPassword" maxlength="1000" value="{{ old('password') }}"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="credNotes" rows="2" maxlength="2000" placeholder="2FA phone, security answers, anything else">{{ old('notes') }}</textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('credModal')">Cancel</button>
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
    .cred-note { margin-top:2px; padding:8px 10px; background:#f8fafc; border-radius:8px; font-size:12.5px; color:#475569; white-space:pre-wrap; }
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
    ['credSoftware','credUrl','credUsername','credEmail','credPassword','credNotes'].forEach(function(id){ document.getElementById(id).value = ''; });
    openModal('credModal');
};

window.credEdit = function (c) {
    document.getElementById('credModalTitle').textContent = 'Edit Credential';
    document.getElementById('credForm').action = @js(url('admin/credentials')) + '/' + c.id;
    document.getElementById('credMethod').value = 'PUT';
    document.getElementById('credSoftware').value = c.software_name || '';
    document.getElementById('credUrl').value = c.login_url || '';
    document.getElementById('credUsername').value = c.username || '';
    document.getElementById('credEmail').value = c.email || '';
    document.getElementById('credPassword').value = c.password || '';
    document.getElementById('credNotes').value = c.notes || '';
    openModal('credModal');
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
    if (typeof openModal === 'function') openModal('credModal');
@endif
</script>
@endpush
@endsection
