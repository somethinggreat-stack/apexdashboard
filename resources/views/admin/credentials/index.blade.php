@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Credentials')

@php
    // A stable gradient + initials for each card, derived from its name.
    $palette = [
        ['#6366f1', '#8b5cf6'], ['#0ea5e9', '#2563eb'], ['#10b981', '#059669'],
        ['#f59e0b', '#f97316'], ['#ec4899', '#db2777'], ['#14b8a6', '#0891b2'],
        ['#f43f5e', '#e11d48'], ['#7c3aed', '#6d28d9'], ['#0891b2', '#0e7490'],
    ];
    $avatar = function (string $name) use ($palette) {
        $name = trim($name) ?: '?';
        $words = preg_split('/\s+/', $name);
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
        $sum = 0;
        foreach (str_split($name) as $ch) { $sum += ord($ch); }
        return ['initials' => $initials, 'grad' => $palette[$sum % count($palette)]];
    };
@endphp

@section('content')

{{-- ============ CRM / software logins ============ --}}
<div class="vault">
    <div class="vault-head">
        <div>
            <h2 class="vault-title">Credentials</h2>
            <p class="vault-sub">{{ $client->business_name }}&rsquo;s CRM &amp; software logins &mdash; GoHighLevel, dispute software, email inbox, and the like.</p>
        </div>
        <button class="vault-add" onclick="credOpen()">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Credential
        </button>
    </div>

    @if ($credentials->isEmpty())
        <div class="vault-empty">
            <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <p>No logins saved yet.</p>
            <span>Click <strong>Add Credential</strong> to store this owner&rsquo;s first CRM / software login.</span>
        </div>
    @else
        <div class="vault-grid">
            @foreach ($credentials as $c)
                @php $a = $avatar($c->software_name); @endphp
                <article class="vcard">
                    <div class="vcard-top">
                        <div class="vcard-mono" style="background:linear-gradient(135deg,{{ $a['grad'][0] }},{{ $a['grad'][1] }});">{{ $a['initials'] }}</div>
                        <div class="vcard-name">{{ $c->software_name }}</div>
                        <div class="vcard-tools">
                            <button type="button" class="vcard-icon" title="Edit"
                                onclick="credEdit({{ Illuminate\Support\Js::from(['id' => $c->id, 'software_name' => $c->software_name, 'email' => $c->email, 'password' => $c->password, 'notes' => $c->notes]) }})">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.credentials.destroy', $c->id) }}" data-confirm-delete data-confirm-message="Delete {{ $c->software_name }}? This can't be undone.">
                                @csrf @method('DELETE')
                                <button class="vcard-icon vcard-danger" title="Delete">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="vcard-body">
                        @if ($c->email)
                            <div class="vfield">
                                <span class="vfield-k">Email</span>
                                <button type="button" class="vfield-v" title="Click to copy" onclick="credCopy(this, @js($c->email))"><span>{{ $c->email }}</span>@include('admin.credentials._copyicon')</button>
                            </div>
                        @endif
                        @if ($c->password)
                            <div class="vfield">
                                <span class="vfield-k">Password</span>
                                <button type="button" class="vfield-v mono" title="Click to copy" onclick="credCopy(this, @js($c->password))"><span>{{ $c->password }}</span>@include('admin.credentials._copyicon')</button>
                            </div>
                        @endif
                        @if (!$c->email && !$c->password)
                            <p class="vcard-blank">No email or password saved.</p>
                        @endif
                        @if ($c->notes)
                            <div class="vcard-note">{{ $c->notes }}</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

{{-- ============ Links & resources (name + link only) ============ --}}
<div class="vault" style="margin-top:20px;">
    <div class="vault-head">
        <div>
            <h2 class="vault-title">Links &amp; Resources</h2>
            <p class="vault-sub">Just a name and a link &mdash; Google Sheets, Jotform, shared folders. No login needed.</p>
        </div>
        <button class="vault-add" onclick="linkOpen()">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Link
        </button>
    </div>

    @if ($links->isEmpty())
        <div class="vault-empty">
            <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <p>No links saved yet.</p>
            <span>Click <strong>Add Link</strong> to save a Google Sheet, Jotform, or any other link.</span>
        </div>
    @else
        <div class="vault-grid">
            @foreach ($links as $l)
                @php $a = $avatar($l->software_name); $href = Str::startsWith($l->login_url, ['http://','https://']) ? $l->login_url : 'https://'.$l->login_url; @endphp
                <article class="vcard vcard-link">
                    <div class="vcard-top">
                        <div class="vcard-mono" style="background:linear-gradient(135deg,{{ $a['grad'][0] }},{{ $a['grad'][1] }});">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        </div>
                        <div class="vcard-name">{{ $l->software_name }}</div>
                        <div class="vcard-tools">
                            <button type="button" class="vcard-icon" title="Edit"
                                onclick="linkEdit({{ Illuminate\Support\Js::from(['id' => $l->id, 'software_name' => $l->software_name, 'login_url' => $l->login_url, 'notes' => $l->notes]) }})">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.credentials.destroy', $l->id) }}" data-confirm-delete data-confirm-message="Delete {{ $l->software_name }}? This can't be undone.">
                                @csrf @method('DELETE')
                                <button class="vcard-icon vcard-danger" title="Delete">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="vcard-body">
                        @if ($l->notes)<div class="vcard-note">{{ $l->notes }}</div>@endif
                        <a class="vcard-open" href="{{ $href }}" target="_blank" rel="noopener noreferrer">
                            Open link
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

{{-- Add / Edit Credential --}}
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

{{-- Add / Edit Link --}}
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
            <div class="form-group"><label>Notes (optional)</label><textarea name="notes" id="linkNotes" rows="2" maxlength="2000" placeholder="What it's for">{{ old('type') === 'link' ? old('notes') : '' }}</textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('linkModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .vault { background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e9eef5); border-radius:18px; padding:22px 24px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .vault-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .vault-title { margin:0; font-size:19px; font-weight:800; letter-spacing:-.4px; color:var(--pro-text,#0f172a); }
    .vault-sub { margin:5px 0 0; font-size:13px; color:#64748b; max-width:640px; }
    .vault-add { display:inline-flex; align-items:center; gap:7px; background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; border:none; padding:10px 16px; border-radius:11px; font-size:13.5px; font-weight:700; cursor:pointer; box-shadow:0 4px 12px rgba(79,70,229,.28); transition:transform .12s, box-shadow .12s; white-space:nowrap; }
    .vault-add:hover { transform:translateY(-1px); box-shadow:0 7px 18px rgba(79,70,229,.36); }

    .vault-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; }

    .vcard { position:relative; border:1px solid var(--pro-line,#e9eef5); border-radius:16px; padding:16px; background:var(--pro-surface,#fff); transition:transform .14s, box-shadow .14s, border-color .14s; overflow:hidden; }
    .vcard:hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(15,23,42,.10); border-color:#dbe3ef; }
    .vcard-top { display:flex; align-items:center; gap:12px; }
    .vcard-mono { flex:0 0 42px; width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; letter-spacing:.5px; box-shadow:0 4px 10px rgba(15,23,42,.16); }
    .vcard-name { flex:1; font-size:16px; font-weight:800; color:var(--pro-text,#0f172a); word-break:break-word; letter-spacing:-.2px; }
    .vcard-tools { display:flex; gap:5px; flex-shrink:0; opacity:0; transition:opacity .14s; }
    .vcard:hover .vcard-tools { opacity:1; }
    .vcard-tools form { margin:0; }
    .vcard-icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9px; border:1px solid var(--pro-line,#e9eef5); background:#fff; color:#64748b; cursor:pointer; transition:all .12s; }
    .vcard-icon:hover { background:#f1f5f9; color:#0f172a; }
    .vcard-danger:hover { background:#fef2f2; color:#dc2626; border-color:#fecaca; }

    .vcard-body { margin-top:14px; display:flex; flex-direction:column; gap:9px; }
    .vfield { display:flex; flex-direction:column; gap:4px; }
    .vfield-k { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; }
    .vfield-v { display:flex; align-items:center; gap:8px; width:100%; text-align:left; background:#f7f9fc; border:1px solid #eef2f8; border-radius:10px; padding:9px 11px; font-size:13.5px; color:var(--pro-text,#0f172a); cursor:pointer; transition:background .12s, border-color .12s; }
    .vfield-v span { flex:1; word-break:break-all; line-height:1.3; }
    .vfield-v.mono span { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing:.3px; }
    .vfield-v:hover { background:#eef4ff; border-color:#c7d7fb; }
    .vfield-v .vcopy { flex-shrink:0; color:#94a3b8; transition:color .12s; }
    .vfield-v:hover .vcopy { color:#4f46e5; }
    .vfield-v.copied { background:#ecfdf5; border-color:#a7f3d0; }
    .vfield-v.copied .vcopy { color:#059669; }

    .vcard-note { background:#fffbeb; border:1px solid #fef0c7; border-radius:10px; padding:8px 11px; font-size:12.5px; color:#92400e; white-space:pre-wrap; line-height:1.4; }
    .vcard-blank { margin:0; font-size:12.5px; color:#94a3b8; font-style:italic; }

    .vcard-open { display:inline-flex; align-items:center; gap:7px; align-self:flex-start; background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; padding:8px 15px; border-radius:10px; font-size:13px; font-weight:700; text-decoration:none; transition:transform .12s, box-shadow .12s; box-shadow:0 3px 9px rgba(79,70,229,.24); }
    .vcard-open:hover { transform:translateY(-1px); box-shadow:0 6px 15px rgba(79,70,229,.32); }

    .vault-empty { text-align:center; padding:34px 0 30px; color:#94a3b8; }
    .vault-empty svg { color:#cbd5e1; margin-bottom:8px; }
    .vault-empty p { margin:0; font-size:15px; font-weight:700; color:#64748b; }
    .vault-empty span { display:block; margin-top:4px; font-size:13px; }

    /* Dark theme */
    :root[data-theme="dark"] .vault { background:#0f1629; border-color:var(--pro-line,#1e293b); }
    :root[data-theme="dark"] .vcard { background:#141c33; border-color:#233150; }
    :root[data-theme="dark"] .vcard:hover { border-color:#2e4066; }
    :root[data-theme="dark"] .vcard-icon { background:#1a2440; border-color:#2b3b5e; color:#94a3b8; }
    :root[data-theme="dark"] .vcard-icon:hover { background:#243255; color:#e2e8f0; }
    :root[data-theme="dark"] .vfield-v { background:#0e1626; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .vfield-v:hover { background:#182444; border-color:#33477a; }
    :root[data-theme="dark"] .vcard-note { background:#2a2411; border-color:#4a3f18; color:#fcd34d; }
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

window.credCopy = function (el, value) {
    var done = function () { el.classList.add('copied'); setTimeout(function(){ el.classList.remove('copied'); }, 1100); };
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
