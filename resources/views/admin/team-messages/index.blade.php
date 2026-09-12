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
                @php $tcLastDay = null; $tcNow = \Illuminate\Support\Carbon::now($tz); @endphp
                @forelse ($messages as $msg)
                    @php
                        $d = $msg->created_at->timezone($tz);
                        $dayKey = $d->format('Y-m-d');
                        $dayLabel = $d->isSameDay($tcNow) ? 'Today' : ($d->isSameDay($tcNow->copy()->subDay()) ? 'Yesterday' : $d->format('F j, Y'));
                    @endphp
                    @if ($dayKey !== $tcLastDay)
                        <div class="tc-daysep"><span>{{ $dayLabel }}</span></div>
                        @php $tcLastDay = $dayKey; @endphp
                    @endif
                    @include('partials.team-message', ['msg' => $msg])
                @empty
                    <div class="tc-thread-empty">
                        <div class="tc-thread-empty-emoji">👋</div>
                        <p>No messages yet — say hello!</p>
                    </div>
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

            <div class="tc-pending" id="tcPending" hidden></div>
            <div class="tc-progress" id="tcProgress" hidden><i></i></div>

            <form class="tc-composer" id="tcForm" method="POST" action="{{ route('admin.team-messages.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="recipient_id" value="{{ $active->id }}">
                <input type="file" id="tcFile" multiple hidden accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx">
                <button type="button" class="tc-attach" id="tcAttach" aria-label="Attach a file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </button>
                <textarea name="body" id="tcInput" rows="1" placeholder="Message {{ $active->full_name }}…" maxlength="5000"></textarea>
                <button type="submit" class="tc-send" aria-label="Send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>

            {{-- Image lightbox (moved to <body> by JS) --}}
            <div class="tc-lightbox" id="tcLightbox" hidden>
                <button type="button" class="tc-lb-x" id="tcLbClose" aria-label="Close">&times;</button>
                <img src="" alt="" id="tcLbImg">
            </div>

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
    .tc-bubble { padding:10px 14px; border-radius:16px; font-size:14px; line-height:1.5; color:var(--pro-text,#0f172a); background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .tc-text { white-space:pre-wrap; word-break:break-word; }
    .tc-msg.mine .tc-bubble { background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; border-color:transparent; }
    .tc-time { font-size:10.5px; color:#94a3b8; margin:4px 6px 0; display:inline-flex; align-items:center; gap:4px; }
    .tc-btick { display:inline-flex; width:15px; color:#9aa7b8; }
    .tc-btick svg { width:15px; height:auto; }
    .tc-btick.read { color:#2563eb; }
    .tc-thread-empty { margin:auto; color:#94a3b8; font-size:13.5px; }
    .tc-msg { position:relative; }
    .tc-bubble { cursor:default; }

    /* Three-dots action trigger on each message (hover on desktop, always on touch). */
    .tc-dots { position:absolute; top:0; opacity:0; width:26px; height:26px; padding:0; border:1px solid var(--pro-line,#e6ebf2); background:var(--pro-surface,#fff); color:#64748b; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(15,23,42,.14); transition:opacity .12s; }
    .tc-dots svg { width:15px; height:15px; }
    .tc-msg:hover .tc-dots, .tc-dots:focus-visible { opacity:1; }
    .tc-msg.mine .tc-dots { left:-34px; }
    .tc-msg:not(.mine) .tc-dots { right:-34px; }
    @media (hover:none){ .tc-dots { opacity:1; } }
    /* These elements set their own display in the class, which would otherwise
       beat the UA [hidden] rule and make them impossible to hide. Force it. */
    .tc-menu[hidden], .tc-menu-item[hidden], .tc-reply-bar[hidden], .tc-modal[hidden],
    .tc-pending[hidden], .tc-progress[hidden], .tc-lightbox[hidden] { display:none !important; }

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

    /* ==========================================================================
       PREMIUM LAYER — immersive aurora-glass redesign (overrides the above)
       ========================================================================== */
    .tc-wrap { gap:18px; }

    .tc-list, .tc-thread {
        border-radius:24px;
        border:1px solid rgba(148,163,184,.18);
        background:linear-gradient(180deg, rgba(255,255,255,.94), rgba(255,255,255,.78));
        backdrop-filter:blur(22px) saturate(150%);
        -webkit-backdrop-filter:blur(22px) saturate(150%);
        box-shadow:0 24px 60px -26px rgba(30,41,59,.42), 0 2px 12px rgba(30,41,59,.05);
    }

    .tc-list-head { padding:20px 20px 14px; border-bottom:1px solid rgba(148,163,184,.14); }
    .tc-list-head h2 { font-size:18px; letter-spacing:-.02em; background:linear-gradient(120deg,#4f46e5,#7c3aed 55%,#ec4899); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .tc-list-head p { color:#94a3b8; }

    .tc-contacts { padding:10px; }
    .tc-contact { position:relative; border-radius:15px; transition:background .16s, transform .14s, box-shadow .16s; }
    .tc-contact:hover { transform:translateX(2px); background:rgba(99,102,241,.06); }
    .tc-contact.active { background:linear-gradient(90deg, rgba(99,102,241,.16), rgba(124,58,237,.05)); box-shadow:inset 0 0 0 1px rgba(99,102,241,.20); }
    .tc-contact.active::before { content:''; position:absolute; left:2px; top:13px; bottom:13px; width:3px; border-radius:3px; background:linear-gradient(#6366f1,#7c3aed); }
    .tc-avatar { border-radius:14px; box-shadow:0 6px 14px -4px rgba(30,41,59,.35); }
    .tc-unread { background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 5px 12px -3px rgba(99,102,241,.6); }

    .tc-thread-head { padding:16px 22px; border-bottom:1px solid rgba(148,163,184,.14); background:linear-gradient(180deg, rgba(255,255,255,.7), rgba(255,255,255,.35)); backdrop-filter:blur(10px); }
    .tc-thread-head .tc-avatar { box-shadow:0 0 0 2px #fff, 0 0 0 4px rgba(99,102,241,.4), 0 8px 18px -6px rgba(99,102,241,.5); }
    .tc-th-name { font-size:16px; font-weight:800; letter-spacing:-.01em; }

    /* The aurora lives on the (non-scrolling, clipped) thread panel — never
       inside the scrolling message list — so it can't inflate the scroll area
       or create a stray horizontal scrollbar. */
    .tc-thread {
        position:relative;
        background:
            radial-gradient(900px 480px at 8% -12%, rgba(99,102,241,.14), transparent 60%),
            radial-gradient(680px 460px at 112% 0%, rgba(236,72,153,.10), transparent 55%),
            radial-gradient(720px 560px at 50% 120%, rgba(56,189,248,.12), transparent 60%),
            linear-gradient(180deg,#f7f9ff,#eef2fb);
    }
    .tc-thread::before {
        content:''; position:absolute; inset:-12%; z-index:0; pointer-events:none; filter:blur(12px);
        background:
            radial-gradient(300px 300px at 28% 30%, rgba(99,102,241,.16), transparent 62%),
            radial-gradient(320px 320px at 72% 58%, rgba(236,72,153,.12), transparent 62%),
            radial-gradient(280px 280px at 52% 84%, rgba(56,189,248,.16), transparent 62%);
        animation:tcAurora 20s ease-in-out infinite alternate;
    }
    .tc-thread::after {
        content:''; position:absolute; inset:0; z-index:0; pointer-events:none;
        background-image:radial-gradient(rgba(79,70,229,.12) 1px, transparent 1.5px);
        background-size:22px 22px; opacity:.5;
        -webkit-mask-image:linear-gradient(180deg, transparent, #000 12%, #000 88%, transparent);
        mask-image:linear-gradient(180deg, transparent, #000 12%, #000 88%, transparent);
    }
    /* Keep real content above the backdrop. */
    .tc-thread-head, .tc-messages, .tc-reply-bar, .tc-composer { position:relative; z-index:1; }
    .tc-messages { padding:22px 22px 26px; background:transparent; overflow-x:hidden; }
    .tc-msg { animation:tcIn .3s cubic-bezier(.2,.7,.3,1) both; }

    .tc-bubble {
        border-radius:18px 18px 18px 7px;
        border:1px solid rgba(148,163,184,.16);
        background:rgba(255,255,255,.97);
        box-shadow:0 8px 20px -10px rgba(30,41,59,.32), 0 1px 2px rgba(30,41,59,.06);
    }
    .tc-msg.mine .tc-bubble {
        border-radius:18px 18px 7px 18px; border-color:transparent; color:#fff;
        background:linear-gradient(135deg,#6366f1,#7c3aed);
        box-shadow:0 12px 28px -10px rgba(99,102,241,.6), 0 2px 6px rgba(124,58,237,.25);
    }

    .tc-daysep { align-self:center; z-index:1; margin:8px 0 2px; }
    .tc-daysep span { font-size:11px; font-weight:700; color:#64748b; padding:5px 14px; border-radius:999px; background:rgba(255,255,255,.78); backdrop-filter:blur(8px); border:1px solid rgba(148,163,184,.2); box-shadow:0 3px 12px -5px rgba(30,41,59,.28); }

    .tc-react { border-radius:999px; background:rgba(255,255,255,.97); box-shadow:0 4px 10px -4px rgba(30,41,59,.3); }

    .tc-thread-empty { z-index:1; margin:auto; display:flex; flex-direction:column; align-items:center; gap:10px; color:#94a3b8; }
    .tc-thread-empty-emoji { font-size:46px; transform-origin:70% 70%; animation:tcWave 2.6s ease-in-out infinite; filter:drop-shadow(0 10px 18px rgba(99,102,241,.3)); }
    .tc-thread-empty p { font-size:14px; font-weight:600; }

    .tc-composer { padding:16px 18px; border-top:1px solid rgba(148,163,184,.14); background:linear-gradient(180deg, rgba(255,255,255,.45), rgba(255,255,255,.85)); backdrop-filter:blur(10px); }
    .tc-composer textarea { border-radius:16px; border:1.5px solid rgba(148,163,184,.3); background:rgba(255,255,255,.92); box-shadow:inset 0 1px 2px rgba(30,41,59,.04); }
    .tc-composer textarea:focus { border-color:#6366f1; background:#fff; box-shadow:0 0 0 4px rgba(99,102,241,.15); }
    .tc-send { width:46px; height:46px; border-radius:15px; background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 12px 24px -8px rgba(99,102,241,.62); }
    .tc-send:hover { transform:translateY(-2px) scale(1.03); filter:brightness(1.04); }

    .tc-contacts::-webkit-scrollbar, .tc-messages::-webkit-scrollbar { width:9px; }
    .tc-contacts::-webkit-scrollbar-thumb, .tc-messages::-webkit-scrollbar-thumb { background:rgba(99,102,241,.28); border-radius:9px; border:2px solid transparent; background-clip:padding-box; }
    .tc-contacts::-webkit-scrollbar-thumb:hover, .tc-messages::-webkit-scrollbar-thumb:hover { background:rgba(99,102,241,.45); background-clip:padding-box; }

    /* Attachments in a bubble */
    .tc-atts { display:flex; flex-direction:column; gap:8px; }
    .tc-atts:not(:last-child) { margin-bottom:8px; }
    .tc-bubble--media { padding:6px; background:rgba(255,255,255,.97); }
    .tc-msg.mine .tc-bubble--media { background:rgba(99,102,241,.14); }
    .tc-att-img { display:block; max-width:260px; border-radius:12px; overflow:hidden; line-height:0; cursor:zoom-in; }
    .tc-att-img img { width:100%; max-height:320px; object-fit:cover; display:block; }
    .tc-att-file { display:flex; align-items:center; gap:11px; min-width:220px; max-width:300px; padding:10px 12px; border-radius:12px; text-decoration:none; background:rgba(255,255,255,.9); border:1px solid rgba(148,163,184,.24); color:var(--pro-text,#0f172a); transition:transform .12s, box-shadow .14s; }
    .tc-att-file:hover { transform:translateY(-1px); box-shadow:0 8px 18px -8px rgba(30,41,59,.3); }
    .tc-msg.mine .tc-att-file { background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.28); color:#fff; }
    .tc-att-ic { flex:none; width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; }
    .tc-att-ic svg { width:19px; height:19px; }
    .tc-att-meta { flex:1; min-width:0; display:flex; flex-direction:column; }
    .tc-att-name { font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-att-size { font-size:11px; opacity:.7; }
    .tc-att-dl { flex:none; width:17px; height:17px; opacity:.75; }

    /* Attach button in composer */
    .tc-attach { flex:none; width:42px; height:42px; border:0; border-radius:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#64748b; background:rgba(148,163,184,.14); transition:background .14s, color .14s, transform .1s; }
    .tc-attach:hover { background:rgba(99,102,241,.14); color:#4f46e5; transform:translateY(-1px); }
    .tc-attach svg { width:20px; height:20px; }

    /* Pending files tray */
    .tc-pending { display:flex; flex-wrap:wrap; gap:8px; padding:10px 18px 0; position:relative; z-index:1; }
    .tc-pending-chip { display:flex; align-items:center; gap:8px; max-width:220px; padding:7px 10px; border-radius:11px; background:var(--pro-surface,#fff); border:1px solid rgba(148,163,184,.24); box-shadow:0 3px 10px -5px rgba(30,41,59,.25); font-size:12.5px; }
    .tc-pending-chip img { width:30px; height:30px; border-radius:7px; object-fit:cover; flex:none; }
    .tc-pending-chip .tc-chip-ic { width:30px; height:30px; border-radius:7px; flex:none; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; }
    .tc-pending-chip .tc-chip-ic svg { width:15px; height:15px; }
    .tc-chip-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; color:var(--pro-text,#0f172a); }
    .tc-chip-x { flex:none; border:0; background:transparent; color:#94a3b8; cursor:pointer; font-size:17px; line-height:1; padding:0 2px; }
    .tc-chip-x:hover { color:#ef4444; }

    /* Upload progress */
    .tc-progress { height:4px; margin:10px 18px 0; border-radius:999px; background:rgba(99,102,241,.14); overflow:hidden; position:relative; z-index:1; }
    .tc-progress i { display:block; height:100%; width:0; border-radius:999px; background:linear-gradient(90deg,#6366f1,#7c3aed); transition:width .15s; }

    /* Drag-over highlight */
    .tc-thread.tc-drag::after { content:'Drop files to send'; position:absolute; inset:12px; z-index:5; display:flex; align-items:center; justify-content:center; font-weight:800; color:#4f46e5; font-size:16px; border:2.5px dashed rgba(99,102,241,.6); border-radius:20px; background:rgba(99,102,241,.08); backdrop-filter:blur(2px); }

    /* Lightbox */
    .tc-lightbox { position:fixed; inset:0; z-index:1002; background:rgba(8,11,22,.85); display:flex; align-items:center; justify-content:center; padding:32px; }
    .tc-lightbox img { max-width:92vw; max-height:88vh; border-radius:12px; box-shadow:0 30px 80px rgba(0,0,0,.6); }
    .tc-lb-x { position:fixed; top:20px; right:24px; width:44px; height:44px; border:0; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; font-size:26px; line-height:1; cursor:pointer; }
    .tc-lb-x:hover { background:rgba(255,255,255,.25); }

    :root[data-theme="dark"] .tc-att-file { background:rgba(20,29,51,.9); border-color:rgba(51,65,85,.6); color:#e2e8f0; }
    :root[data-theme="dark"] .tc-pending-chip { background:#141d33; border-color:#233150; }
    :root[data-theme="dark"] .tc-attach { background:rgba(148,163,184,.12); }

    @keyframes tcIn { from { opacity:0; transform:translateY(9px) scale(.98); } to { opacity:1; transform:none; } }
    @keyframes tcAurora { from { transform:translate(-3%,-2%) rotate(0deg); } to { transform:translate(3%,3%) rotate(7deg); } }
    @keyframes tcWave { 0%,60%,100%{transform:rotate(0);} 10%{transform:rotate(14deg);} 20%{transform:rotate(-8deg);} 30%{transform:rotate(14deg);} 40%{transform:rotate(-4deg);} 50%{transform:rotate(10deg);} }
    @media (prefers-reduced-motion: reduce){ .tc-msg, .tc-messages::before, .tc-thread-empty-emoji { animation:none !important; } }

    :root[data-theme="dark"] .tc-list, :root[data-theme="dark"] .tc-thread { background:linear-gradient(180deg, rgba(17,24,39,.9), rgba(13,17,32,.72)); border-color:rgba(51,65,85,.55); box-shadow:0 24px 60px -26px rgba(0,0,0,.6); }
    :root[data-theme="dark"] .tc-messages { background:radial-gradient(900px 480px at 8% -12%, rgba(99,102,241,.18), transparent 60%), radial-gradient(680px 460px at 112% 0%, rgba(236,72,153,.12), transparent 55%), radial-gradient(720px 560px at 50% 120%, rgba(56,189,248,.14), transparent 60%), linear-gradient(180deg,#0b1120,#0a0e1c); }
    :root[data-theme="dark"] .tc-messages::after { background-image:radial-gradient(rgba(148,163,184,.14) 1px, transparent 1.5px); }
    :root[data-theme="dark"] .tc-bubble { background:rgba(20,29,51,.92); border-color:rgba(51,65,85,.6); color:#e2e8f0; }
    :root[data-theme="dark"] .tc-thread-head, :root[data-theme="dark"] .tc-composer { background:linear-gradient(180deg, rgba(17,24,39,.7), rgba(13,17,32,.4)); }
    :root[data-theme="dark"] .tc-composer textarea { background:rgba(11,17,32,.9); color:#e2e8f0; border-color:rgba(51,65,85,.6); }
    :root[data-theme="dark"] .tc-daysep span { background:rgba(20,29,51,.82); color:#94a3b8; border-color:rgba(51,65,85,.6); }
    :root[data-theme="dark"] .tc-react { background:rgba(20,29,51,.92); }
    :root[data-theme="dark"] .tc-contact:hover { background:rgba(99,102,241,.12); }
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
    var DOTS = '<button type="button" class="tc-dots" aria-label="Message actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button>';
    var FILE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    var DL_SVG = '<svg class="tc-att-dl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
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

    function attsHtml(list){
        if (!list || !list.length) return '';
        return '<div class="tc-atts">' + list.map(function (a) {
            if (a.image) return '<a class="tc-att-img" href="' + a.url + '" data-lightbox><img src="' + a.url + '" alt="' + esc(a.name) + '" loading="lazy"></a>';
            return '<a class="tc-att-file" href="' + a.download + '">'
                + '<span class="tc-att-ic">' + FILE_SVG + '</span>'
                + '<span class="tc-att-meta"><span class="tc-att-name">' + esc(a.name) + '</span><span class="tc-att-size">' + esc(a.size) + '</span></span>'
                + DL_SVG + '</a>';
        }).join('') + '</div>';
    }

    // Full inner HTML of a message row — kept in step with partials/team-message.blade.php.
    function bubbleInner(m){
        var atts = (!m.deleted && m.attachments && m.attachments.length) ? m.attachments : [];
        var onlyMedia = atts.length && !m.body;
        var h = '<div class="tc-bubble' + (m.deleted ? ' deleted' : '') + (onlyMedia ? ' tc-bubble--media' : '') + '">';
        if (m.reply && !m.deleted) h += '<div class="tc-quote"><span class="tc-quote-author">' + esc(m.reply.author)
            + '</span><span class="tc-quote-text">' + esc(m.reply.text) + '</span></div>';
        if (m.forwarded && !m.deleted) h += '<div class="tc-fwd">↪ Forwarded</div>';
        h += attsHtml(atts);
        if (m.deleted) h += '<div class="tc-text">🚫 This message was deleted</div>';
        else if (m.body) h += '<div class="tc-text">' + esc(m.body) + '</div>';
        h += '</div>';
        var tick = (m.mine && !m.deleted) ? '<span class="tc-btick">' + TICK + '</span>' : '';
        h += '<div class="tc-time">' + esc(m.at) + tick + '</div>';
        h += '<div class="tc-reacts">' + reactsHtml(m.reactions) + '</div>';
        if (!m.deleted) h += DOTS;
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
            var txt = m.deleted ? 'This message was deleted' : (m.body ? m.body : '📎 Attachment');
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

    // ---------- Attachments ----------
    var fileInput = document.getElementById('tcFile');
    var attachBtn = document.getElementById('tcAttach');
    var pendingBox = document.getElementById('tcPending');
    var progressBox = document.getElementById('tcProgress');
    var progressBar = progressBox ? progressBox.querySelector('i') : null;
    var ALLOWED = ['jpg','jpeg','png','gif','webp','pdf','zip','doc','docx','xls','xlsx','csv','txt','ppt','pptx'];
    var IMG_EXT = ['jpg','jpeg','png','gif','webp'];
    var MAX_BYTES = 25600 * 1024;
    var pending = [];

    function extOf(name){ var i = name.lastIndexOf('.'); return i >= 0 ? name.slice(i + 1).toLowerCase() : ''; }

    function addFiles(list){
        Array.prototype.slice.call(list || []).forEach(function (f) {
            if (pending.length >= 10) { toast('Up to 10 files per message'); return; }
            if (ALLOWED.indexOf(extOf(f.name)) < 0) { toast(f.name + ': file type not allowed'); return; }
            if (f.size > MAX_BYTES) { toast(f.name + ': larger than 25 MB'); return; }
            var it = { file: f };
            if (IMG_EXT.indexOf(extOf(f.name)) >= 0) it.url = URL.createObjectURL(f);
            pending.push(it);
        });
        renderPending();
    }
    function renderPending(){
        if (!pending.length) { pendingBox.hidden = true; pendingBox.innerHTML = ''; return; }
        pendingBox.hidden = false;
        pendingBox.innerHTML = pending.map(function (it, i) {
            var thumb = it.url ? '<img src="' + it.url + '">' : '<span class="tc-chip-ic">' + FILE_SVG + '</span>';
            return '<span class="tc-pending-chip">' + thumb + '<span class="tc-chip-name">' + esc(it.file.name)
                + '</span><button type="button" class="tc-chip-x" data-i="' + i + '" aria-label="Remove">&times;</button></span>';
        }).join('');
    }
    function clearPending(){ pending.forEach(function (it) { if (it.url) URL.revokeObjectURL(it.url); }); pending = []; renderPending(); }

    if (attachBtn) attachBtn.addEventListener('click', function () { fileInput.click(); });
    if (fileInput) fileInput.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });
    if (pendingBox) pendingBox.addEventListener('click', function (e) {
        var x = e.target.closest('.tc-chip-x'); if (!x) return;
        var i = parseInt(x.dataset.i, 10);
        if (pending[i] && pending[i].url) URL.revokeObjectURL(pending[i].url);
        pending.splice(i, 1); renderPending();
    });

    // Drag & drop anywhere on the thread panel.
    var thread = box.closest('.tc-thread');
    if (thread){
        ['dragenter','dragover'].forEach(function (ev) { thread.addEventListener(ev, function (e) {
            if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) { e.preventDefault(); thread.classList.add('tc-drag'); }
        }); });
        thread.addEventListener('dragleave', function (e) { if (!e.relatedTarget || !thread.contains(e.relatedTarget)) thread.classList.remove('tc-drag'); });
        thread.addEventListener('drop', function (e) {
            thread.classList.remove('tc-drag');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) { e.preventDefault(); addFiles(e.dataTransfer.files); }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body && !pending.length) return;
        var btn = form.querySelector('.tc-send'); btn.disabled = true;

        var fd = new FormData();
        fd.append('_token', csrf); fd.append('recipient_id', withId);
        if (body) fd.append('body', body);
        if (replyId) fd.append('reply_to_id', replyId);
        pending.forEach(function (it) { fd.append('attachments[]', it.file, it.file.name); });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', STORE);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        if (pending.length && progressBox) { progressBox.hidden = false; progressBar.style.width = '0%'; }
        xhr.upload.onprogress = function (ev) { if (ev.lengthComputable && progressBar) progressBar.style.width = Math.round(ev.loaded / ev.total * 100) + '%'; };
        xhr.onload = function () {
            btn.disabled = false; if (progressBox) progressBox.hidden = true;
            var res = null; try { res = JSON.parse(xhr.responseText); } catch (err) {}
            if (xhr.status >= 200 && xhr.status < 300 && res && res.ok) {
                append(res.message); input.value = ''; grow(); cancelReply(); clearPending(); toBottom(); input.focus();
            } else {
                toast(res && res.message ? res.message : 'Could not send message');
            }
        };
        xhr.onerror = function () { btn.disabled = false; if (progressBox) progressBox.hidden = true; toast('Network error — try again'); };
        xhr.send(fd);
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

    // ---------- Image lightbox ----------
    var lightbox = document.getElementById('tcLightbox');
    var lbImg = document.getElementById('tcLbImg');
    if (lightbox) document.body.appendChild(lightbox);
    function closeLightbox(){ if (lightbox) { lightbox.hidden = true; lbImg.src = ''; } }
    box.addEventListener('click', function (e) {
        var img = e.target.closest('.tc-att-img'); if (!img) return;
        e.preventDefault();
        lbImg.src = img.getAttribute('href'); lightbox.hidden = false;
    });
    if (lightbox){
        document.getElementById('tcLbClose').addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function (e) { if (e.target === lightbox) closeLightbox(); });
    }

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

    // Three-dots trigger opens the same action menu.
    box.addEventListener('click', function (e) {
        var dots = e.target.closest('.tc-dots'); if (!dots) return;
        e.stopPropagation();
        var el = dots.closest('.tc-msg'); if (!el) return;
        var r = dots.getBoundingClientRect();
        openMenu(r.left, r.bottom + 4, el);
    });

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
        if (e.key === 'Escape'){ closeMenu(); if (fwdModal) fwdModal.hidden = true; closeLightbox(); }
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
