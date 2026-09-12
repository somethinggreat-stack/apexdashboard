@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Team Chat')

@php
    $palette = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ec4899','#14b8a6','#f43f5e','#7c3aed','#0891b2'];
    $mono = function ($name) {
        $p = preg_split('/\s+/', trim($name ?: '?'));
        return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    };
    $color = function ($s) use ($palette) { $n = 0; foreach (str_split($s ?: '?') as $c) { $n += ord($c); } return $palette[$n % count($palette)]; };
@endphp

@section('content')
<div class="tc-wrap">
    <aside class="tc-list">
        <div class="tc-list-head">
            <h2>Team Chat</h2>
            <p>Message any teammate directly.</p>
        </div>
        <div class="tc-contacts">
            @forelse ($members as $m)
                <a href="{{ route('admin.team-messages.index', ['with' => $m->id]) }}" class="tc-contact {{ $active && $active->id === $m->id ? 'active' : '' }}">
                    <span class="tc-avatar" style="background:{{ $color($m->full_name) }}">{{ $mono($m->full_name) }}</span>
                    <span class="tc-c-body">
                        <span class="tc-c-name">{{ $m->full_name }}</span>
                        <span class="tc-c-role">{{ $m->isSuper() ? 'Super Admin' : 'VA' }}</span>
                    </span>
                    @if (($unread[$m->id] ?? 0) > 0)<span class="tc-unread">{{ $unread[$m->id] }}</span>@endif
                </a>
            @empty
                <div class="tc-empty">No teammates to message yet.</div>
            @endforelse
        </div>
    </aside>

    <section class="tc-thread">
        @if ($active)
            <div class="tc-thread-head">
                <span class="tc-avatar sm" style="background:{{ $color($active->full_name) }}">{{ $mono($active->full_name) }}</span>
                <div>
                    <div class="tc-th-name">{{ $active->full_name }}</div>
                    <div class="tc-th-role">{{ $active->isSuper() ? 'Super Admin' : 'VA' }} · {{ $active->email }}</div>
                </div>
            </div>

            <div class="tc-messages" id="tcMessages" data-with="{{ $active->id }}" data-last="{{ $messages->last()->id ?? 0 }}">
                @forelse ($messages as $msg)
                    <div class="tc-msg {{ $msg->sender_id === $me->id ? 'mine' : '' }}">
                        <div class="tc-bubble">{{ $msg->body }}</div>
                        <div class="tc-time">{{ $msg->created_at->timezone($tz)->format('M j · g:i A') }}</div>
                    </div>
                @empty
                    <div class="tc-thread-empty">No messages yet — say hello 👋</div>
                @endforelse
            </div>

            <form class="tc-composer" id="tcForm" method="POST" action="{{ route('admin.team-messages.store') }}">
                @csrf
                <input type="hidden" name="recipient_id" value="{{ $active->id }}">
                <textarea name="body" id="tcInput" rows="1" placeholder="Message {{ $active->full_name }}…" maxlength="5000" required></textarea>
                <button type="submit" class="tc-send" aria-label="Send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>
        @else
            <div class="tc-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <p>Select a teammate on the left to start chatting.</p>
            </div>
        @endif
    </section>
</div>

@push('head')
<style>
    .tc-wrap { display:grid; grid-template-columns:320px 1fr; gap:16px; height:calc(100vh - 150px); min-height:520px; }
    @media (max-width:820px){ .tc-wrap { grid-template-columns:1fr; height:auto; } }

    .tc-list, .tc-thread { background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:18px; overflow:hidden; display:flex; flex-direction:column; }
    .tc-list-head { padding:18px 18px 12px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .tc-list-head h2 { margin:0; font-size:17px; font-weight:800; color:var(--pro-text,#0f172a); }
    .tc-list-head p { margin:3px 0 0; font-size:12.5px; color:#94a3b8; }
    .tc-contacts { flex:1; overflow-y:auto; padding:8px; }
    .tc-contact { display:flex; align-items:center; gap:12px; padding:10px 11px; border-radius:12px; text-decoration:none; }
    .tc-contact:hover { background:var(--pro-soft,#f5f7fb); }
    .tc-contact.active { background:linear-gradient(90deg, rgba(79,70,229,.12), rgba(37,99,235,.05)); }
    .tc-avatar { flex:none; width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:14px; box-shadow:0 4px 10px rgba(15,23,42,.16); }
    .tc-avatar.sm { width:38px; height:38px; }
    .tc-c-body { min-width:0; flex:1; display:flex; flex-direction:column; }
    .tc-c-name { font-size:14px; font-weight:700; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-c-role { font-size:11.5px; color:#94a3b8; }
    .tc-unread { flex:none; min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#4f46e5; color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; }
    .tc-empty { padding:26px 14px; text-align:center; color:#94a3b8; font-size:13px; }

    .tc-thread-head { display:flex; align-items:center; gap:12px; padding:15px 20px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .tc-th-name { font-size:15px; font-weight:800; color:var(--pro-text,#0f172a); }
    .tc-th-role { font-size:12px; color:#94a3b8; }
    .tc-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:var(--pro-soft,#f7f9fc); }
    .tc-msg { display:flex; flex-direction:column; align-items:flex-start; max-width:74%; }
    .tc-msg.mine { align-self:flex-end; align-items:flex-end; }
    .tc-bubble { padding:10px 14px; border-radius:16px; font-size:14px; line-height:1.5; color:var(--pro-text,#0f172a); background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); white-space:pre-wrap; word-break:break-word; box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .tc-msg.mine .tc-bubble { background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; border-color:transparent; }
    .tc-time { font-size:10.5px; color:#94a3b8; margin:4px 6px 0; }
    .tc-thread-empty { margin:auto; color:#94a3b8; font-size:13.5px; }

    .tc-composer { display:flex; align-items:flex-end; gap:10px; padding:14px 16px; border-top:1px solid var(--pro-line,#eef2f7); }
    .tc-composer textarea { flex:1; resize:none; max-height:140px; border:1.5px solid var(--pro-line,#e6ebf2); border-radius:14px; padding:11px 14px; font:inherit; font-size:14px; background:var(--pro-soft,#f7f9fc); color:var(--pro-text,#0f172a); outline:none; transition:border-color .15s, box-shadow .15s; }
    .tc-composer textarea:focus { border-color:#4f46e5; background:var(--pro-surface,#fff); box-shadow:0 0 0 4px rgba(79,70,229,.13); }
    .tc-send { flex:none; width:44px; height:44px; border:0; border-radius:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#fff; background:linear-gradient(135deg,#4f46e5,#2563eb); box-shadow:0 8px 18px rgba(79,70,229,.32); transition:transform .1s, filter .15s; }
    .tc-send:hover { transform:translateY(-1px); filter:brightness(1.05); }
    .tc-send svg { width:19px; height:19px; }
    .tc-send:disabled { opacity:.5; cursor:default; transform:none; }

    .tc-placeholder { margin:auto; display:flex; flex-direction:column; align-items:center; gap:12px; color:#94a3b8; }
    .tc-placeholder svg { width:46px; height:46px; color:#cbd5e1; }
    .tc-placeholder p { font-size:14px; }

    :root[data-theme="dark"] .tc-list, :root[data-theme="dark"] .tc-thread { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-messages { background:#0b1120; }
    :root[data-theme="dark"] .tc-bubble { background:#141d33; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-contact:hover { background:#182444; }
    :root[data-theme="dark"] .tc-composer textarea { background:#0b1120; border-color:#233150; color:#e2e8f0; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var box = document.getElementById('tcMessages');
    if (!box) return;
    var form  = document.getElementById('tcForm');
    var input = document.getElementById('tcInput');
    var csrf  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var withId = box.dataset.with;
    var lastId = parseInt(box.dataset.last, 10) || 0;
    var THREAD = @js(route('admin.team-messages.thread'));
    var STORE  = @js(route('admin.team-messages.store'));

    function esc(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function atBottom(){ return box.scrollHeight - box.scrollTop - box.clientHeight < 80; }
    function toBottom(){ box.scrollTop = box.scrollHeight; }

    function append(m){
        var empty = box.querySelector('.tc-thread-empty'); if (empty) empty.remove();
        var el = document.createElement('div');
        el.className = 'tc-msg' + (m.mine ? ' mine' : '');
        el.innerHTML = '<div class="tc-bubble">' + esc(m.body) + '</div><div class="tc-time">' + esc(m.at) + '</div>';
        box.appendChild(el);
        if (m.id > lastId) lastId = m.id;
    }

    toBottom();

    // Auto-grow + Enter to send.
    function grow(){ input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body) return;
        var btn = form.querySelector('.tc-send'); btn.disabled = true;
        var fd = new FormData(); fd.append('_token', csrf); fd.append('recipient_id', withId); fd.append('body', body);
        fetch(STORE, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (res && res.ok) { append(res.message); input.value=''; grow(); toBottom(); input.focus(); }
            })
            .catch(function () { btn.disabled = false; });
    });

    // Live poll for new incoming messages.
    setInterval(function () {
        fetch(THREAD + '?with=' + encodeURIComponent(withId) + '&after=' + lastId, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.messages || !res.messages.length) return;
                var stick = atBottom();
                res.messages.forEach(append);
                if (stick) toBottom();
            })
            .catch(function () {});
    }, 4000);
})();
</script>
@endpush
@endsection
