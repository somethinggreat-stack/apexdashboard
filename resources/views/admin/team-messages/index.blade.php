@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Team Chat')

@php
    $palette = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ec4899','#14b8a6','#f43f5e','#7c3aed','#0891b2'];
    $mono = function ($name) {
        $p = preg_split('/\s+/', trim($name ?: '?'));
        return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    };
    $color = function ($s) use ($palette) { $n = 0; foreach (str_split($s ?: '?') as $c) { $n += ord($c); } return $palette[$n % count($palette)]; };
    // Photo if we have one, otherwise a colored monogram.
    $avatar = function ($admin, $extra = '') use ($mono, $color) {
        $url = $admin->avatarUrl();
        if ($url) {
            return '<span class="tc-avatar has-img ' . $extra . '"><img src="' . e($url) . '" alt="" loading="lazy"></span>';
        }
        return '<span class="tc-avatar ' . $extra . '" style="background:' . $color($admin->full_name) . '">' . e($mono($admin->full_name)) . '</span>';
    };
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
                @php $p = $previews[$m->id] ?? null; $u = $unread[$m->id] ?? 0; @endphp
                <a href="{{ route('admin.team-messages.index', ['with' => $m->id]) }}" class="tc-contact {{ $active && $active->id === $m->id ? 'active' : '' }}" data-contact="{{ $m->id }}">
                    {!! $avatar($m) !!}
                    <span class="tc-c-body">
                        <span class="tc-c-top">
                            <span class="tc-c-name">{{ $m->full_name }}</span>
                            <span class="tc-c-time {{ $u > 0 ? 'unread' : '' }}" data-time="{{ $m->id }}">{{ $p['at'] ?? '' }}</span>
                        </span>
                        <span class="tc-c-sub">
                            <span class="tc-c-preview {{ $u > 0 ? 'unread' : '' }}" data-preview="{{ $m->id }}">
                                @if ($p)
                                    @if ($p['mine'])<span class="tc-tick {{ $p['read'] ? 'read' : '' }}" data-tick>@include('partials.tick')</span>@endif
                                    <span data-preview-text>{{ Str::limit($p['body'], 38) }}</span>
                                @else
                                    <span class="tc-c-muted">{{ $m->isSuper() ? 'Super Admin' : 'VA' }} · Tap to message</span>
                                @endif
                            </span>
                            @if ($u > 0)<span class="tc-unread" data-badge="{{ $m->id }}">{{ $u }}</span>@endif
                        </span>
                    </span>
                </a>
            @empty
                <div class="tc-empty">No teammates to message yet.</div>
            @endforelse
        </div>
    </aside>

    <section class="tc-thread">
        @if ($active)
            <div class="tc-thread-head">
                {!! $avatar($active, 'sm') !!}
                <div>
                    <div class="tc-th-name">{{ $active->full_name }}</div>
                    <div class="tc-th-role">{{ $active->isSuper() ? 'Super Admin' : 'VA' }} · {{ $active->email }}</div>
                </div>
            </div>

            <div class="tc-messages" id="tcMessages" data-with="{{ $active->id }}" data-last="{{ $messages->last()->id ?? 0 }}">
                @forelse ($messages as $msg)
                    @include('partials.team-message', ['msg' => $msg])
                @empty
                    <div class="tc-thread-empty">No messages yet — say hello 👋</div>
                @endforelse
            </div>

            <div class="tc-reply-bar" id="tcReply" hidden>
                <span class="tc-reply-accent"></span>
                <div class="tc-reply-info">
                    <span class="tc-reply-author"></span>
                    <span class="tc-reply-text"></span>
                </div>
                <button type="button" class="tc-reply-x" id="tcReplyCancel" aria-label="Cancel reply">&times;</button>
            </div>

            <form class="tc-composer" id="tcForm" method="POST" action="{{ route('admin.team-messages.store') }}">
                @csrf
                <input type="hidden" name="recipient_id" value="{{ $active->id }}">
                <textarea name="body" id="tcInput" rows="1" placeholder="Message {{ $active->full_name }}…" maxlength="5000" required></textarea>
                <button type="submit" class="tc-send" aria-label="Send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>

            {{-- Message action menu (WhatsApp-style). Moved to <body> by JS so position:fixed is exact. --}}
            <div class="tc-menu" id="tcMenu" hidden>
                <div class="tc-menu-emoji">
                    @foreach ($emoji as $e)
                        <button type="button" data-emoji="{{ $e }}">{{ $e }}</button>
                    @endforeach
                </div>
                <button type="button" class="tc-menu-item" data-act="reply">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg> Reply
                </button>
                <button type="button" class="tc-menu-item" data-act="copy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy
                </button>
                <button type="button" class="tc-menu-item" data-act="forward">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg> Forward
                </button>
                <button type="button" class="tc-menu-item danger" data-act="delete" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete
                </button>
            </div>

            {{-- Forward picker --}}
            <div class="tc-modal" id="tcForward" hidden>
                <div class="tc-modal-card">
                    <div class="tc-modal-head">
                        <span>Forward to…</span>
                        <button type="button" id="tcFwdClose" aria-label="Close">&times;</button>
                    </div>
                    <div class="tc-modal-list" id="tcFwdList"></div>
                </div>
            </div>
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
    .tc-avatar { flex:none; width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:14px; box-shadow:0 4px 10px rgba(15,23,42,.16); }
    .tc-avatar.sm { width:38px; height:38px; border-radius:12px; }
    .tc-avatar.has-img { overflow:hidden; background:#e2e8f0; }
    .tc-avatar.has-img img { width:100%; height:100%; object-fit:cover; display:block; }
    .tc-c-body { min-width:0; flex:1; display:flex; flex-direction:column; gap:3px; }
    .tc-c-top { display:flex; align-items:center; gap:8px; }
    .tc-c-name { flex:1; min-width:0; font-size:14px; font-weight:700; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-c-time { flex:none; font-size:11px; color:#94a3b8; }
    .tc-c-time.unread { color:#4f46e5; font-weight:700; }
    .tc-c-sub { display:flex; align-items:center; gap:8px; }
    .tc-c-preview { flex:1; min-width:0; display:flex; align-items:center; gap:4px; font-size:12.5px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-c-preview [data-preview-text] { overflow:hidden; text-overflow:ellipsis; }
    .tc-c-preview.unread { color:var(--pro-text,#0f172a); font-weight:600; }
    .tc-c-muted { overflow:hidden; text-overflow:ellipsis; }
    .tc-tick { flex:none; display:inline-flex; width:16px; color:#9aa7b8; }
    .tc-tick svg { width:16px; height:auto; }
    .tc-tick.read { color:#2563eb; }
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
    .tc-time { font-size:10.5px; color:#94a3b8; margin:4px 6px 0; display:inline-flex; align-items:center; gap:4px; }
    .tc-btick { display:inline-flex; width:15px; color:#9aa7b8; }
    .tc-btick svg { width:15px; height:auto; }
    .tc-btick.read { color:#2563eb; }
    .tc-thread-empty { margin:auto; color:#94a3b8; font-size:13.5px; }
    .tc-msg { position:relative; }
    .tc-bubble { cursor:default; }
    /* These elements set their own display in the class, which would otherwise
       beat the UA [hidden] rule and make them impossible to hide. Force it. */
    .tc-menu[hidden], .tc-menu-item[hidden], .tc-reply-bar[hidden], .tc-modal[hidden] { display:none !important; }

    /* Quoted reply inside a bubble */
    .tc-quote { display:flex; flex-direction:column; gap:1px; padding:5px 9px; margin:-2px 0 6px; border-left:3px solid rgba(79,70,229,.7); border-radius:7px; background:rgba(79,70,229,.08); font-size:12.5px; }
    .tc-msg.mine .tc-quote { border-left-color:rgba(255,255,255,.85); background:rgba(255,255,255,.16); }
    .tc-quote-author { font-weight:700; color:#4f46e5; }
    .tc-msg.mine .tc-quote-author { color:#fff; }
    .tc-quote-text { color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:230px; }
    .tc-msg.mine .tc-quote-text { color:rgba(255,255,255,.85); }

    .tc-fwd { display:flex; align-items:center; gap:4px; font-size:11.5px; font-style:italic; color:#94a3b8; margin-bottom:3px; }
    .tc-msg.mine .tc-fwd { color:rgba(255,255,255,.8); }

    .tc-bubble.deleted { background:transparent; border:1px dashed var(--pro-line,#d7dee8); box-shadow:none; }
    .tc-bubble.deleted .tc-text { font-style:italic; color:#94a3b8; }
    .tc-msg.mine .tc-bubble.deleted { background:transparent; }
    .tc-msg.mine .tc-bubble.deleted .tc-text { color:rgba(255,255,255,.7); }

    /* Reaction pills */
    .tc-reacts { display:flex; flex-wrap:wrap; gap:4px; margin-top:-4px; padding:0 4px; }
    .tc-reacts:empty { display:none; }
    .tc-react { display:inline-flex; align-items:center; gap:3px; padding:1px 7px; border-radius:999px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); font-size:12px; font-weight:700; color:#475569; cursor:pointer; box-shadow:0 1px 2px rgba(15,23,42,.06); }
    .tc-react.mine { background:rgba(79,70,229,.12); border-color:rgba(79,70,229,.4); color:#4f46e5; }

    /* Action menu */
    .tc-menu { position:fixed; z-index:1000; min-width:190px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:14px; box-shadow:0 18px 44px rgba(15,23,42,.22); padding:6px; overflow:hidden; }
    .tc-menu-emoji { display:flex; justify-content:space-between; gap:2px; padding:4px 4px 8px; margin-bottom:4px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .tc-menu-emoji button { border:0; background:transparent; font-size:21px; line-height:1; padding:4px; border-radius:9px; cursor:pointer; transition:transform .1s, background .12s; }
    .tc-menu-emoji button:hover { transform:scale(1.2); background:var(--pro-soft,#f1f5f9); }
    .tc-menu-item { display:flex; align-items:center; gap:11px; width:100%; border:0; background:transparent; padding:9px 11px; border-radius:9px; font:inherit; font-size:13.5px; font-weight:600; color:var(--pro-text,#0f172a); cursor:pointer; text-align:left; }
    .tc-menu-item:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-menu-item svg { width:17px; height:17px; color:#64748b; flex:none; }
    .tc-menu-item.danger { color:#ef4444; }
    .tc-menu-item.danger svg { color:#ef4444; }

    /* Reply composer bar */
    .tc-reply-bar { display:flex; align-items:center; gap:10px; padding:9px 16px 0; }
    .tc-reply-accent { flex:none; width:3px; align-self:stretch; min-height:32px; border-radius:3px; background:#4f46e5; }
    .tc-reply-info { flex:1; min-width:0; display:flex; flex-direction:column; }
    .tc-reply-author { font-size:12.5px; font-weight:800; color:#4f46e5; }
    .tc-reply-text { font-size:12.5px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-reply-x { flex:none; border:0; background:transparent; font-size:22px; line-height:1; color:#94a3b8; cursor:pointer; padding:0 4px; }

    /* Forward modal */
    .tc-modal { position:fixed; inset:0; z-index:1001; background:rgba(15,23,42,.5); display:flex; align-items:center; justify-content:center; padding:20px; }
    .tc-modal-card { width:100%; max-width:360px; max-height:70vh; display:flex; flex-direction:column; background:var(--pro-surface,#fff); border-radius:18px; overflow:hidden; box-shadow:0 24px 60px rgba(15,23,42,.35); }
    .tc-modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px; border-bottom:1px solid var(--pro-line,#eef2f7); font-weight:800; color:var(--pro-text,#0f172a); }
    .tc-modal-head button { border:0; background:transparent; font-size:24px; line-height:1; color:#94a3b8; cursor:pointer; }
    .tc-modal-list { overflow-y:auto; padding:8px; }
    .tc-fwd-row { display:flex; align-items:center; gap:12px; width:100%; border:0; background:transparent; padding:9px 11px; border-radius:11px; cursor:pointer; font:inherit; font-size:14px; font-weight:700; color:var(--pro-text,#0f172a); text-align:left; }
    .tc-fwd-row:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-fwd-row .tc-avatar { width:36px; height:36px; font-size:13px; }

    :root[data-theme="dark"] .tc-menu, :root[data-theme="dark"] .tc-modal-card { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-react { background:#141d33; border-color:#233150; color:#cbd5e1; }
    :root[data-theme="dark"] .tc-menu-item, :root[data-theme="dark"] .tc-fwd-row { color:#e2e8f0; }
    :root[data-theme="dark"] .tc-menu-item:hover, :root[data-theme="dark"] .tc-menu-emoji button:hover, :root[data-theme="dark"] .tc-fwd-row:hover { background:#182444; }

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

    var TICK = '<svg viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6.6l3 3 5.5-6.4"/><path d="M8 9.6l1 1 5.5-6.4"/></svg>';
    var BASE = STORE, REACT = BASE + '/react', FORWARD = BASE + '/forward';
    var PEER = @js($active?->full_name ?? '');

    function esc(s){ return (s||'').replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function atBottom(){ return box.scrollHeight - box.scrollTop - box.clientHeight < 80; }
    function toBottom(){ box.scrollTop = box.scrollHeight; }
    function toast(t){ if (window.apexToast) window.apexToast(t); }

    function nowShort(){
        var d = new Date(), h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12; if (h === 0) h = 12;
        return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ap;
    }

    function reactsHtml(list){
        if (!list || !list.length) return '';
        return list.map(function (r) {
            return '<span class="tc-react' + (r.mine ? ' mine' : '') + '" data-emoji="' + r.emoji + '">'
                + r.emoji + (r.count > 1 ? ' ' + r.count : '') + '</span>';
        }).join('');
    }

    // Full inner HTML of a message row — kept in step with partials/team-message.blade.php.
    function bubbleInner(m){
        var h = '<div class="tc-bubble' + (m.deleted ? ' deleted' : '') + '">';
        if (m.reply && !m.deleted) h += '<div class="tc-quote"><span class="tc-quote-author">' + esc(m.reply.author)
            + '</span><span class="tc-quote-text">' + esc(m.reply.text) + '</span></div>';
        if (m.forwarded && !m.deleted) h += '<div class="tc-fwd">↪ Forwarded</div>';
        h += '<div class="tc-text">' + (m.deleted ? '🚫 This message was deleted' : esc(m.body)) + '</div></div>';
        var tick = (m.mine && !m.deleted) ? '<span class="tc-btick">' + TICK + '</span>' : '';
        h += '<div class="tc-time">' + esc(m.at) + tick + '</div>';
        h += '<div class="tc-reacts">' + reactsHtml(m.reactions) + '</div>';
        return h;
    }

    // Keep the left contact row's preview line in sync, WhatsApp-style, and float it to the top.
    function updatePreview(m){
        var row = document.querySelector('.tc-contact[data-contact="' + withId + '"]');
        if (!row) return;
        var time = row.querySelector('[data-time]');
        if (time){ time.textContent = nowShort(); time.classList.remove('unread'); }
        var prev = row.querySelector('[data-preview]');
        if (prev){
            prev.classList.remove('unread');
            var t = (m.mine && !m.deleted) ? '<span class="tc-tick" data-tick>' + TICK + '</span>' : '';
            var txt = m.deleted ? 'This message was deleted' : m.body;
            prev.innerHTML = t + '<span data-preview-text>' + esc(txt).slice(0, 80) + '</span>';
        }
        var badge = row.querySelector('[data-badge]'); if (badge) badge.remove();
        if (row.parentNode) row.parentNode.insertBefore(row, row.parentNode.firstChild); // move to top
    }

    function append(m){
        var empty = box.querySelector('.tc-thread-empty'); if (empty) empty.remove();
        var el = document.createElement('div');
        el.className = 'tc-msg' + (m.mine ? ' mine' : '');
        el.dataset.id = m.id;
        el.innerHTML = bubbleInner(m);
        box.appendChild(el);
        if (m.id > lastId) lastId = m.id;
        updatePreview(m);
    }

    // Live-sync reactions and deletions on messages already on screen.
    function applyStates(states){
        if (!states) return;
        states.forEach(function (s) {
            var el = box.querySelector('.tc-msg[data-id="' + s.id + '"]');
            if (!el) return;
            var rr = el.querySelector('.tc-reacts');
            if (rr) rr.innerHTML = reactsHtml(s.reactions);
            if (s.deleted){
                var b = el.querySelector('.tc-bubble');
                if (b && !b.classList.contains('deleted')){
                    b.classList.add('deleted');
                    b.innerHTML = '<div class="tc-text">🚫 This message was deleted</div>';
                }
            }
        });
    }

    // Flip sent-message ticks blue once the other person has read them.
    function applyReadReceipts(upTo){
        if (!upTo) return;
        var maxMine = 0;
        box.querySelectorAll('.tc-msg.mine').forEach(function (el) {
            var id = parseInt(el.dataset.id, 10) || 0;
            if (id > maxMine) maxMine = id;
            var t = el.querySelector('.tc-btick');
            if (t && id <= upTo) t.classList.add('read');
        });
        var row = document.querySelector('.tc-contact[data-contact="' + withId + '"]');
        if (row){
            var pt = row.querySelector('[data-tick]');
            if (pt && maxMine && maxMine <= upTo) pt.classList.add('read');
        }
    }

    toBottom();
    if (input) input.focus(); // ready to type the moment the chat opens

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
        if (replyId) fd.append('reply_to_id', replyId);
        fetch(STORE, { method:'POST', cache:'no-store', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (res && res.ok) { append(res.message); input.value=''; grow(); cancelReply(); toBottom(); input.focus(); }
            })
            .catch(function () { btn.disabled = false; });
    });

    // Live poll for new incoming messages. cache:'no-store' + a buster stop the
    // browser from serving a stale empty response for the same ?after= URL.
    function poll(){
        fetch(THREAD + '?with=' + encodeURIComponent(withId) + '&after=' + lastId + '&_=' + Date.now(),
            { cache:'no-store', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res) return;
                if (res.messages && res.messages.length) {
                    var stick = atBottom();
                    res.messages.forEach(append);
                    if (stick) toBottom();
                }
                applyReadReceipts(res.readUpTo);
                applyStates(res.states);
            })
            .catch(function () {});
    }
    setInterval(poll, 3000);

    // ---------- Message actions: menu, react, reply, copy, forward, delete ----------
    var menu     = document.getElementById('tcMenu');
    var fwdModal = document.getElementById('tcForward');
    var replyBar = document.getElementById('tcReply');
    if (menu) document.body.appendChild(menu);         // detach so position:fixed is exact
    if (fwdModal) document.body.appendChild(fwdModal);
    var menuMsg = null, replyId = null, forwardId = null, guardUntil = 0;

    function postJson(url, data, method){
        var fd = new FormData(); fd.append('_token', csrf);
        Object.keys(data).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined && data[k] !== '') fd.append(k, data[k]);
        });
        if (method) fd.append('_method', method);
        return fetch(url, { method:'POST', cache:'no-store', body:fd,
            headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).then(function (r) { return r.json(); });
    }

    function openMenu(x, y, el){
        var bub = el.querySelector('.tc-bubble');
        if (!bub || bub.classList.contains('deleted')) return;   // nothing to do on a deleted message
        var txtEl = el.querySelector('.tc-text');
        menuMsg = { id: parseInt(el.dataset.id, 10), mine: el.classList.contains('mine'), text: txtEl ? txtEl.textContent : '' };
        menu.querySelector('[data-act="delete"]').hidden = !menuMsg.mine;
        menu.hidden = false;
        var mw = menu.offsetWidth, mh = menu.offsetHeight;
        menu.style.left = Math.max(8, Math.min(x, window.innerWidth  - mw - 8)) + 'px';
        menu.style.top  = Math.max(8, Math.min(y, window.innerHeight - mh - 8)) + 'px';
        guardUntil = Date.now() + 350;
    }
    function closeMenu(){ if (menu) { menu.hidden = true; } menuMsg = null; }

    function react(id, emoji){
        postJson(REACT, { message_id: id, emoji: emoji }).then(function (res) {
            if (res && res.ok){
                var el = box.querySelector('.tc-msg[data-id="' + id + '"]');
                var rr = el && el.querySelector('.tc-reacts');
                if (rr) rr.innerHTML = reactsHtml(res.reactions);
            }
        });
    }

    function startReply(){
        if (!menuMsg || !replyBar) return;
        replyId = menuMsg.id;
        replyBar.hidden = false;
        replyBar.querySelector('.tc-reply-author').textContent = menuMsg.mine ? 'You' : PEER;
        replyBar.querySelector('.tc-reply-text').textContent = menuMsg.text.slice(0, 140);
        input.focus();
    }
    function cancelReply(){ replyId = null; if (replyBar) replyBar.hidden = true; }

    function copyMsg(){
        if (!menuMsg) return;
        var t = menuMsg.text;
        if (navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(t).then(function () { toast('Copied'); }, function () {});
        } else {
            var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); toast('Copied'); } catch (e) {}
            ta.remove();
        }
    }

    function delMsg(){
        if (!menuMsg) return;
        var id = menuMsg.id;
        if (!window.confirm('Delete this message for everyone?')) return;
        postJson(BASE + '/' + id, {}, 'DELETE').then(function (res) {
            if (res && res.ok){
                var el = box.querySelector('.tc-msg[data-id="' + id + '"]');
                if (el){
                    var b = el.querySelector('.tc-bubble'); b.classList.add('deleted');
                    b.innerHTML = '<div class="tc-text">🚫 This message was deleted</div>';
                    var rr = el.querySelector('.tc-reacts'); if (rr) rr.innerHTML = '';
                }
            }
        });
    }

    function openForward(){
        if (!menuMsg || !fwdModal) return;
        forwardId = menuMsg.id;
        var list = document.getElementById('tcFwdList'); list.innerHTML = '';
        document.querySelectorAll('.tc-contact').forEach(function (c) {
            var id = c.dataset.contact;
            var nameEl = c.querySelector('.tc-c-name'); var name = nameEl ? nameEl.textContent : 'Teammate';
            var av = c.querySelector('.tc-avatar');
            var row = document.createElement('button'); row.type = 'button'; row.className = 'tc-fwd-row';
            row.innerHTML = (av ? av.outerHTML : '') + '<span>' + esc(name) + '</span>';
            row.addEventListener('click', function () { doForward(id, name); });
            list.appendChild(row);
        });
        fwdModal.hidden = false;
    }
    function doForward(id, name){
        postJson(FORWARD, { message_id: forwardId, recipient_id: id }).then(function (res) {
            fwdModal.hidden = true;
            if (res && res.ok) toast('Forwarded to ' + name);
        });
    }

    // Open the menu: right-click (desktop) or long-press (touch).
    box.addEventListener('contextmenu', function (e) {
        var el = e.target.closest('.tc-msg'); if (el){ e.preventDefault(); openMenu(e.clientX, e.clientY, el); }
    });
    var lp;
    box.addEventListener('touchstart', function (e) {
        var el = e.target.closest('.tc-msg'); if (!el) return;
        var t = e.touches[0]; lp = setTimeout(function () { openMenu(t.clientX, t.clientY, el); }, 480);
    }, { passive: true });
    ['touchend','touchmove','touchcancel'].forEach(function (ev) { box.addEventListener(ev, function () { clearTimeout(lp); }); });

    // Tap a reaction pill to toggle that emoji.
    box.addEventListener('click', function (e) {
        var pill = e.target.closest('.tc-react'); if (!pill) return;
        var el = pill.closest('.tc-msg'); if (el) react(parseInt(el.dataset.id, 10), pill.dataset.emoji);
    });

    if (menu){
        menu.querySelectorAll('.tc-menu-emoji button').forEach(function (b) {
            b.addEventListener('click', function () { if (menuMsg) react(menuMsg.id, b.dataset.emoji); closeMenu(); });
        });
        menu.addEventListener('click', function (e) {
            var it = e.target.closest('.tc-menu-item'); if (!it) return;
            var act = it.dataset.act;
            if (act === 'reply') startReply();
            else if (act === 'copy') copyMsg();
            else if (act === 'forward') openForward();
            else if (act === 'delete') delMsg();
            closeMenu();
        });
    }

    document.addEventListener('click', function (e) {
        if (!menu || menu.hidden) return;
        if (Date.now() < guardUntil) return;
        if (!menu.contains(e.target)) closeMenu();
    });
    box.addEventListener('scroll', function () { if (menu && !menu.hidden) closeMenu(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape'){ closeMenu(); if (fwdModal) fwdModal.hidden = true; }
    });

    var rc = document.getElementById('tcReplyCancel'); if (rc) rc.addEventListener('click', cancelReply);
    if (fwdModal){
        var fc = document.getElementById('tcFwdClose'); if (fc) fc.addEventListener('click', function () { fwdModal.hidden = true; });
        fwdModal.addEventListener('click', function (e) { if (e.target === fwdModal) fwdModal.hidden = true; });
    }
})();
</script>
@endpush
@endsection
