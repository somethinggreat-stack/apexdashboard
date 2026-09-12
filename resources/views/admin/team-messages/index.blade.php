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
            <div class="tc-list-head-row">
                <h2>Team Chat</h2>
                <button type="button" class="tc-newgroup" id="tcNewGroup" title="New group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                    <span>New group</span>
                </button>
            </div>
            <p>Message any teammate or start a group.</p>
            <div class="tc-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="tcSearch" placeholder="Search people & messages" autocomplete="off">
                <button type="button" id="tcSearchClear" hidden aria-label="Clear">&times;</button>
            </div>
            <div class="tc-filters">
                <button type="button" class="tc-filter active" data-filter="all">All</button>
                <button type="button" class="tc-filter" data-filter="unread">Unread</button>
                <button type="button" class="tc-filter" data-filter="fav">Favorites</button>
            </div>
        </div>
        <div class="tc-contacts" id="tcContacts">
            <div class="tc-section" data-section="fav" {{ count($favorites) ? '' : 'hidden' }}>Favorites</div>
            <div id="tcFavList">
                @foreach ($favorites as $it)
                    @include('partials.team-chat-row', ['it' => $it])
                @endforeach
            </div>
            <div class="tc-section" data-section="chats">Chats</div>
            <div id="tcChatList">
                @forelse ($chats as $it)
                    @include('partials.team-chat-row', ['it' => $it])
                @empty
                    <div class="tc-empty">No teammates to message yet.</div>
                @endforelse
            </div>
            <div class="tc-search-results" id="tcSearchResults" hidden></div>
            <div class="tc-no-results" id="tcNoResults" hidden>No matches.</div>
        </div>
    </aside>

    <section class="tc-thread">
        @if ($active || $peer)
            @php $isGroup = $active && $active->isGroup(); @endphp
            <div class="tc-thread-head">
                @if ($isGroup)
                    <span class="tc-avatar sm tc-avatar--group">{{ $active->icon ?: '💬' }}</span>
                    <div class="tc-th-info">
                        <div class="tc-th-name">{{ $active->name }}</div>
                        <div class="tc-th-role">{{ $members->count() }} members<span id="tcOnlineCount">{{ $onlineCount > 0 ? ' · '.$onlineCount.' online' : '' }}</span></div>
                    </div>
                    <button type="button" class="tc-th-btn" id="tcMembersBtn" title="Members">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>{{ $members->count() }}</span>
                    </button>
                @else
                    <span class="tc-av">{!! $avatar($peer, 'sm') !!}<i class="tc-dot {{ $peerOnline ? 'on' : '' }}" id="tcHeaderDot"></i></span>
                    <div class="tc-th-info">
                        <div class="tc-th-name">{{ $peer->full_name }}</div>
                        <div class="tc-th-role tc-presence" id="tcHeaderSeen">{{ $peerSeen }}</div>
                    </div>
                @endif
            </div>

            @if ($pinned->isNotEmpty())
                <div class="tc-pinned" id="tcPinned">
                    <button type="button" class="tc-pinned-head" id="tcPinnedHead">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>
                        <span class="tc-pinned-latest">{{ $pinned->first()->body !== '' ? \Illuminate\Support\Str::limit($pinned->first()->body, 60) : '📎 Attachment' }}</span>
                        @if ($pinned->count() > 1)<span class="tc-pinned-count">{{ $pinned->count() }}</span>@endif
                    </button>
                    <div class="tc-pinned-drop" id="tcPinnedDrop" hidden>
                        @foreach ($pinned as $pm)
                            <div class="tc-pinned-item" data-goto="{{ $pm->id }}">
                                <span class="tc-pinned-text">{{ optional($pm->sender)->full_name ? \Illuminate\Support\Str::of(optional($pm->sender)->full_name)->before(' ').': ' : '' }}{{ $pm->body !== '' ? \Illuminate\Support\Str::limit($pm->body, 70) : '📎 Attachment' }}</span>
                                <button type="button" class="tc-pinned-x" data-unpin="{{ $pm->id }}" title="Unpin">&times;</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="tc-messages" id="tcMessages"
                 @if ($active) data-conversation="{{ $active->id }}" @endif
                 @if ($peer && ! $active) data-peer="{{ $peer->id }}" @endif
                 data-group="{{ $isGroup ? 1 : 0 }}" data-last="{{ $messages->last()->id ?? 0 }}">
                @php $tcLastDay = null; $tcPrevSender = null; $tcNow = \Illuminate\Support\Carbon::now($tz); @endphp
                @forelse ($messages as $msg)
                    @php
                        $d = $msg->created_at->timezone($tz);
                        $dayKey = $d->format('Y-m-d');
                        $dayLabel = $d->isSameDay($tcNow) ? 'Today' : ($d->isSameDay($tcNow->copy()->subDay()) ? 'Yesterday' : $d->format('F j, Y'));
                        $dayChanged = $dayKey !== $tcLastDay;
                        $showSender = $isGroup && ! $msg->isSystem() && ($dayChanged || $tcPrevSender !== $msg->sender_id);
                        $tcPrevSender = $msg->isSystem() ? null : $msg->sender_id;
                    @endphp
                    @if ($dayChanged)
                        <div class="tc-daysep"><span>{{ $dayLabel }}</span></div>
                        @php $tcLastDay = $dayKey; @endphp
                    @endif
                    @include('partials.team-message', ['msg' => $msg, 'isGroup' => $isGroup, 'showSender' => $showSender, 'readUpTo' => $readUpTo])
                @empty
                    <div class="tc-thread-empty">
                        <div class="tc-thread-empty-emoji">{{ $isGroup ? '🎉' : '👋' }}</div>
                        <p>{{ $isGroup ? 'Group created — say hello to the team!' : 'No messages yet — say hello!' }}</p>
                    </div>
                @endforelse
            </div>

            <div class="tc-seen" id="tcSeen" hidden></div>
            <div class="tc-typing" id="tcTyping" hidden>
                <span class="tc-typing-dots"><i></i><i></i><i></i></span>
                <span id="tcTypingText"></span>
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
                @if ($active)
                    <input type="hidden" name="conversation_id" value="{{ $active->id }}">
                @else
                    <input type="hidden" name="recipient_id" value="{{ $peer->id }}">
                @endif
                <input type="file" id="tcFile" multiple hidden accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx">
                <button type="button" class="tc-attach" id="tcAttach" aria-label="Attach a file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </button>
                <textarea name="body" id="tcInput" rows="1" placeholder="Message {{ $isGroup ? $active->name : $peer->full_name }}…" maxlength="5000"></textarea>
                <button type="submit" class="tc-send" aria-label="Send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>

            {{-- Full emoji picker for reactions (moved to <body> by JS) --}}
            <div class="tc-emoji-picker" id="tcEmojiPicker" hidden></div>

            {{-- Image lightbox (moved to <body> by JS) --}}
            <div class="tc-lightbox" id="tcLightbox" hidden>
                <button type="button" class="tc-lb-x" id="tcLbClose" aria-label="Close">&times;</button>
                <img src="" alt="" id="tcLbImg">
            </div>

            {{-- Message action menu (WhatsApp-style). Moved to <body> by JS so position:fixed is exact. --}}
            <div class="tc-menu" id="tcMenu" hidden>
                <div class="tc-menu-emoji" id="tcMenuEmoji"></div>
                <button type="button" class="tc-menu-item" data-act="reply">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg> Reply
                </button>
                <button type="button" class="tc-menu-item" data-act="copy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy
                </button>
                <button type="button" class="tc-menu-item" data-act="forward">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg> Forward
                </button>
                <button type="button" class="tc-menu-item" data-act="pin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg> <span data-pin-label>Pin</span>
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

            @if ($isGroup)
                @php $iAmAdmin = optional($members->firstWhere('admin_id', $me->id))->role === 'admin'; @endphp
                <div class="tc-modal" id="tcMembers" hidden data-group="{{ $active->id }}" data-admin="{{ $iAmAdmin ? 1 : 0 }}">
                    <div class="tc-modal-card">
                        <div class="tc-modal-head">
                            <span>{{ $active->name }} · {{ $members->count() }} members</span>
                            <button type="button" id="tcMembersClose" aria-label="Close">&times;</button>
                        </div>
                        @if ($iAmAdmin)
                            <div class="tc-member-tools">
                                <input type="text" id="tcRenameName" class="tc-inp" value="{{ $active->name }}" maxlength="80" placeholder="Group name">
                                <button type="button" class="tc-btn-mini" id="tcRenameBtn">Rename</button>
                            </div>
                        @endif
                        <div class="tc-modal-list">
                            @foreach ($members->sortByDesc('role') as $mp)
                                <div class="tc-member-row" data-admin="{{ $mp->admin_id }}">
                                    @if ($mp->admin && $mp->admin->avatarUrl())
                                        <span class="tc-avatar sm has-img"><img src="{{ $mp->admin->avatarUrl() }}" alt=""></span>
                                    @else
                                        <span class="tc-avatar sm" style="background:{{ $color(optional($mp->admin)->full_name) }}">{{ $mono(optional($mp->admin)->full_name) }}</span>
                                    @endif
                                    <span class="tc-member-name">{{ optional($mp->admin)->full_name }}{{ $mp->admin_id === $me->id ? ' (you)' : '' }}</span>
                                    @if ($mp->role === 'admin')<span class="tc-member-badge">Admin</span>@endif
                                    @if ($iAmAdmin && $mp->admin_id !== $me->id)
                                        <button type="button" class="tc-member-remove" data-admin="{{ $mp->admin_id }}" title="Remove">&times;</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($iAmAdmin && $addable->isNotEmpty())
                            <div class="tc-member-add">
                                <div class="tc-member-add-title">Add members</div>
                                <div class="tc-member-add-list">
                                    @foreach ($addable as $t)
                                        <label class="tc-addable"><input type="checkbox" value="{{ $t->id }}"> {{ $t->full_name }}</label>
                                    @endforeach
                                </div>
                                <button type="button" class="tc-btn-mini" id="tcAddMembersBtn">Add selected</button>
                            </div>
                        @endif
                        <button type="button" class="tc-leave-btn" id="tcLeaveBtn">Leave group</button>
                    </div>
                </div>
            @endif
        @else
            <div class="tc-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <p>Select a teammate on the left to start chatting.</p>
            </div>
        @endif
    </section>
</div>

{{-- New group modal (always available from the sidebar button) --}}
<div class="tc-modal" id="tcGroupModal" hidden>
    <div class="tc-modal-card">
        <div class="tc-modal-head">
            <span>New group</span>
            <button type="button" id="tcGroupClose" aria-label="Close">&times;</button>
        </div>
        <div class="tc-group-form">
            <div class="tc-group-top">
                <button type="button" class="tc-icon-pick" id="tcIconPick">💬</button>
                <input type="text" id="tcGroupName" class="tc-inp" placeholder="Group name" maxlength="80">
            </div>
            <div class="tc-icon-row" id="tcIconRow">
                @foreach ($groupIcons as $gi)
                    <button type="button" class="tc-icon-opt {{ $loop->first ? 'sel' : '' }}" data-icon="{{ $gi }}">{{ $gi }}</button>
                @endforeach
            </div>
            <div class="tc-group-members-title">Add people</div>
            <div class="tc-modal-list tc-group-members">
                @foreach ($teammates as $t)
                    <label class="tc-addable"><input type="checkbox" value="{{ $t->id }}">
                        @if ($t->avatarUrl())
                            <span class="tc-avatar sm has-img"><img src="{{ $t->avatarUrl() }}" alt=""></span>
                        @else
                            <span class="tc-avatar sm" style="background:{{ $color($t->full_name) }}">{{ $mono($t->full_name) }}</span>
                        @endif
                        <span>{{ $t->full_name }}</span>
                    </label>
                @endforeach
            </div>
            <button type="button" class="tc-btn-primary" id="tcGroupCreate">Create group</button>
        </div>
    </div>
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
    .tc-dots { position:absolute; top:2px; opacity:.55; width:24px; height:24px; padding:0; border:1px solid var(--pro-line,#e6ebf2); background:var(--pro-surface,#fff); color:#64748b; border-radius:7px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(15,23,42,.12); transition:opacity .12s, transform .1s; }
    .tc-dots svg { width:14px; height:14px; }
    .tc-msg:hover .tc-dots, .tc-dots:focus-visible { opacity:1; }
    .tc-dots:hover { transform:scale(1.08); }
    .tc-msg.mine .tc-dots { left:-32px; }
    .tc-msg:not(.mine) .tc-dots { right:-32px; }
    /* These elements set their own display in the class, which would otherwise
       beat the UA [hidden] rule and make them impossible to hide. Force it. */
    .tc-menu[hidden], .tc-menu-item[hidden], .tc-reply-bar[hidden], .tc-modal[hidden],
    .tc-pending[hidden], .tc-progress[hidden], .tc-lightbox[hidden],
    .tc-typing[hidden], .tc-seen[hidden], .tc-emoji-picker[hidden],
    .tc-pinned-drop[hidden], .tc-search-results[hidden], .tc-no-results[hidden] { display:none !important; }

    /* Search + filters */
    .tc-search { position:relative; display:flex; align-items:center; margin-top:12px; }
    .tc-search svg { position:absolute; left:12px; width:16px; height:16px; color:#94a3b8; pointer-events:none; }
    .tc-search input { width:100%; border:1.5px solid rgba(148,163,184,.28); border-radius:12px; padding:9px 30px 9px 34px; font:inherit; font-size:13px; background:var(--pro-soft,#f7f9fc); color:var(--pro-text,#0f172a); outline:none; }
    .tc-search input:focus { border-color:#6366f1; background:var(--pro-surface,#fff); box-shadow:0 0 0 3px rgba(99,102,241,.13); }
    #tcSearchClear { position:absolute; right:8px; border:0; background:transparent; color:#94a3b8; font-size:18px; line-height:1; cursor:pointer; padding:2px 4px; }
    .tc-filters { display:flex; gap:6px; margin-top:10px; }
    .tc-filter { border:1px solid rgba(148,163,184,.28); background:transparent; color:#64748b; cursor:pointer; padding:5px 12px; border-radius:999px; font:inherit; font-size:12px; font-weight:700; }
    .tc-filter:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-filter.active { background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; border-color:transparent; }

    .tc-section { padding:12px 12px 4px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; }
    .tc-no-results { padding:24px 14px; text-align:center; color:#94a3b8; font-size:13px; }

    /* Row favorite/mute actions */
    .tc-contact { position:relative; }
    .tc-row-actions { position:absolute; right:8px; top:50%; transform:translateY(-50%); display:none; gap:2px; background:var(--pro-surface,#fff); border-radius:10px; padding:2px; box-shadow:0 4px 12px -4px rgba(15,23,42,.3); }
    .tc-contact:hover .tc-row-actions { display:flex; }
    .tc-row-act { border:0; background:transparent; cursor:pointer; width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#94a3b8; }
    .tc-row-act:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-row-act svg { width:16px; height:16px; }
    .tc-fav-btn svg { fill:none; }
    .tc-fav-btn.on { color:#f59e0b; } .tc-fav-btn.on svg { fill:#f59e0b; }
    .tc-mute-btn.on { color:#6366f1; }
    .tc-mute-ic { flex:none; color:#94a3b8; display:inline-flex; } .tc-mute-ic svg { width:14px; height:14px; }

    /* Pinned banner */
    .tc-pinned { position:relative; z-index:2; border-bottom:1px solid rgba(148,163,184,.14); background:rgba(250,204,21,.08); }
    .tc-pinned-head { display:flex; align-items:center; gap:9px; width:100%; border:0; background:transparent; cursor:pointer; padding:10px 20px; text-align:left; font:inherit; }
    .tc-pinned-head svg { width:15px; height:15px; color:#ca8a04; flex:none; }
    .tc-pinned-latest { flex:1; min-width:0; font-size:12.5px; font-weight:600; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-pinned-count { flex:none; font-size:11px; font-weight:800; color:#fff; background:#ca8a04; border-radius:999px; padding:1px 7px; }
    .tc-pinned-drop { flex-direction:column; max-height:180px; overflow-y:auto; padding:4px 12px 10px; }
    .tc-pinned-item { display:flex; align-items:center; gap:8px; padding:7px 9px; border-radius:9px; cursor:pointer; }
    .tc-pinned-item:hover { background:rgba(250,204,21,.12); }
    .tc-pinned-text { flex:1; min-width:0; font-size:12.5px; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-pinned-x { flex:none; border:0; background:transparent; color:#94a3b8; font-size:16px; line-height:1; cursor:pointer; padding:0 4px; }
    .tc-pinned-x:hover { color:#ef4444; }
    .tc-msg[data-pinned="1"] .tc-bubble { box-shadow:0 0 0 1.5px rgba(202,138,4,.4), 0 8px 20px -10px rgba(30,41,59,.32); }

    /* Search results */
    .tc-search-results { padding:4px 8px 10px; }
    .tc-sr-item { display:block; padding:9px 11px; border-radius:11px; text-decoration:none; }
    .tc-sr-item:hover { background:var(--pro-soft,#f5f7fb); }
    .tc-sr-title { font-size:13px; font-weight:700; color:var(--pro-text,#0f172a); }
    .tc-sr-snip { font-size:12px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    :root[data-theme="dark"] .tc-search input { background:#0b1120; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-row-actions { background:#141d33; }
    :root[data-theme="dark"] .tc-pinned { background:rgba(250,204,21,.06); }
    :root[data-theme="dark"] .tc-sr-item:hover, :root[data-theme="dark"] .tc-filter:hover { background:#182444; }

    /* Emoji reaction quick bar + full picker */
    .tc-menu-emoji button { position:relative; }
    .tc-emoji-more { font-size:16px !important; color:#6366f1; font-weight:800; }
    .tc-emoji-picker { position:fixed; z-index:1003; width:308px; max-height:320px; overflow-y:auto; padding:10px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:16px; box-shadow:0 20px 50px rgba(15,23,42,.28); }
    .tc-emoji-cat { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; margin:8px 4px 4px; }
    .tc-emoji-cat:first-child { margin-top:2px; }
    .tc-emoji-grid { display:grid; grid-template-columns:repeat(8, 1fr); gap:2px; }
    .tc-emoji-grid button { border:0; background:transparent; font-size:20px; line-height:1; padding:5px 0; border-radius:8px; cursor:pointer; }
    .tc-emoji-grid button:hover { background:var(--pro-soft,#f1f5f9); transform:scale(1.15); }
    :root[data-theme="dark"] .tc-emoji-picker { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-emoji-grid button:hover { background:#182444; }

    /* Presence dots */
    .tc-av { position:relative; flex:none; display:inline-flex; }
    .tc-dot { position:absolute; right:-2px; bottom:-2px; width:12px; height:12px; border-radius:50%; background:#cbd5e1; border:2.5px solid var(--pro-surface,#fff); box-shadow:0 0 0 .5px rgba(15,23,42,.06); }
    .tc-dot.on { background:#22c55e; }
    .tc-presence { display:flex; align-items:center; }
    .tc-presence.online { color:#16a34a; font-weight:700; }

    /* Typing indicator */
    .tc-typing { align-items:center; gap:9px; padding:6px 20px; position:relative; z-index:1; font-size:12.5px; font-weight:600; color:#6366f1; }
    .tc-typing-dots { display:inline-flex; gap:3px; }
    .tc-typing-dots i { width:6px; height:6px; border-radius:50%; background:#6366f1; opacity:.5; animation:tcTypeBounce 1.2s infinite; }
    .tc-typing-dots i:nth-child(2){ animation-delay:.2s; } .tc-typing-dots i:nth-child(3){ animation-delay:.4s; }
    @keyframes tcTypeBounce { 0%,60%,100%{ transform:translateY(0); opacity:.4; } 30%{ transform:translateY(-4px); opacity:1; } }

    /* "Seen by" read receipts (groups) */
    .tc-seen { justify-content:flex-end; align-items:center; gap:6px; padding:4px 20px 0; position:relative; z-index:1; font-size:11px; font-weight:600; color:#94a3b8; }
    .tc-seen .tc-seen-avs { display:inline-flex; }
    .tc-seen .tc-avatar { width:18px; height:18px; border-radius:50%; font-size:8px; margin-left:-5px; border:2px solid var(--pro-surface,#fff); box-shadow:none; }
    .tc-seen .tc-avatar:first-child { margin-left:0; }

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

    /* Groups: sidebar icon, header, sender chips, system messages */
    .tc-list-head-row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .tc-newgroup { display:inline-flex; align-items:center; gap:6px; border:0; cursor:pointer; padding:6px 11px; border-radius:999px; font:inherit; font-size:12px; font-weight:700; color:#fff; background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 6px 14px -5px rgba(99,102,241,.6); }
    .tc-newgroup:hover { filter:brightness(1.05); transform:translateY(-1px); }
    .tc-newgroup svg { width:15px; height:15px; }
    .tc-avatar--group { background:linear-gradient(135deg,#4f46e5,#7c3aed) !important; font-size:20px; }
    .tc-avatar.sm.tc-avatar--group { font-size:17px; }
    .tc-avatar.xs { width:26px; height:26px; border-radius:8px; font-size:10px; box-shadow:0 3px 8px -3px rgba(15,23,42,.35); }
    .tc-avatar.xs.has-img { overflow:hidden; } .tc-avatar.xs.has-img img { width:100%; height:100%; object-fit:cover; }
    .tc-th-info { flex:1; min-width:0; }
    .tc-th-btn { display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(148,163,184,.3); background:var(--pro-surface,#fff); color:#475569; cursor:pointer; padding:7px 12px; border-radius:11px; font:inherit; font-size:12.5px; font-weight:700; }
    .tc-th-btn:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-th-btn svg { width:16px; height:16px; }

    .tc-sender { display:flex; align-items:center; gap:7px; margin:2px 0 3px; }
    .tc-sender-name { font-size:12px; font-weight:800; }

    .tc-sys { align-self:center; z-index:1; margin:6px 0; max-width:80%; }
    .tc-sys span { display:inline-block; padding:5px 13px; border-radius:999px; font-size:11.5px; font-weight:600; color:#64748b; background:rgba(255,255,255,.7); border:1px solid rgba(148,163,184,.18); backdrop-filter:blur(6px); }

    /* Member panel + group form */
    .tc-inp { flex:1; min-width:0; border:1.5px solid rgba(148,163,184,.3); border-radius:11px; padding:9px 12px; font:inherit; font-size:14px; background:var(--pro-surface,#fff); color:var(--pro-text,#0f172a); outline:none; }
    .tc-inp:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.14); }
    .tc-member-tools { display:flex; gap:8px; padding:12px 16px 4px; }
    .tc-btn-mini { flex:none; border:0; cursor:pointer; padding:0 14px; border-radius:11px; font:inherit; font-size:12.5px; font-weight:700; color:#fff; background:linear-gradient(135deg,#6366f1,#7c3aed); }
    .tc-member-row { display:flex; align-items:center; gap:11px; padding:8px 10px; border-radius:11px; }
    .tc-member-row:hover { background:var(--pro-soft,#f5f7fb); }
    .tc-member-name { flex:1; min-width:0; font-size:14px; font-weight:600; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-member-badge { flex:none; font-size:10.5px; font-weight:800; color:#4f46e5; background:rgba(99,102,241,.12); padding:2px 8px; border-radius:999px; }
    .tc-member-remove { flex:none; border:0; background:transparent; color:#94a3b8; font-size:19px; line-height:1; cursor:pointer; padding:0 4px; }
    .tc-member-remove:hover { color:#ef4444; }
    .tc-member-add { border-top:1px solid rgba(148,163,184,.16); padding:12px 16px; }
    .tc-member-add-title, .tc-group-members-title { font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:8px; }
    .tc-member-add-list { display:flex; flex-direction:column; gap:2px; max-height:150px; overflow-y:auto; margin-bottom:10px; }
    .tc-addable { display:flex; align-items:center; gap:9px; padding:7px 8px; border-radius:9px; cursor:pointer; font-size:13.5px; font-weight:600; color:var(--pro-text,#0f172a); }
    .tc-addable:hover { background:var(--pro-soft,#f5f7fb); }
    .tc-addable input { width:16px; height:16px; accent-color:#6366f1; }
    .tc-leave-btn { margin:6px 16px 16px; border:1px solid rgba(239,68,68,.3); background:rgba(239,68,68,.06); color:#ef4444; cursor:pointer; padding:9px; border-radius:11px; font:inherit; font-size:13px; font-weight:700; }
    .tc-leave-btn:hover { background:rgba(239,68,68,.12); }

    .tc-group-form { padding:16px; }
    .tc-group-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .tc-icon-pick { flex:none; width:46px; height:46px; border-radius:13px; border:0; cursor:pointer; font-size:24px; background:linear-gradient(135deg,#4f46e5,#7c3aed); }
    .tc-icon-row { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:14px; }
    .tc-icon-opt { width:36px; height:36px; border-radius:10px; border:1.5px solid transparent; background:var(--pro-soft,#f1f5f9); cursor:pointer; font-size:18px; }
    .tc-icon-opt.sel { border-color:#6366f1; background:rgba(99,102,241,.12); }
    .tc-group-members { max-height:230px; }
    .tc-btn-primary { width:100%; margin-top:12px; border:0; cursor:pointer; padding:12px; border-radius:13px; font:inherit; font-size:14px; font-weight:800; color:#fff; background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 10px 22px -8px rgba(99,102,241,.6); }
    .tc-btn-primary:hover { filter:brightness(1.05); }

    :root[data-theme="dark"] .tc-sys span { background:rgba(20,29,51,.8); color:#94a3b8; border-color:rgba(51,65,85,.6); }
    :root[data-theme="dark"] .tc-th-btn, :root[data-theme="dark"] .tc-inp { background:#141d33; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-member-row:hover, :root[data-theme="dark"] .tc-addable:hover { background:#182444; }

    .tc-msg.tc-flash .tc-bubble { animation:tcFlash 1.3s ease; }
    @keyframes tcFlash { 0%,100%{ box-shadow:0 8px 20px -10px rgba(30,41,59,.32); } 30%{ box-shadow:0 0 0 3px rgba(99,102,241,.5); } }
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
    var CONV = box.dataset.conversation || '';
    var PEER_ID = box.dataset.peer || '';
    var IS_GROUP = box.dataset.group === '1';
    var lastId = parseInt(box.dataset.last, 10) || 0;
    var THREAD = @js(route('admin.team-messages.thread'));
    var STORE  = @js(route('admin.team-messages.store'));
    var GBASE  = @js(url('admin/team-messages/group'));
    var TC_PEERS = @js($teammates->map(fn ($t) => ['id' => $t->id, 'name' => $t->full_name, 'avatar' => $t->avatarUrl()])->values());
    var WATERMARKS = @js($watermarks ?? []);
    var TYPING_URL = @js(route('admin.team-messages.typing'));

    var TICK = '<svg viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6.6l3 3 5.5-6.4"/><path d="M8 9.6l1 1 5.5-6.4"/></svg>';
    var DOTS = '<button type="button" class="tc-dots" aria-label="Message actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button>';
    var FILE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    var DL_SVG = '<svg class="tc-att-dl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    var BASE = STORE, REACT = BASE + '/react', FORWARD = BASE + '/forward';
    var PEER_NAME = @js($active && $active->isGroup() ? '' : ($peer->full_name ?? ''));

    function activeRow(){
        if (CONV){ var r = document.querySelector('.tc-contact[data-conversation="' + CONV + '"]'); if (r) return r; }
        if (PEER_ID) return document.querySelector('.tc-contact[data-peer="' + PEER_ID + '"]');
        return null;
    }
    function senderChip(m){
        if (!IS_GROUP || m.mine || !m.sender) return '';
        var s = m.sender;
        var av = s.avatar
            ? '<span class="tc-avatar xs has-img"><img src="' + s.avatar + '" alt=""></span>'
            : '<span class="tc-avatar xs" style="background:' + s.color + '">' + esc(s.mono) + '</span>';
        return '<div class="tc-sender">' + av + '<span class="tc-sender-name" style="color:' + s.color + '">' + esc(s.name) + '</span></div>';
    }

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
        var row = activeRow();
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
        if (m.system){
            el.className = 'tc-sys'; el.innerHTML = '<span>' + esc(m.body) + '</span>';
            box.appendChild(el); if (m.id > lastId) lastId = m.id;
            return;
        }
        el.className = 'tc-msg' + (m.mine ? ' mine' : '') + (IS_GROUP && !m.mine ? ' tc-msg--grp' : '');
        el.dataset.id = m.id;
        el.dataset.pinned = m.pinned ? 1 : 0;
        el.innerHTML = senderChip(m) + bubbleInner(m);
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
        var row = activeRow();
        if (row){
            var pt = row.querySelector('[data-tick]');
            if (pt && maxMine && maxMine <= upTo) pt.classList.add('read');
        }
    }

    // ---------- Presence / typing / read receipts ----------
    var typingEl = document.getElementById('tcTyping');
    var typingText = document.getElementById('tcTypingText');
    var seenEl = document.getElementById('tcSeen');
    var lastTypingPing = 0;

    function pingTyping(){
        if (!CONV) return;
        var now = Date.now(); if (now - lastTypingPing < 2500) return; lastTypingPing = now;
        var fd = new FormData(); fd.append('_token', csrf); fd.append('conversation_id', CONV);
        fetch(TYPING_URL, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).catch(function () {});
    }
    function updateTyping(list){
        if (!typingEl) return;
        if (!list || !list.length){ typingEl.hidden = true; return; }
        var t;
        if (!IS_GROUP) t = 'typing…';
        else if (list.length === 1) t = list[0] + ' is typing…';
        else if (list.length === 2) t = list[0] + ' and ' + list[1] + ' are typing…';
        else t = 'Several people are typing…';
        var stick = atBottom();
        typingText.textContent = t; typingEl.hidden = false;
        if (stick) toBottom();
    }
    function setDot(peerId, online){
        var d = document.querySelector('.tc-dot[data-dot="' + peerId + '"]'); if (d) d.classList.toggle('on', !!online);
    }
    function updateHeaderPresence(presence){
        if (!presence) return;
        if (IS_GROUP){
            var n = presence.filter(function (p) { return p.online; }).length;
            var el = document.getElementById('tcOnlineCount'); if (el) el.textContent = n > 0 ? ' · ' + n + ' online' : '';
        } else if (PEER_ID){
            var p = presence.filter(function (x) { return String(x.id) === String(PEER_ID); })[0];
            if (p){
                var dot = document.getElementById('tcHeaderDot'); if (dot) dot.classList.toggle('on', p.online);
                var seen = document.getElementById('tcHeaderSeen'); if (seen){ seen.textContent = p.seen; seen.classList.toggle('online', p.online); }
                setDot(PEER_ID, p.online);
            }
        }
    }
    function updateSeen(){
        if (!seenEl || !IS_GROUP) return;
        var mine = box.querySelectorAll('.tc-msg.mine'); if (!mine.length){ seenEl.hidden = true; return; }
        var mid = parseInt(mine[mine.length - 1].dataset.id, 10) || 0;
        var readers = WATERMARKS.filter(function (w) { return w.upTo >= mid; });
        if (!readers.length){ seenEl.hidden = true; return; }
        var avs = readers.slice(0, 6).map(function (w) {
            return w.avatar ? '<span class="tc-avatar has-img"><img src="' + w.avatar + '"></span>'
                : '<span class="tc-avatar" style="background:' + w.color + '">' + esc(w.mono) + '</span>';
        }).join('');
        seenEl.innerHTML = 'Seen by <span class="tc-seen-avs">' + avs + '</span>' + (readers.length > 6 ? ' +' + (readers.length - 6) : '');
        seenEl.hidden = false;
    }

    toBottom();
    if (input) input.focus(); // ready to type the moment the chat opens
    updateSeen();

    // Auto-grow + Enter to send.
    function grow(){ input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
    input.addEventListener('input', function () { grow(); pingTyping(); });
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
        fd.append('_token', csrf);
        if (CONV) fd.append('conversation_id', CONV); else if (PEER_ID) fd.append('recipient_id', PEER_ID);
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
                // First message in a brand-new DM — adopt the conversation id it created.
                if (!CONV && res.conversation_id) {
                    CONV = String(res.conversation_id); box.dataset.conversation = CONV;
                    var r = activeRow(); if (r) r.dataset.conversation = CONV;
                }
                append(res.message); input.value = ''; grow(); cancelReply(); clearPending(); updateSeen(); toBottom(); input.focus();
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
        if (!CONV) return;   // a brand-new DM with no conversation yet — nothing to poll
        fetch(THREAD + '?c=' + encodeURIComponent(CONV) + '&after=' + lastId + '&_=' + Date.now(),
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
                updateTyping(res.typing);
                if (res.watermarks) { WATERMARKS = res.watermarks; updateSeen(); }
                updateHeaderPresence(res.presence);
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
    var menuMsg = null, replyId = null, forwardId = null, guardUntil = 0, emojiTargetId = null;

    // ---------- Emoji reactions: recent quick-bar + full picker ----------
    var DEFAULT_EMOJI = ['👍','❤️','😂','😮','😢','🙏'];
    var EMOJI_ALL = {
        'Smileys': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','💩','🤡','👻','👽','🤖'],
        'Gestures': ['👋','🤚','✋','🖖','👌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🙏','✍️','💅','🤳','💪','🔥','💯','✔️','➕','✖️','🎉','🎊','⭐','🌟','✨','⚡','💥','💫','💦'],
        'Hearts': ['❤️','🧡','💛','💚','💙','💜','🤎','🖤','🤍','💔','❣️','💕','💞','💓','💗','💖','💘','💝'],
        'Animals & Food': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐷','🐸','🐵','🐔','🐧','🦄','🐝','🦋','🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍓','🫐','🍒','🍑','🥭','🍍','🥥','🍔','🍟','🍕','🌭','🍿','🎂','🍰','🍩','🍪','☕','🍺','🥂'],
        'Objects & Symbols': ['💬','📌','📎','✅','❌','❗','❓','💡','📁','📣','🛠️','🏆','🎯','💼','🚀','⏰','📅','🔒','🔑','💸','💰','📈','📉','✏️','📝','🔔','⚠️','🚫','♻️','✔️','☑️','🆗','🆕','🔝','💤']
    };

    function getRecent(){
        try { var r = JSON.parse(localStorage.getItem('tc-recent-emoji') || '[]'); return (r && r.length) ? r : DEFAULT_EMOJI.slice(); }
        catch (e) { return DEFAULT_EMOJI.slice(); }
    }
    function recordRecent(emoji){
        try {
            var r = getRecent().filter(function (x) { return x !== emoji; });
            r.unshift(emoji); r = r.slice(0, 8);
            localStorage.setItem('tc-recent-emoji', JSON.stringify(r));
        } catch (e) {}
    }
    function renderQuickEmojis(){
        var row = document.getElementById('tcMenuEmoji'); if (!row) return;
        row.innerHTML = getRecent().slice(0, 6).map(function (e) {
            return '<button type="button" data-emoji="' + e + '">' + e + '</button>';
        }).join('') + '<button type="button" class="tc-emoji-more" data-more title="More emojis">＋</button>';
    }

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
        var nameEl = el.querySelector('.tc-sender-name');
        menuMsg = { id: parseInt(el.dataset.id, 10), mine: el.classList.contains('mine'),
                    text: txtEl ? txtEl.textContent : '', author: nameEl ? nameEl.textContent : '' };
        menu.querySelector('[data-act="delete"]').hidden = !menuMsg.mine;
        var pinLbl = menu.querySelector('[data-pin-label]');
        if (pinLbl) pinLbl.textContent = el.dataset.pinned === '1' ? 'Unpin' : 'Pin';
        renderQuickEmojis();
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
        replyBar.querySelector('.tc-reply-author').textContent = menuMsg.mine ? 'You' : (menuMsg.author || PEER_NAME);
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

    function pinMsg(){
        if (!menuMsg) return;
        var el = box.querySelector('.tc-msg[data-id="' + menuMsg.id + '"]');
        var isPinned = el && el.dataset.pinned === '1';
        postJson(BASE + '/pin', { message_id: menuMsg.id, pinned: isPinned ? 0 : 1 })
            .then(function (res) { if (res && res.ok) location.reload(); });
    }

    // Pinned banner: expand/collapse, jump to a pinned message, unpin.
    var pinnedHead = document.getElementById('tcPinnedHead');
    var pinnedDrop = document.getElementById('tcPinnedDrop');
    if (pinnedHead && pinnedDrop){
        pinnedHead.addEventListener('click', function () { pinnedDrop.hidden = !pinnedDrop.hidden; });
        pinnedDrop.addEventListener('click', function (e) {
            var un = e.target.closest('[data-unpin]');
            if (un){ e.stopPropagation(); postJson(BASE + '/pin', { message_id: un.dataset.unpin, pinned: 0 }).then(function (res) { if (res && res.ok) location.reload(); }); return; }
            var go = e.target.closest('[data-goto]');
            if (go){
                var t = box.querySelector('.tc-msg[data-id="' + go.dataset.goto + '"]');
                if (t){ t.scrollIntoView({ behavior:'smooth', block:'center' }); t.classList.add('tc-flash'); setTimeout(function () { t.classList.remove('tc-flash'); }, 1300); }
                pinnedDrop.hidden = true;
            }
        });
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
        TC_PEERS.forEach(function (pr) {
            var av = pr.avatar
                ? '<span class="tc-avatar sm has-img"><img src="' + pr.avatar + '" alt=""></span>'
                : '<span class="tc-avatar sm" style="background:#6366f1">' + esc((pr.name[0] || '?').toUpperCase()) + '</span>';
            var row = document.createElement('button'); row.type = 'button'; row.className = 'tc-fwd-row';
            row.innerHTML = av + '<span>' + esc(pr.name) + '</span>';
            row.addEventListener('click', function () { doForward(pr.id, pr.name); });
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

    // Full emoji picker (built once, moved to body).
    var picker = document.getElementById('tcEmojiPicker');
    if (picker) document.body.appendChild(picker);
    var pickerBuilt = false;
    function buildPicker(){
        if (pickerBuilt || !picker) return; pickerBuilt = true;
        var html = '';
        Object.keys(EMOJI_ALL).forEach(function (cat) {
            html += '<div class="tc-emoji-cat">' + cat + '</div><div class="tc-emoji-grid">'
                + EMOJI_ALL[cat].map(function (e) { return '<button type="button" data-emoji="' + e + '">' + e + '</button>'; }).join('')
                + '</div>';
        });
        picker.innerHTML = html;
    }
    function openPicker(anchor){
        buildPicker(); picker.hidden = false;
        var r = anchor.getBoundingClientRect(), pw = picker.offsetWidth, ph = picker.offsetHeight;
        picker.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pw - 8)) + 'px';
        picker.style.top  = Math.max(8, Math.min(r.bottom + 6, window.innerHeight - ph - 8)) + 'px';
    }
    function closePicker(){ if (picker) picker.hidden = true; emojiTargetId = null; }
    if (picker){
        picker.addEventListener('click', function (e) {
            var b = e.target.closest('[data-emoji]'); if (!b) return;
            if (emojiTargetId){ react(emojiTargetId, b.dataset.emoji); recordRecent(b.dataset.emoji); }
            closePicker();
        });
        document.addEventListener('click', function (e) {
            if (picker.hidden) return;
            if (!picker.contains(e.target) && !e.target.closest('[data-more]')) closePicker();
        });
    }

    if (menu){
        // Quick-bar emoji (recent) + the "＋" that opens the full picker.
        var emojiRow = menu.querySelector('.tc-menu-emoji');
        emojiRow.addEventListener('click', function (e) {
            var more = e.target.closest('[data-more]');
            if (more){ emojiTargetId = menuMsg ? menuMsg.id : null; closeMenu(); openPicker(more); return; }
            var b = e.target.closest('[data-emoji]');
            if (b){ if (menuMsg){ react(menuMsg.id, b.dataset.emoji); recordRecent(b.dataset.emoji); } closeMenu(); }
        });
        menu.addEventListener('click', function (e) {
            var it = e.target.closest('.tc-menu-item'); if (!it) return;
            var act = it.dataset.act;
            if (act === 'reply') startReply();
            else if (act === 'copy') copyMsg();
            else if (act === 'forward') openForward();
            else if (act === 'pin') pinMsg();
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
        if (e.key === 'Escape'){ closeMenu(); if (fwdModal) fwdModal.hidden = true; closeLightbox(); closePicker(); }
    });

    var rc = document.getElementById('tcReplyCancel'); if (rc) rc.addEventListener('click', cancelReply);
    if (fwdModal){
        var fc = document.getElementById('tcFwdClose'); if (fc) fc.addEventListener('click', function () { fwdModal.hidden = true; });
        fwdModal.addEventListener('click', function (e) { if (e.target === fwdModal) fwdModal.hidden = true; });
    }

    // ---------- Group members panel (active group only) ----------
    var membersModal = document.getElementById('tcMembers');
    var membersBtn = document.getElementById('tcMembersBtn');
    if (membersModal) document.body.appendChild(membersModal);
    if (membersModal && membersBtn){
        var GID = membersModal.dataset.group;
        function gpost(path, extra){
            var fd = new FormData(); fd.append('_token', csrf);
            if (extra) extra(fd);
            return fetch(GBASE + '/' + GID + path, { method:'POST', body:fd,
                headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).then(function (r) { return r.json(); });
        }
        membersBtn.addEventListener('click', function () { membersModal.hidden = false; });
        document.getElementById('tcMembersClose').addEventListener('click', function () { membersModal.hidden = true; });
        membersModal.addEventListener('click', function (e) { if (e.target === membersModal) membersModal.hidden = true; });

        membersModal.addEventListener('click', function (e) {
            var rem = e.target.closest('.tc-member-remove'); if (!rem) return;
            if (!window.confirm('Remove this member from the group?')) return;
            gpost('/members/' + rem.dataset.admin, function (fd) { fd.append('_method', 'DELETE'); })
                .then(function (res) { if (res && res.ok) location.reload(); });
        });
        var addBtn = document.getElementById('tcAddMembersBtn');
        if (addBtn) addBtn.addEventListener('click', function () {
            var ids = Array.prototype.map.call(membersModal.querySelectorAll('.tc-member-add-list input:checked'), function (c) { return c.value; });
            if (!ids.length) { toast('Select teammates to add'); return; }
            gpost('/members', function (fd) { ids.forEach(function (i) { fd.append('members[]', i); }); })
                .then(function (res) { if (res && res.ok) location.reload(); });
        });
        var renameBtn = document.getElementById('tcRenameBtn');
        if (renameBtn) renameBtn.addEventListener('click', function () {
            var nm = document.getElementById('tcRenameName').value.trim(); if (!nm) { toast('Name required'); return; }
            gpost('/rename', function (fd) { fd.append('name', nm); }).then(function (res) { if (res && res.ok) location.reload(); });
        });
        document.getElementById('tcLeaveBtn').addEventListener('click', function () {
            if (!window.confirm('Leave this group?')) return;
            gpost('/leave').then(function (res) { if (res && res.ok) location.href = @js(route('admin.team-messages.index')); });
        });
    }
})();

// New-group modal — lives outside the thread scope so it works with no chat open.
(function () {
    var modal = document.getElementById('tcGroupModal');
    var openBtn = document.getElementById('tcNewGroup');
    if (!modal || !openBtn) return;
    document.body.appendChild(modal);
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var STORE_GROUP = @js(route('admin.team-messages.group.store'));
    var toast = window.apexToast || function () {};
    var chosenIcon = '💬';

    openBtn.addEventListener('click', function () { modal.hidden = false; });
    document.getElementById('tcGroupClose').addEventListener('click', function () { modal.hidden = true; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });

    var iconRow = document.getElementById('tcIconRow'), iconPick = document.getElementById('tcIconPick');
    iconRow.addEventListener('click', function (e) {
        var b = e.target.closest('.tc-icon-opt'); if (!b) return;
        chosenIcon = b.dataset.icon; iconPick.textContent = chosenIcon;
        iconRow.querySelectorAll('.tc-icon-opt').forEach(function (x) { x.classList.remove('sel'); });
        b.classList.add('sel');
    });

    document.getElementById('tcGroupCreate').addEventListener('click', function () {
        var name = document.getElementById('tcGroupName').value.trim();
        var members = Array.prototype.map.call(modal.querySelectorAll('.tc-group-members input:checked'), function (c) { return c.value; });
        if (!name) { toast('Name your group'); return; }
        if (!members.length) { toast('Pick at least one teammate'); return; }
        var fd = new FormData(); fd.append('_token', csrf); fd.append('name', name); fd.append('icon', chosenIcon);
        members.forEach(function (m) { fd.append('members[]', m); });
        fetch(STORE_GROUP, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res && res.ok) location.href = '?c=' + res.conversation_id; else toast('Could not create group'); });
    });
})();

// Sidebar organize — search, filters, favorite, mute (works with no chat open).
(function () {
    var search = document.getElementById('tcSearch'); if (!search) return;
    var contacts = document.getElementById('tcContacts');
    var clearBtn = document.getElementById('tcSearchClear');
    var srBox = document.getElementById('tcSearchResults');
    var noRes = document.getElementById('tcNoResults');
    var favList = document.getElementById('tcFavList');
    var chatList = document.getElementById('tcChatList');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var SEARCH_URL = @js(route('admin.team-messages.search'));
    var FAV_URL = @js(route('admin.team-messages.favorite'));
    var MUTE_URL = @js(route('admin.team-messages.mute'));
    var curFilter = 'all', searchTimer, lastQuery = '';

    function esc(s){ return (s||'').replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function rows(){ return contacts.querySelectorAll('.tc-contact'); }

    function applyView(){
        var q = search.value.trim().toLowerCase();
        var searching = q.length > 0;
        clearBtn.hidden = !searching;
        var anyRow = false;
        rows().forEach(function (r) {
            var show = true;
            if (curFilter === 'unread' && r.dataset.unread !== '1') show = false;
            if (curFilter === 'fav' && r.dataset.fav !== '1') show = false;
            if (searching && r.dataset.name.indexOf(q) < 0) show = false;
            r.hidden = !show; if (show) anyRow = true;
        });
        var flat = searching || curFilter !== 'all';
        contacts.querySelectorAll('.tc-section').forEach(function (s) {
            if (flat) { s.hidden = true; return; }
            s.hidden = s.dataset.section === 'fav' ? favList.querySelectorAll('.tc-contact').length === 0 : false;
        });
        if (searching && q.length >= 2) scheduleSearch(search.value.trim());
        else { srBox.hidden = true; srBox.innerHTML = ''; lastQuery = ''; noRes.hidden = anyRow || !searching; }
    }

    function scheduleSearch(q){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            if (q === lastQuery) return; lastQuery = q;
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) { if (search.value.trim() === q) renderResults(res.messages || []); })
                .catch(function () {});
        }, 250);
    }
    function renderResults(msgs){
        if (!msgs.length){ srBox.hidden = true; srBox.innerHTML = ''; }
        else {
            srBox.innerHTML = '<div class="tc-section">Messages</div>' + msgs.map(function (m) {
                return '<a class="tc-sr-item" href="?c=' + m.conversation_id + '"><span class="tc-sr-title">' + esc(m.title)
                    + '</span><span class="tc-sr-snip">' + esc(m.sender) + ': ' + esc(m.snippet) + '</span></a>';
            }).join('');
            srBox.hidden = false;
        }
        var anyRow = Array.prototype.some.call(rows(), function (r) { return !r.hidden; });
        noRes.hidden = anyRow || !srBox.hidden;
    }

    search.addEventListener('input', applyView);
    clearBtn.addEventListener('click', function () { search.value = ''; applyView(); search.focus(); });
    document.querySelectorAll('.tc-filter').forEach(function (f) {
        f.addEventListener('click', function () {
            document.querySelectorAll('.tc-filter').forEach(function (x) { x.classList.remove('active'); });
            f.classList.add('active'); curFilter = f.dataset.filter; applyView();
        });
    });

    function post(url, data){
        var fd = new FormData(); fd.append('_token', csrf);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(url, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).catch(function () {});
    }

    contacts.addEventListener('click', function (e) {
        var favBtn = e.target.closest('[data-fav-toggle]');
        var muteBtn = e.target.closest('[data-mute-toggle]');
        if (!favBtn && !muteBtn) return;
        e.preventDefault(); e.stopPropagation();
        var row = (favBtn || muteBtn).closest('.tc-contact');
        var conv = row.dataset.conversation; if (!conv) return;

        if (favBtn){
            var on = favBtn.classList.toggle('on'); row.dataset.fav = on ? '1' : '0';
            favBtn.title = on ? 'Unfavorite' : 'Favorite';
            post(FAV_URL, { conversation_id: conv, favorite: on ? 1 : 0 });
            (on ? favList : chatList).insertBefore(row, (on ? favList : chatList).firstChild);
            applyView();
        } else {
            var m = muteBtn.classList.toggle('on'); row.dataset.muted = m ? '1' : '0';
            muteBtn.title = m ? 'Unmute' : 'Mute';
            post(MUTE_URL, { conversation_id: conv, muted: m ? 1 : 0 });
            var badge = row.querySelector('[data-badge]'); if (m && badge) badge.remove();
            var sub = row.querySelector('.tc-c-sub');
            var ic = row.querySelector('.tc-mute-ic');
            if (m && !ic && sub){ var s = document.createElement('span'); s.className = 'tc-mute-ic'; s.innerHTML = @js(view('partials.mute-icon')->render()); sub.appendChild(s); }
            else if (!m && ic) ic.remove();
        }
    });
})();

// Global presence poll — keeps every sidebar online dot fresh, even with no chat open.
(function () {
    if (!document.querySelector('.tc-contact')) return;
    var URL = @js(route('admin.team-messages.presence'));
    function tick(){
        fetch(URL + '?_=' + Date.now(), { cache:'no-store', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.presence) return;
                res.presence.forEach(function (p) { var d = document.querySelector('.tc-dot[data-dot="' + p.id + '"]'); if (d) d.classList.toggle('on', !!p.online); });
            }).catch(function () {});
    }
    setInterval(tick, 20000);
})();
</script>
@endpush
@endsection
