@extends(request()->boolean('standalone') ? 'layouts.chat' : ($adminLayout ?? 'layouts.admin-pro'))

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
            <div class="tc-me">
                <span class="tc-me-av">{!! $avatar($me, 'sm') !!}<i class="tc-me-dot"></i></span>
                <div class="tc-me-info">
                    <span class="tc-me-name">{{ $me->full_name }}</span>
                    <span class="tc-me-sub"><i class="tc-me-online"></i>Active</span>
                </div>
                <button type="button" class="tc-newgroup" id="tcNewGroup" title="New group" aria-label="New group">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                </button>
                <a href="{{ route('admin.logout') }}" class="tc-newgroup tc-logout" title="Sign out" aria-label="Sign out">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
            <div class="tc-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="tcSearch" placeholder="Search people & messages" autocomplete="off">
                <button type="button" id="tcSearchClear" hidden aria-label="Clear">&times;</button>
            </div>
            <div class="tc-filters">
                <button type="button" class="tc-filter active" data-filter="all">All</button>
                <button type="button" class="tc-filter" data-filter="unread">Unread</button>
                <button type="button" class="tc-filter" data-filter="groups">Groups</button>
            </div>
        </div>
        <div class="tc-contacts" id="tcContacts">
            <a class="tc-contact tc-self-row {{ $isSelf ? 'active' : '' }}" data-self="1" data-name="message yourself notes you saved"
               @if ($selfConv) data-conversation="{{ $selfConv->id }}" @endif
               href="{{ route('admin.team-messages.index', array_merge(['self' => 1], request()->boolean('standalone') ? ['standalone' => 1] : [])) }}">
                <span class="tc-av tc-self-av">{!! $avatar($me, 'sm') !!}<i class="tc-self-badge">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H7a2 2 0 0 0-2 2v16l7-3 7 3V5a2 2 0 0 0-2-2z"/></svg>
                </i></span>
                <span class="tc-c-body">
                    <span class="tc-c-top"><span class="tc-c-name">Message yourself</span></span>
                    <span class="tc-c-sub"><span class="tc-c-preview">{{ $selfLast ? \Illuminate\Support\Str::limit($selfLast->body ?: '📎 Attachment', 40) : 'Notes, reminders &amp; files' }}</span></span>
                </span>
            </a>
            <div id="tcChatList">
                @forelse (array_merge($favorites, $chats) as $it)
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
                        <div class="tc-th-nameline">
                            <span class="tc-th-name" id="tcGroupName">{{ $active->name }}</span>
                            <button type="button" class="tc-th-edit" id="tcGroupNameEdit" title="Edit group name" aria-label="Edit group name">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            </button>
                        </div>
                        <div class="tc-th-role">{{ $members->count() }} members<span id="tcOnlineCount">{{ $onlineCount > 0 ? ' · '.$onlineCount.' online' : '' }}</span></div>
                    </div>
                    <button type="button" class="tc-th-btn" id="tcMembersBtn" title="Members">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>{{ $members->count() }}</span>
                    </button>
                @else
                    <span class="tc-av">{!! $avatar($peer, 'sm') !!}@unless ($isSelf)<i class="tc-dot {{ $peerOnline ? 'on' : '' }}" id="tcHeaderDot"></i>@endunless</span>
                    <div class="tc-th-info">
                        <div class="tc-th-name">{{ $isSelf ? 'Message yourself' : $peer->full_name }}</div>
                        <div class="tc-th-role tc-presence" id="tcHeaderSeen">{{ $isSelf ? 'Notes · visible only to you' : $peerSeen }}</div>
                    </div>
                @endif
                <nav class="tc-tabs" id="tcTabs" role="tablist">
                    <button type="button" class="tc-tab active" data-tab="chat" role="tab">Chat</button>
                    <button type="button" class="tc-tab" data-tab="files" role="tab">Files</button>
                    <button type="button" class="tc-tab" data-tab="photos" role="tab">Photos</button>
                </nav>
                <div class="tc-th-spacer"></div>
                <div class="tc-hsearch" id="tcHSearch">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="tcHeaderSearch" placeholder="Search messages &amp; files" autocomplete="off">
                    <button type="button" id="tcHeaderSearchClear" hidden aria-label="Clear">&times;</button>
                    <div class="tc-hsearch-results" id="tcHeaderSearchResults" hidden></div>
                </div>
                <button type="button" class="tc-bell {{ $notifyLevel === 'none' ? 'muted' : '' }}" id="tcBell" data-level="{{ $notifyLevel }}" title="Notifications">
                    @if ($notifyLevel === 'none')
                        @include('partials.mute-icon')
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    @endif
                </button>
            </div>

            <div class="tc-bell-pop" id="tcBellPop" hidden>
                <button type="button" class="tc-bell-opt" data-level="all">All messages</button>
                <button type="button" class="tc-bell-opt" data-level="mentions">Only @mentions</button>
                <button type="button" class="tc-bell-opt" data-level="none">Off</button>
            </div>

            <div class="tc-mention-pop" id="tcMentionPop" hidden></div>

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
                 data-group="{{ $isGroup ? 1 : 0 }}" data-last="{{ $messages->last()->id ?? 0 }}"
                 data-first="{{ $messages->first()->id ?? 0 }}" data-more="{{ $hasMoreOlder ? 1 : 0 }}">
                @if ($hasMoreOlder)
                    <div class="tc-load-older" id="tcLoadOlder"><button type="button">Load earlier messages</button></div>
                @endif
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
                        <div class="tc-daysep" data-daykey="{{ $dayKey }}"><span>{{ $dayLabel }}</span></div>
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

            {{-- Files tab (Teams-style table). Shown when the Files header tab is active. --}}
            <div class="tc-panel tc-files-panel" id="tcPanelFiles" hidden>
                <div class="tc-panel-bar">
                    <button type="button" class="tc-upload-btn" id="tcFilesUpload">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V6"/><path d="M5 12l7-7 7 7"/></svg>
                        Upload
                    </button>
                    <div class="tc-sel-bar" id="tcFilesSelBar" hidden>
                        <button type="button" class="tc-sel-dl" id="tcFilesDownload">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download
                        </button>
                        <button type="button" class="tc-sel-clear" id="tcFilesClearSel"><span id="tcFilesSelCount">0</span> selected&nbsp;&times;</button>
                    </div>
                </div>
                <div class="tc-files-scroll" id="tcFilesScroll"><div class="tc-panel-empty">Loading…</div></div>
            </div>

            {{-- Photos tab (Teams-style grid grouped by date). --}}
            <div class="tc-panel tc-photos-panel" id="tcPanelPhotos" hidden>
                <div class="tc-photos-scroll" id="tcPhotosScroll"><div class="tc-panel-empty">Loading…</div></div>
            </div>

            <div class="tc-seen" id="tcSeen" hidden></div>
            <div class="tc-typing" id="tcTyping" hidden>
                <span class="tc-typing-dots"><i></i><i></i><i></i></span>
                <span id="tcTypingText"></span>
            </div>

            <div class="tc-edit-bar" id="tcEditBar" hidden>
                <span class="tc-reply-accent"></span>
                <div class="tc-reply-info"><span class="tc-reply-author">Editing message</span><span class="tc-reply-text">Esc to cancel</span></div>
                <button type="button" class="tc-reply-x" id="tcEditCancel" aria-label="Cancel edit">&times;</button>
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
                <input type="file" id="tcFile" multiple hidden>
                <button type="button" class="tc-attach" id="tcAttach" aria-label="Attach a file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </button>
                <button type="button" class="tc-emoji-btn" id="tcEmojiBtn" aria-label="Emoji">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                </button>
                <textarea name="body" id="tcInput" rows="1" placeholder="{{ $isSelf ? 'Write a note to yourself…' : 'Message ' . ($isGroup ? $active->name : $peer->full_name) . '…' }}" maxlength="5000"></textarea>
                <button type="submit" class="tc-send" aria-label="Send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>

            {{-- Teams-style emoji picker (moved to <body> by JS) --}}
            <div class="tc-emoji-picker" id="tcEmojiPicker" hidden>
                <div class="tc-emoji-head">
                    <div class="tc-emoji-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="tcEmojiSearch" placeholder="Find something fun" autocomplete="off" spellcheck="false">
                    </div>
                </div>
                <div class="tc-emoji-scroll" id="tcEmojiScroll"></div>
                <div class="tc-emoji-tabs" id="tcEmojiTabs"></div>
            </div>

            {{-- Shared files gallery --}}
            <div class="tc-modal" id="tcGallery" hidden>
                <div class="tc-modal-card tc-gallery-card">
                    <div class="tc-modal-head"><span>Shared files</span><button type="button" id="tcGalleryClose" aria-label="Close">&times;</button></div>
                    <div class="tc-gallery-body" id="tcGalleryBody"><div class="tc-gallery-empty">Loading…</div></div>
                </div>
            </div>

            {{-- Image lightbox (moved to <body> by JS) --}}
            <div class="tc-lightbox" id="tcLightbox" hidden>
                <button type="button" class="tc-lb-x" id="tcLbClose" aria-label="Close">&times;</button>
                <button type="button" class="tc-lb-nav tc-lb-prev" id="tcLbPrev" aria-label="Previous" hidden>&#8249;</button>
                <img src="" alt="" id="tcLbImg">
                <button type="button" class="tc-lb-nav tc-lb-next" id="tcLbNext" aria-label="Next" hidden>&#8250;</button>
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
                <button type="button" class="tc-menu-item" data-act="edit" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg> Edit
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

            {{-- Teams-style hover quick-react bar (emojis appear the moment you hover a message) --}}
            <div class="tc-qr" id="tcQuickReact" hidden></div>

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

{{-- Styled confirm dialog --}}
<div class="tc-modal tc-confirm-modal" id="tcConfirmModal" hidden>
    <div class="tc-confirm-card">
        <div class="tc-confirm-msg" id="tcConfirmMsg"></div>
        <div class="tc-confirm-row">
            <button type="button" class="tc-btn-cancel" id="tcConfirmCancel">Cancel</button>
            <button type="button" class="tc-btn-danger" id="tcConfirmOk">Confirm</button>
        </div>
    </div>
</div>

    {{-- Delete choice (WhatsApp-style) --}}
    <div class="tc-modal tc-confirm-modal" id="tcDeleteModal" hidden>
        <div class="tc-confirm-card">
            <div class="tc-confirm-title">Delete message?</div>
            <div class="tc-confirm-actions-v">
                <button type="button" class="tc-del-opt" id="tcDelEveryone" hidden>Delete for everyone</button>
                <button type="button" class="tc-del-opt" id="tcDelMe">Delete for me</button>
                <button type="button" class="tc-del-opt tc-del-cancel" id="tcDelCancel">Cancel</button>
            </div>
        </div>
    </div>
</div>{{-- /.tc-wrap --}}

@push('head')
<style>
    .tc-wrap { display:grid; grid-template-columns:320px 1fr; gap:16px; height:calc(100vh - 150px); min-height:520px; }
    @media (max-width:820px){ .tc-wrap { grid-template-columns:1fr; height:auto; } }

    .tc-list, .tc-thread { background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:18px; overflow:hidden; display:flex; flex-direction:column; }
    .tc-list-head { padding:18px 18px 12px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    /* Identity header — your avatar + name, with a compact New-group action. */
    .tc-me { display:flex; align-items:center; gap:11px; }
    .tc-me-av { position:relative; flex:none; }
    .tc-me-av .tc-avatar { width:42px; height:42px; border-radius:13px; font-size:15px; }
    .tc-me-dot { position:absolute; right:-2px; bottom:-2px; width:12px; height:12px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 2.5px var(--pro-surface,#fff); }
    .tc-me-info { min-width:0; flex:1; display:flex; flex-direction:column; line-height:1.25; }
    .tc-me-name { font-size:15.5px; font-weight:800; color:var(--pro-text,#0f172a); letter-spacing:-.01em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-me-sub { font-size:12px; font-weight:600; color:#94a3b8; display:flex; align-items:center; }
    .tc-me-online { width:7px; height:7px; border-radius:50%; background:#22c55e; margin-right:5px; flex:none; }
    .tc-newgroup { flex:none; display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border:1px solid var(--pro-line,#e6ebf2); cursor:pointer; border-radius:12px; color:#4f46e5; background:var(--pro-surface,#fff); transition:background .12s, border-color .12s, transform .1s; }
    .tc-newgroup:hover { background:var(--pro-soft,#f1f5f9); border-color:#c7d2fe; transform:translateY(-1px); }
    .tc-newgroup:active { transform:translateY(0); }
    .tc-newgroup svg { width:18px; height:18px; }
    .tc-logout { color:#ef4444; text-decoration:none; }
    .tc-logout:hover { background:rgba(239,68,68,.09); border-color:rgba(239,68,68,.4); }
    :root[data-theme="dark"] .tc-logout { color:#f87171; }
    :root[data-theme="dark"] .tc-logout:hover { background:rgba(239,68,68,.16); border-color:rgba(239,68,68,.5); }
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
    .tc-unread { flex:none; min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#16a34a; color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; }
    .tc-empty { padding:26px 14px; text-align:center; color:#94a3b8; font-size:13px; }

    .tc-thread-head { display:flex; align-items:center; gap:12px; padding:15px 20px; border-bottom:1px solid var(--pro-line,#eef2f7); }
    .tc-th-nameline { display:flex; align-items:center; gap:6px; }
    .tc-th-edit { border:0; background:transparent; padding:3px; border-radius:7px; cursor:pointer; color:#94a3b8; opacity:0; transition:opacity .12s, background .12s, color .12s; display:inline-flex; }
    .tc-th-nameline:hover .tc-th-edit, .tc-th-edit:focus-visible { opacity:1; }
    .tc-th-edit:hover { background:var(--pro-soft,#f1f5f9); color:#6366f1; }
    .tc-th-edit svg { width:15px; height:15px; }
    .tc-th-name-input { font:700 15px system-ui,sans-serif; color:var(--pro-ink,#1e293b); border:0; border-bottom:2px solid #6366f1; outline:0; background:transparent; padding:0 2px 2px; min-width:120px; max-width:340px; }
    :root[data-theme="dark"] .tc-th-name-input { color:#e2e8f0; }
    :root[data-theme="dark"] .tc-th-edit:hover { background:#182444; }
    /* ---- Header tabs (Chat / Files / Photos) ---- */
    .tc-th-spacer { flex:1; }
    .tc-tabs { display:flex; align-items:center; gap:2px; margin-left:18px; }
    .tc-tab { position:relative; border:0; background:transparent; padding:6px 12px 10px; font:600 14px system-ui,sans-serif; color:#64748b; cursor:pointer; border-radius:8px 8px 0 0; transition:color .12s, background .12s; }
    .tc-tab:hover { color:var(--pro-ink,#1e293b); background:var(--pro-soft,#f5f7fb); }
    .tc-tab.active { color:#4f46e5; }
    .tc-tab.active::after { content:""; position:absolute; left:12px; right:12px; bottom:0; height:2.5px; border-radius:3px; background:#6366f1; }
    :root[data-theme="dark"] .tc-tab { color:#94a3b8; }
    :root[data-theme="dark"] .tc-tab:hover { color:#e2e8f0; background:#182444; }
    :root[data-theme="dark"] .tc-tab.active { color:#a5b4fc; }
    @media (max-width:560px){ .tc-tabs { margin-left:8px; } .tc-tab { padding:6px 8px 10px; font-size:13px; } }
    /* ---- Files / Photos panels ---- */
    .tc-panel { flex:1; min-height:0; display:flex; flex-direction:column; background:var(--pro-soft,#f7f9fc); }
    .tc-panel[hidden] { display:none !important; }
    /* When a non-chat tab is active, hide the chat surfaces. */
    .tcv-files .tc-messages, .tcv-files .tc-composer, .tcv-files .tc-reply-bar, .tcv-files .tc-edit-bar, .tcv-files .tc-pinned, .tcv-files #tcSeen, .tcv-files #tcTyping,
    .tcv-photos .tc-messages, .tcv-photos .tc-composer, .tcv-photos .tc-reply-bar, .tcv-photos .tc-edit-bar, .tcv-photos .tc-pinned, .tcv-photos #tcSeen, .tcv-photos #tcTyping { display:none !important; }
    .tc-panel-bar { display:flex; align-items:center; gap:12px; padding:14px 22px 8px; }
    .tc-upload-btn { display:inline-flex; align-items:center; gap:8px; border:1px solid var(--pro-line,#e2e8f0); background:var(--pro-surface,#fff); color:var(--pro-ink,#1e293b); font:600 13.5px system-ui,sans-serif; padding:8px 15px; border-radius:9px; cursor:pointer; transition:border-color .12s, box-shadow .12s; }
    .tc-upload-btn:hover { border-color:#6366f1; box-shadow:0 4px 12px -6px rgba(99,102,241,.5); }
    .tc-upload-btn svg { width:16px; height:16px; }
    .tc-sel-bar { display:flex; align-items:center; gap:10px; margin-left:auto; }
    .tc-sel-bar[hidden] { display:none !important; }
    .tc-sel-dl { display:inline-flex; align-items:center; gap:7px; border:0; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; font:600 13px system-ui,sans-serif; padding:8px 14px; border-radius:9px; cursor:pointer; }
    .tc-sel-dl svg { width:15px; height:15px; }
    .tc-sel-clear { border:0; background:transparent; color:#64748b; font:600 13px system-ui,sans-serif; cursor:pointer; padding:6px 8px; border-radius:7px; }
    .tc-sel-clear:hover { background:var(--pro-soft,#eef2f7); }
    .tc-files-scroll { flex:1; overflow-y:auto; padding:4px 12px 18px; }
    .tc-panel-empty { text-align:center; color:#94a3b8; font-size:14px; padding:48px 0; }
    .tc-ftable { width:100%; border-collapse:collapse; }
    .tc-ftable thead th { position:sticky; top:0; z-index:1; background:var(--pro-soft,#f7f9fc); text-align:left; font:700 12px system-ui,sans-serif; color:#94a3b8; padding:8px 12px; border-bottom:1px solid var(--pro-line,#e6ebf2); white-space:nowrap; }
    .tc-ftable th.tc-fsort { cursor:pointer; user-select:none; }
    .tc-ftable th.tc-fsort:hover { color:#475569; }
    .tc-ftable td { padding:9px 12px; border-bottom:1px solid var(--pro-line,#eef2f7); font-size:13.5px; color:var(--pro-ink,#1e293b); vertical-align:middle; }
    .tc-ftable tbody tr { transition:background .1s; }
    .tc-ftable tbody tr:hover { background:var(--pro-surface,#fff); }
    .tc-ftable tbody tr.sel { background:rgba(99,102,241,.07); }
    .tc-fcheck { width:34px; text-align:center; }
    .tc-fcheck input { width:16px; height:16px; accent-color:#6366f1; cursor:pointer; }
    .tc-ficon { width:40px; }
    .tc-ficon span { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:7px; font-size:16px; }
    .tc-fname { display:flex; align-items:center; gap:9px; min-width:0; }
    .tc-fname a { color:var(--pro-ink,#1e293b); text-decoration:none; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:38ch; }
    .tc-fname a:hover { color:#4f46e5; text-decoration:underline; }
    .tc-fmeta { color:#64748b; white-space:nowrap; }
    .tc-fby { display:flex; align-items:center; gap:7px; color:#475569; white-space:nowrap; }
    .tc-fdl { border:0; background:transparent; color:#94a3b8; cursor:pointer; padding:5px; border-radius:7px; opacity:0; }
    .tc-ftable tbody tr:hover .tc-fdl { opacity:1; }
    .tc-fdl:hover { background:var(--pro-soft,#eef2f7); color:#4f46e5; }
    .tc-fdl svg { width:16px; height:16px; }
    @media (max-width:640px){ .tc-fcol-when, .tc-fcol-by { display:none; } .tc-fname a { max-width:22ch; } }
    /* Photos grid */
    .tc-photos-scroll { flex:1; overflow-y:auto; padding:14px 22px 22px; }
    .tc-photos-group { font:700 13px system-ui,sans-serif; color:var(--pro-ink,#334155); margin:14px 2px 10px; }
    .tc-photos-group:first-child { margin-top:2px; }
    .tc-photos-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(148px, 1fr)); gap:10px; margin-bottom:6px; }
    .tc-photo { position:relative; aspect-ratio:1; border-radius:12px; overflow:hidden; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); cursor:pointer; display:block; }
    .tc-photo img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .18s; }
    .tc-photo:hover img { transform:scale(1.05); }
    .tc-photo-cap { position:absolute; left:0; right:0; bottom:0; padding:14px 8px 6px; font:600 11px system-ui,sans-serif; color:#fff; background:linear-gradient(180deg, transparent, rgba(15,23,42,.68)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    :root[data-theme="dark"] .tc-panel { background:#0b1120; }
    :root[data-theme="dark"] .tc-ftable thead th { background:#0b1120; border-bottom-color:#233150; color:#7c8aa5; }
    :root[data-theme="dark"] .tc-ftable td { border-bottom-color:#182335; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-ftable tbody tr:hover { background:#111a2e; }
    :root[data-theme="dark"] .tc-upload-btn { background:#0f1629; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-fname a { color:#e2e8f0; }
    .tc-th-name { font-size:15px; font-weight:800; color:var(--pro-text,#0f172a); }
    .tc-th-role { font-size:12px; color:#94a3b8; }
    .tc-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:var(--pro-soft,#f7f9fc); }
    .tc-msg { display:flex; flex-direction:column; align-items:flex-start; align-self:flex-start; max-width:74%; }
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
    .tc-msg.mine .tc-dots { left:-27px; }
    .tc-msg:not(.mine) .tc-dots { right:-27px; }
    /* These elements set their own display in the class, which would otherwise
       beat the UA [hidden] rule and make them impossible to hide. Force it. */
    .tc-menu[hidden], .tc-menu-item[hidden], .tc-reply-bar[hidden], .tc-modal[hidden],
    .tc-pending[hidden], .tc-progress[hidden], .tc-lightbox[hidden],
    .tc-typing[hidden], .tc-seen[hidden], .tc-emoji-picker[hidden],
    .tc-pinned-drop[hidden], .tc-search-results[hidden], .tc-no-results[hidden],
    .tc-bell-pop[hidden], .tc-mention-pop[hidden], .tc-contact[hidden], .tc-section[hidden], .tc-edit-bar[hidden], .tc-qr[hidden] { display:none !important; }

    /* Phase 6 polish */
    .tc-link { color:#2563eb; text-decoration:underline; word-break:break-all; }
    .tc-msg.mine .tc-link { color:#e0e7ff; }
    .tc-edited { font-size:10px; color:#94a3b8; font-style:italic; }
    .tc-msg.mine .tc-edited { color:rgba(255,255,255,.7); }
    .tc-pin-ic { font-size:10px; }
    .tc-quote[data-goto] { cursor:pointer; }
    .tc-emoji-btn { flex:none; width:42px; height:42px; border:0; border-radius:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#64748b; background:rgba(148,163,184,.14); transition:background .14s, color .14s, transform .1s; }
    .tc-emoji-btn:hover { background:rgba(99,102,241,.14); color:#4f46e5; transform:translateY(-1px); }
    .tc-emoji-btn svg { width:20px; height:20px; }
    .tc-edit-bar { display:flex; align-items:center; gap:10px; padding:9px 16px 0; }

    /* Shared files gallery */
    .tc-gallery-card { max-width:520px; max-height:80vh; }
    .tc-gallery-body { overflow-y:auto; padding:14px 16px; }
    .tc-gallery-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:6px; margin-bottom:14px; }
    .tc-gallery-grid a { display:block; aspect-ratio:1; border-radius:10px; overflow:hidden; background:var(--pro-soft,#f1f5f9); }
    .tc-gallery-grid img { width:100%; height:100%; object-fit:cover; display:block; }
    .tc-gallery-file { display:flex; align-items:center; gap:11px; padding:9px 10px; border-radius:11px; text-decoration:none; color:var(--pro-text,#0f172a); }
    .tc-gallery-file:hover { background:var(--pro-soft,#f5f7fb); }
    .tc-gallery-file .tc-att-ic { flex:none; width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; }
    .tc-gallery-file .tc-att-ic svg { width:18px; height:18px; }
    .tc-gallery-meta { min-width:0; flex:1; }
    .tc-gallery-meta b { display:block; font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-gallery-meta span { font-size:11.5px; color:#94a3b8; }
    .tc-gallery-sub { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; margin:4px 0 8px; }
    .tc-gallery-empty { text-align:center; color:#94a3b8; padding:30px; font-size:14px; }
    :root[data-theme="dark"] .tc-gallery-file:hover { background:#182444; }
    :root[data-theme="dark"] .tc-emoji-btn { background:rgba(148,163,184,.12); }

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

    /* Header search bar (searches messages + file names across all chats) */
    .tc-hsearch { position:relative; width:280px; max-width:34vw; flex:none; }
    .tc-hsearch > svg { position:absolute; left:11px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:#94a3b8; pointer-events:none; }
    .tc-hsearch input { width:100%; height:38px; border:1px solid var(--pro-line,#e6ebf2); border-radius:10px; padding:0 30px 0 33px; font:inherit; font-size:13px; background:var(--pro-soft,#f7f9fc); color:var(--pro-text,#0f172a); outline:none; }
    .tc-hsearch input:focus { border-color:#6366f1; background:var(--pro-surface,#fff); box-shadow:0 0 0 3px rgba(99,102,241,.13); }
    #tcHeaderSearchClear { position:absolute; right:7px; top:50%; transform:translateY(-50%); border:0; background:transparent; color:#94a3b8; font-size:17px; line-height:1; cursor:pointer; padding:2px 4px; }
    .tc-hsearch-results { position:absolute; top:calc(100% + 7px); right:0; width:430px; max-width:82vw; max-height:62vh; overflow-y:auto; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:14px; box-shadow:0 22px 54px -20px rgba(15,23,42,.42); padding:6px; z-index:60; }
    .tc-hsearch-results[hidden] { display:none !important; }
    .tc-hs-group { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; font-weight:700; padding:9px 10px 4px; }
    .tc-hs-item { display:flex; gap:11px; align-items:center; padding:9px 10px; border-radius:10px; text-decoration:none; color:inherit; }
    .tc-hs-item:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-hs-ic { flex:none; width:36px; height:36px; border-radius:9px; display:grid; place-items:center; background:rgba(99,102,241,.12); color:#4f46e5; }
    .tc-hs-ic svg { width:17px; height:17px; }
    .tc-hs-meta { min-width:0; flex:1; }
    .tc-hs-title { display:block; font-size:13px; font-weight:600; color:var(--pro-text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-hs-sub { display:block; font-size:12px; color:#8a93a6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tc-hs-empty { padding:16px 10px; font-size:13px; color:#94a3b8; text-align:center; }
    :root[data-theme="dark"] .tc-hsearch input { background:#0b1120; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-hsearch-results { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-hs-item:hover { background:#182444; }
    :root[data-theme="dark"] .tc-hs-title { color:#e2e8f0; }
    @media (max-width:900px){ .tc-hsearch { width:190px; } }

    /* "Message yourself" row */
    .tc-self-row { border-bottom:1px solid var(--pro-line,#eef2f7); margin-bottom:4px; }
    .tc-self-row .tc-c-name { font-weight:700; }
    .tc-self-av { position:relative; }
    .tc-self-badge { position:absolute; right:-3px; bottom:-3px; width:17px; height:17px; border-radius:50%; background:#4f46e5; border:2px solid var(--pro-surface,#fff); display:grid; place-items:center; color:#fff; }
    .tc-self-badge svg { width:9px; height:9px; }
    :root[data-theme="dark"] .tc-self-badge { border-color:#0f1629; }
    :root[data-theme="dark"] .tc-self-row { border-color:#1e2a44; }

    :root[data-theme="dark"] .tc-search input { background:#0b1120; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-row-actions { background:#141d33; }
    :root[data-theme="dark"] .tc-pinned { background:rgba(250,204,21,.06); }
    :root[data-theme="dark"] .tc-sr-item:hover, :root[data-theme="dark"] .tc-filter:hover { background:#182444; }

    /* Emoji reaction quick bar + full picker */
    .tc-menu-emoji button { position:relative; }
    .tc-emoji-more { font-size:16px !important; color:#6366f1; font-weight:800; }
    /* ---- Teams-style emoji panel ---- */
    .tc-emoji-picker { position:fixed; z-index:1003; width:340px; display:flex; flex-direction:column; overflow:hidden; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:14px; box-shadow:0 24px 60px rgba(15,23,42,.30); }
    .tc-emoji-head { padding:12px 12px 8px; }
    .tc-emoji-search { display:flex; align-items:center; gap:8px; padding:0 4px 8px; border-bottom:2px solid var(--pro-line,#e6ebf2); }
    .tc-emoji-search:focus-within { border-bottom-color:#6366f1; }
    .tc-emoji-search svg { width:17px; height:17px; color:#94a3b8; flex:none; }
    .tc-emoji-search input { flex:1; border:0; outline:0; background:transparent; font:500 14px system-ui,sans-serif; color:var(--pro-ink,#1e293b); }
    .tc-emoji-search input::placeholder { color:#94a3b8; }
    .tc-emoji-scroll { flex:1; max-height:288px; overflow-y:auto; padding:6px 12px 8px; scroll-behavior:smooth; }
    .tc-emoji-cat { font-size:12px; font-weight:700; color:var(--pro-ink,#334155); margin:12px 2px 6px; }
    .tc-emoji-cat:first-child { margin-top:2px; }
    .tc-emoji-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap:1px; }
    .tc-emoji-grid button { border:0; background:transparent; font-size:25px; line-height:1; height:40px; border-radius:9px; cursor:pointer; transition:background .1s, transform .06s; font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif; }
    .tc-emoji-grid button:hover { background:var(--pro-soft,#f1f5f9); transform:scale(1.12); }
    .tc-emoji-empty { text-align:center; color:#94a3b8; font-size:13px; padding:26px 0; }
    .tc-emoji-tabs { display:flex; align-items:center; justify-content:space-between; gap:2px; padding:6px 8px; border-top:1px solid var(--pro-line,#eef2f7); background:var(--pro-soft,#f8fafc); }
    .tc-emoji-tabs button { flex:1; border:0; background:transparent; font-size:19px; line-height:1; height:32px; border-radius:8px; cursor:pointer; opacity:.65; filter:grayscale(.15); transition:background .12s, opacity .12s; font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif; }
    .tc-emoji-tabs button:hover { opacity:1; background:rgba(99,102,241,.10); }
    .tc-emoji-tabs button.active { opacity:1; background:rgba(99,102,241,.14); box-shadow:inset 0 -2px 0 #6366f1; }
    :root[data-theme="dark"] .tc-emoji-picker { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-emoji-search { border-bottom-color:#233150; }
    :root[data-theme="dark"] .tc-emoji-search input { color:#e2e8f0; }
    :root[data-theme="dark"] .tc-emoji-cat { color:#cbd5e1; }
    :root[data-theme="dark"] .tc-emoji-grid button:hover { background:#182444; }
    :root[data-theme="dark"] .tc-emoji-tabs { background:#0b1120; border-top-color:#233150; }
    :root[data-theme="dark"] .tc-emoji-tabs button:hover { background:rgba(99,102,241,.18); }

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
    .tc-msg.mine .tc-bubble.deleted .tc-text { color:#64748b; }
    .tc-deleted-text { color:#64748b !important; font-style:italic; display:flex; align-items:center; gap:6px; }
    :root[data-theme="dark"] .tc-deleted-text { color:#94a3b8 !important; }

    /* Reaction pills */
    .tc-reacts { display:flex; flex-wrap:wrap; gap:5px; margin-top:-2px; padding:0 4px; }
    .tc-reacts:empty { display:none; }
    .tc-react { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); font-weight:700; color:#475569; cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.08); transition:transform .1s; }
    .tc-react:hover { transform:scale(1.06); }
    .tc-react-e { font-size:18px; line-height:1; }
    .tc-react-n { font-size:12.5px; font-weight:700; }
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

    /* Teams-style hover quick-react bar */
    .tc-qr { position:fixed; z-index:1001; display:flex; align-items:center; gap:1px; padding:4px 6px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:999px; box-shadow:0 10px 30px -8px rgba(15,23,42,.35); }
    .tc-qr button { border:0; background:transparent; font-size:22px; line-height:1; padding:3px 4px; border-radius:50%; cursor:pointer; transition:transform .1s, background .12s; }
    .tc-qr button:hover { transform:scale(1.25); background:var(--pro-soft,#f1f5f9); }
    .tc-qr .tc-qr-more, .tc-qr .tc-qr-dots { font-size:17px; color:#64748b; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; }
    .tc-qr .tc-qr-sep { width:1px; align-self:stretch; margin:3px 3px; background:var(--pro-line,#e6ebf2); }
    :root[data-theme="dark"] .tc-qr { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-qr button:hover { background:#182444; }
    :root[data-theme="dark"] .tc-qr .tc-qr-sep { background:#233150; }

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
        background:var(--pro-surface,#fff);
        box-shadow:0 20px 48px -28px rgba(30,41,59,.32), 0 2px 10px rgba(30,41,59,.05);
    }

    .tc-list-head { padding:20px 20px 14px; border-bottom:1px solid rgba(148,163,184,.14); }
    :root[data-theme="dark"] .tc-newgroup { background:#0f1629; border-color:#233150; color:#a5b4fc; }
    :root[data-theme="dark"] .tc-newgroup:hover { background:#182444; border-color:#334568; }

    .tc-contacts { padding:10px; }
    .tc-contact { position:relative; border-radius:15px; transition:background .16s, transform .14s, box-shadow .16s; }
    .tc-contact:hover { transform:translateX(2px); background:rgba(99,102,241,.06); }
    .tc-contact.active { background:linear-gradient(90deg, rgba(99,102,241,.16), rgba(124,58,237,.05)); box-shadow:inset 0 0 0 1px rgba(99,102,241,.20); }
    .tc-contact.active::before { content:''; position:absolute; left:2px; top:13px; bottom:13px; width:3px; border-radius:3px; background:linear-gradient(#6366f1,#7c3aed); }
    .tc-avatar { border-radius:14px; box-shadow:0 6px 14px -4px rgba(30,41,59,.35); }
    .tc-unread { background:linear-gradient(135deg,#22c55e,#16a34a); box-shadow:0 5px 12px -3px rgba(34,197,94,.55); }

    .tc-thread-head { padding:16px 22px; border-bottom:1px solid rgba(148,163,184,.14); background:linear-gradient(180deg, rgba(255,255,255,.7), rgba(255,255,255,.35)); }
    .tc-thread-head .tc-avatar { box-shadow:0 0 0 2px #fff, 0 0 0 4px rgba(99,102,241,.4), 0 8px 18px -6px rgba(99,102,241,.5); }
    .tc-th-name { font-size:16px; font-weight:800; letter-spacing:-.01em; }

    /* Plain, clean thread surface — no aurora gradients, no dot grid. */
    .tc-thread { position:relative; }
    .tc-thread::before, .tc-thread::after { content:none; }
    .tc-thread-head, .tc-messages, .tc-reply-bar, .tc-composer { position:relative; z-index:1; }
    .tc-messages { padding:22px 22px 26px; background:var(--pro-soft,#f7f9fc); overflow-x:hidden; }
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
    .tc-daysep span { font-size:11px; font-weight:700; color:#64748b; padding:5px 14px; border-radius:999px; background:rgba(255,255,255,.78); border:1px solid rgba(148,163,184,.2); box-shadow:0 3px 12px -5px rgba(30,41,59,.28); }

    .tc-load-older { align-self:center; z-index:1; margin:6px 0 2px; }
    .tc-load-older button { font-size:12px; font-weight:600; color:#475569; padding:6px 16px; border-radius:999px; background:rgba(255,255,255,.85); border:1px solid rgba(148,163,184,.28); box-shadow:0 3px 12px -6px rgba(30,41,59,.3); cursor:pointer; transition:background .12s, color .12s; }
    .tc-load-older button:hover { background:#fff; color:#1e293b; }
    .tc-load-older.loading button { opacity:.55; pointer-events:none; }
    .tc-load-older.loading button::after { content:'…'; }

    .tc-react { border-radius:999px; background:rgba(255,255,255,.97); box-shadow:0 4px 10px -4px rgba(30,41,59,.3); }

    .tc-thread-empty { z-index:1; margin:auto; display:flex; flex-direction:column; align-items:center; gap:10px; color:#94a3b8; }
    .tc-thread-empty-emoji { font-size:46px; transform-origin:70% 70%; animation:tcWave 2.6s ease-in-out 2; }
    .tc-thread-empty p { font-size:14px; font-weight:600; }

    .tc-composer { padding:16px 18px; border-top:1px solid rgba(148,163,184,.14); background:linear-gradient(180deg, rgba(255,255,255,.45), rgba(255,255,255,.85)); }
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
    .tc-att-img { position:relative; display:block; max-width:260px; border-radius:12px; overflow:hidden; line-height:0; cursor:zoom-in; }
    .tc-att-img img { width:100%; max-height:320px; object-fit:cover; display:block; }

    /* Multiple images render as a compact WhatsApp-style grid, not huge stacked images. */
    .tc-att-grid { display:grid; gap:3px; width:300px; max-width:100%; border-radius:12px; overflow:hidden; }
    .tc-att-grid--1 { grid-template-columns:1fr; width:auto; }
    .tc-att-grid--2, .tc-att-grid--3, .tc-att-grid--4 { grid-template-columns:1fr 1fr; }
    .tc-att-grid .tc-att-img { max-width:none; border-radius:0; aspect-ratio:1; cursor:zoom-in; }
    .tc-att-grid .tc-att-img img { width:100%; height:100%; max-height:none; object-fit:cover; }
    .tc-att-grid .tc-att-img:nth-child(n+5) { display:none; }   /* extras stay in the DOM for the viewer, hidden in the grid */
    .tc-att-grid--1 .tc-att-img { aspect-ratio:auto; border-radius:12px; max-width:260px; }
    .tc-att-grid--1 .tc-att-img img { height:auto; max-height:320px; }
    .tc-att-grid--3 .tc-att-img:first-child { grid-column:1 / -1; aspect-ratio:2 / 1; }
    .tc-att-more { position:absolute; inset:0; display:grid; place-items:center; background:rgba(6,10,25,.55); color:#fff; font-size:23px; font-weight:700; line-height:1; letter-spacing:-.01em; }
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
    .tc-thread.tc-drag::after { content:'Drop files to attach'; position:absolute; inset:12px; z-index:5; display:flex; align-items:center; justify-content:center; font-weight:800; color:#4f46e5; font-size:16px; border:2.5px dashed rgba(99,102,241,.6); border-radius:20px; background:rgba(99,102,241,.08); }

    /* Lightbox */
    .tc-lightbox { position:fixed; inset:0; z-index:1002; background:rgba(8,11,22,.85); display:flex; align-items:center; justify-content:center; padding:32px; }
    .tc-lightbox img { max-width:92vw; max-height:88vh; border-radius:12px; box-shadow:0 30px 80px rgba(0,0,0,.6); }
    .tc-lb-nav { position:fixed; top:50%; transform:translateY(-50%); width:52px; height:52px; border-radius:50%; border:0; background:rgba(255,255,255,.14); color:#fff; font-size:32px; line-height:1; display:grid; place-items:center; cursor:pointer; transition:background .12s; }
    .tc-lb-nav:hover { background:rgba(255,255,255,.28); }
    .tc-lb-prev { left:22px; } .tc-lb-next { right:22px; }
    .tc-lb-nav[hidden] { display:none !important; }
    .tc-lb-x { position:fixed; top:20px; right:24px; width:44px; height:44px; border:0; border-radius:50%; background:rgba(255,255,255,.12); color:#fff; font-size:26px; line-height:1; cursor:pointer; }
    .tc-lb-x:hover { background:rgba(255,255,255,.25); }

    :root[data-theme="dark"] .tc-att-file { background:rgba(20,29,51,.9); border-color:rgba(51,65,85,.6); color:#e2e8f0; }
    :root[data-theme="dark"] .tc-pending-chip { background:#141d33; border-color:#233150; }
    :root[data-theme="dark"] .tc-attach { background:rgba(148,163,184,.12); }

    /* Groups: sidebar icon, header, sender chips, system messages */
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
    .tc-sys span { display:inline-block; padding:5px 13px; border-radius:999px; font-size:11.5px; font-weight:600; color:#64748b; background:rgba(255,255,255,.7); border:1px solid rgba(148,163,184,.18); }

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

    /* Flex column so the members list scrolls and the Create button stays pinned + visible. */
    .tc-group-form { padding:16px; display:flex; flex-direction:column; flex:1; min-height:0; }
    .tc-group-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex:none; }
    .tc-icon-pick { flex:none; width:46px; height:46px; border-radius:13px; border:0; cursor:pointer; font-size:24px; background:linear-gradient(135deg,#4f46e5,#7c3aed); }
    .tc-icon-row { display:grid; grid-template-columns:repeat(8, 1fr); gap:5px; margin-bottom:14px; flex:none; }
    .tc-icon-opt { width:100%; height:36px; border-radius:10px; border:1.5px solid transparent; background:var(--pro-soft,#f1f5f9); cursor:pointer; font-size:18px; padding:0; }
    .tc-icon-opt.sel { border-color:#6366f1; background:rgba(99,102,241,.12); }
    .tc-group-members-title { flex:none; }
    .tc-group-members { flex:1; min-height:60px; overflow-y:auto; }
    .tc-btn-primary { flex:none; width:100%; margin-top:12px; border:0; cursor:pointer; padding:12px; border-radius:13px; font:inherit; font-size:14px; font-weight:800; color:#fff; background:linear-gradient(135deg,#6366f1,#7c3aed); box-shadow:0 10px 22px -8px rgba(99,102,241,.6); }
    .tc-btn-primary:hover { filter:brightness(1.05); }

    :root[data-theme="dark"] .tc-sys span { background:rgba(20,29,51,.8); color:#94a3b8; border-color:rgba(51,65,85,.6); }
    :root[data-theme="dark"] .tc-th-btn, :root[data-theme="dark"] .tc-inp { background:#141d33; border-color:#233150; color:#e2e8f0; }
    :root[data-theme="dark"] .tc-member-row:hover, :root[data-theme="dark"] .tc-addable:hover { background:#182444; }

    /* Confirm + delete dialogs */
    .tc-confirm-card { width:100%; max-width:340px; background:var(--pro-surface,#fff); border-radius:18px; padding:22px; box-shadow:0 24px 60px rgba(15,23,42,.35); }
    .tc-confirm-title { font-size:16px; font-weight:800; margin-bottom:16px; color:var(--pro-text,#0f172a); }
    .tc-confirm-msg { font-size:14.5px; color:var(--pro-text,#0f172a); margin-bottom:20px; line-height:1.5; }
    .tc-confirm-row { display:flex; gap:10px; justify-content:flex-end; }
    .tc-btn-cancel { border:1px solid var(--pro-line,#e6ebf2); background:transparent; color:#475569; padding:9px 18px; border-radius:11px; font:inherit; font-weight:700; cursor:pointer; }
    .tc-btn-cancel:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-btn-danger { border:0; background:#ef4444; color:#fff; padding:9px 18px; border-radius:11px; font:inherit; font-weight:700; cursor:pointer; }
    .tc-btn-danger:hover { filter:brightness(1.05); }
    .tc-confirm-actions-v { display:flex; flex-direction:column; gap:8px; }
    .tc-del-opt { border:1px solid var(--pro-line,#e6ebf2); background:transparent; padding:12px; border-radius:12px; font:inherit; font-weight:700; font-size:14px; cursor:pointer; color:#ef4444; }
    .tc-del-opt:hover { background:rgba(239,68,68,.07); }
    .tc-del-cancel { color:var(--pro-text,#0f172a); }
    .tc-del-cancel:hover { background:var(--pro-soft,#f1f5f9); }
    :root[data-theme="dark"] .tc-confirm-card { background:#0f1629; }
    :root[data-theme="dark"] .tc-btn-cancel:hover, :root[data-theme="dark"] .tc-del-cancel:hover { background:#182444; }

    /* @mentions */
    .tc-mention { color:#4f46e5; font-weight:700; background:rgba(99,102,241,.13); border-radius:5px; padding:0 3px; }
    .tc-msg.mine .tc-mention { color:#fff; background:rgba(255,255,255,.24); }
    .tc-mentions-me .tc-bubble { border-color:rgba(245,158,11,.55) !important; box-shadow:0 0 0 1.5px rgba(245,158,11,.45), 0 8px 20px -10px rgba(30,41,59,.32) !important; }
    .tc-mention-badge { flex:none; min-width:20px; height:20px; padding:0 5px; border-radius:999px; background:linear-gradient(135deg,#f59e0b,#f97316); color:#fff; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; }

    /* @autocomplete popup */
    .tc-mention-pop { position:fixed; z-index:1004; width:260px; max-height:230px; overflow-y:auto; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:14px; box-shadow:0 18px 44px rgba(15,23,42,.24); padding:6px; }
    .tc-mention-opt { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:10px; cursor:pointer; }
    .tc-mention-opt.active, .tc-mention-opt:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-mention-opt .tc-avatar { width:30px; height:30px; font-size:11px; }
    .tc-mention-opt .tc-me-name { font-size:13.5px; font-weight:700; color:var(--pro-text,#0f172a); }
    .tc-mention-opt .tc-me-ev { width:30px; height:30px; border-radius:9px; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; }

    /* Notify bell */
    .tc-bell { flex:none; width:38px; height:38px; border-radius:11px; border:1px solid rgba(148,163,184,.3); background:var(--pro-surface,#fff); color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .tc-bell:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-bell.muted { color:#ef4444; }
    .tc-bell svg { width:18px; height:18px; }
    .tc-bell-pop { position:fixed; z-index:1004; width:210px; background:var(--pro-surface,#fff); border:1px solid var(--pro-line,#e6ebf2); border-radius:13px; box-shadow:0 18px 44px rgba(15,23,42,.24); padding:6px; }
    .tc-bell-opt { display:flex; align-items:center; justify-content:space-between; width:100%; border:0; background:transparent; padding:9px 11px; border-radius:9px; font:inherit; font-size:13.5px; font-weight:600; color:var(--pro-text,#0f172a); cursor:pointer; text-align:left; }
    .tc-bell-opt:hover { background:var(--pro-soft,#f1f5f9); }
    .tc-bell-opt.sel { color:#4f46e5; }
    .tc-bell-opt.sel::after { content:'✓'; font-weight:800; }

    :root[data-theme="dark"] .tc-mention-pop, :root[data-theme="dark"] .tc-bell-pop, :root[data-theme="dark"] .tc-bell { background:#0f1629; border-color:#233150; }
    :root[data-theme="dark"] .tc-mention-opt:hover, :root[data-theme="dark"] .tc-mention-opt.active, :root[data-theme="dark"] .tc-bell-opt:hover { background:#182444; }

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
    :root[data-theme="dark"] .tc-load-older button { background:rgba(20,29,51,.82); color:#94a3b8; border-color:rgba(51,65,85,.6); }
    :root[data-theme="dark"] .tc-load-older button:hover { background:rgba(30,41,64,.95); color:#e2e8f0; }
    :root[data-theme="dark"] .tc-react { background:rgba(20,29,51,.92); }
    :root[data-theme="dark"] .tc-contact:hover { background:rgba(99,102,241,.12); }
</style>
@endpush

@push('scripts')
<script data-tc>
// Cleanup registry — lets the whole chat script be safely re-run on SPA navigation
// (clears the previous thread's intervals, document listeners, and body-moved modals).
(function () {
    if (window.__tcReg) window.__tcReg.cleanup();
    var reg = { intervals: [], nodes: [], docs: [], wins: [] };
    window.__tcReg = reg;
    window.TCI = function (fn, ms) { var id = setInterval(fn, ms); reg.intervals.push(id); return id; };
    window.TCB = function (node) { document.body.appendChild(node); reg.nodes.push(node); return node; };
    window.TCD = function (t, fn, o) { document.addEventListener(t, fn, o); reg.docs.push([t, fn, o]); };
    // Window listeners must be tracked too, or SPA navigation LEAKS them: an old listener
    // keeps the previous conversation's CONV in its closure and would mark that chat read
    // (via poll->thread) when a new message arrives — the "auto-read without opening" bug.
    window.TCW = function (t, fn, o) { window.addEventListener(t, fn, o); reg.wins.push([t, fn, o]); };
    reg.cleanup = function () {
        reg.intervals.forEach(clearInterval);
        reg.docs.forEach(function (d) { document.removeEventListener(d[0], d[1], d[2]); });
        reg.wins.forEach(function (w) { window.removeEventListener(w[0], w[1], w[2]); });
        reg.nodes.forEach(function (n) { if (n && n.parentNode) n.parentNode.removeChild(n); });
    };
})();

// ---------- Sidebar live-updates (ALWAYS runs, even with NO conversation open) ----------
// The thread IIFE below bails when there's no open chat (#tcMessages absent), so the sidebar's
// live preview/unread/reorder wiring must live here — driven by the app-wide poller
// (partials/team-realtime) via window events. This is what keeps the chat list in sync when
// you're sitting on Team Chat with nothing selected.
(function () {
    var activeRow = document.querySelector('.tc-contact.active');
    var CONV = (activeRow && activeRow.dataset.conversation) || '';

    // Tell the poller which conversation is open+focused (so it never notifies the chat you're
    // reading, and marks it read).
    function announceActive(){
        if (!window.ApexRealtime || !CONV) return;
        var looking = !document.hidden && document.hasFocus();
        window.ApexRealtime.setActive(CONV, looking);
        if (looking) window.ApexRealtime.markConversationRead(CONV);
    }
    announceActive();
    TCD('visibilitychange', announceActive);
    TCW('focus', announceActive);
    TCW('blur', announceActive);   // switched to another app → let taskbar notifications through

    // If a row is still a "Tap to message" placeholder (a DM with no conversation yet), give it a
    // real preview element so the first-ever message can render like any other.
    function ensurePreviewEl(row){
        var pv = row.querySelector('[data-preview-text]');
        if (pv) return pv;
        var sub = row.querySelector('.tc-c-sub'); if (!sub) return null;
        var muted = sub.querySelector('.tc-c-muted'); if (muted) muted.remove();
        var wrap = document.createElement('span'); wrap.className = 'tc-c-preview'; wrap.setAttribute('data-preview', '');
        pv = document.createElement('span'); pv.setAttribute('data-preview-text', '');
        wrap.appendChild(pv); sub.insertBefore(wrap, sub.firstChild);
        return pv;
    }

    // Update a conversation's row preview/time/mention and float it to the top of its list.
    // The numeric UNREAD count comes authoritatively from applyRowUnread(), not from here.
    function bumpSidebar(m){
        var row = document.querySelector('.tc-contact[data-conversation="' + m.conversation_id + '"]');
        // First-ever DM: the row exists only as a peer placeholder (data-peer, no data-conversation).
        // Adopt it — stamp the new conversation id so applyRowUnread + future updates find it.
        if (!row && m.sender_id){
            row = document.querySelector('.tc-contact[data-peer="' + m.sender_id + '"]:not([data-conversation])');
            if (row) row.dataset.conversation = String(m.conversation_id);
        }
        if (!row) return;   // truly not in this sidebar (e.g. a brand-new group) — next full load shows it
        var isOpen = String(m.conversation_id) === String(CONV);
        var pv = ensurePreviewEl(row);
        // Match the server format: groups show "Name: text", DMs show just the text.
        if (pv) pv.textContent = (row.dataset.group === '1' ? m.sender + ': ' : '') + m.snippet;
        var tick = row.querySelector('[data-tick]'); if (tick) tick.remove();   // incoming message → no "sent" tick
        var tm = row.querySelector('[data-time]'); if (tm) tm.textContent = 'now';
        if (!isOpen && m.mention && row.dataset.muted !== '1' && !row.querySelector('.tc-mention-badge')){
            var mb = document.createElement('span'); mb.className = 'tc-mention-badge'; mb.title = 'You were mentioned'; mb.textContent = '@';
            var sub2 = row.querySelector('.tc-c-sub'); if (sub2) sub2.insertBefore(mb, row.querySelector('[data-badge]') || null);
        }
        var listParent = row.parentNode; if (listParent && listParent.firstChild !== row) listParent.insertBefore(row, listParent.firstChild);
        window.dispatchEvent(new CustomEvent('apex:sidebar-changed'));   // keep filter/search view consistent
    }

    // Authoritative per-conversation unread — sets EACH row to exactly its own count
    // (the open conversation is always 0). Fired every poll, so counts also drop on read.
    function applyRowUnread(map){
        map = map || {};
        document.querySelectorAll('.tc-contact[data-conversation]').forEach(function (row) {
            var id = row.dataset.conversation;
            var isOpen = String(id) === String(CONV);
            var n = isOpen ? 0 : (parseInt(map[id], 10) || 0);
            var badge = row.querySelector('[data-badge]');
            var sub = row.querySelector('.tc-c-sub');
            if (n > 0){
                if (!badge){ badge = document.createElement('span'); badge.className = 'tc-unread'; badge.setAttribute('data-badge', ''); if (sub) sub.appendChild(badge); }
                badge.textContent = n > 99 ? '99+' : n;
                row.dataset.unread = '1';
                row.querySelectorAll('.tc-c-preview, .tc-c-time').forEach(function (el) { el.classList.add('unread'); });
            } else {
                if (badge) badge.remove();
                row.dataset.unread = '0';
                row.querySelectorAll('.tc-c-preview, .tc-c-time').forEach(function (el) { el.classList.remove('unread'); });
                if (isOpen){ var mb = row.querySelector('.tc-mention-badge'); if (mb) mb.remove(); }
            }
        });
        window.dispatchEvent(new CustomEvent('apex:sidebar-changed'));   // re-apply active filter/search
    }

    TCW('apex:team-unread', function (e) { applyRowUnread(e.detail || {}); });
    TCW('apex:team-message', function (e) {
        var m = e.detail; if (!m) return;
        if (CONV && String(m.conversation_id) === String(CONV)){
            window.dispatchEvent(new CustomEvent('apex:thread-poll'));   // the open thread fetches the full message
            if (!document.hidden && window.ApexRealtime) window.ApexRealtime.markConversationRead(CONV);
        } else {
            bumpSidebar(m);
        }
    });

    // The thread just created a conversation (first message in a new DM). Adopt its id here too,
    // so replies are recognised as the OPEN chat (no self-notifications) and it's marked active.
    TCW('apex:conv-adopted', function (e) {
        if (!e.detail || !e.detail.conv) return;
        CONV = String(e.detail.conv);
        var row = (e.detail.peer && document.querySelector('.tc-contact[data-peer="' + e.detail.peer + '"]'))
            || document.querySelector('.tc-contact.active');
        if (row) row.dataset.conversation = CONV;
        announceActive();
    });
})();

(function () {
    var STANDALONE = @js(request()->boolean('standalone'));
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
    var OLDER_URL = @js(route('admin.team-messages.older'));
    var MENTIONABLES = @js($mentionables ?? []);

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
            var e = esc(r.emoji);   // never trust the stored reaction — escape before inserting as HTML
            return '<span class="tc-react' + (r.mine ? ' mine' : '') + '" data-emoji="' + e + '">'
                + '<span class="tc-react-e">' + e + '</span>'
                + (r.count > 1 ? '<span class="tc-react-n">' + r.count + '</span>' : '') + '</span>';
        }).join('');
    }

    function deletedLabel(by){
        return by === 'You' ? 'You deleted this message' : 'This message was deleted by ' + esc(by || 'Someone');
    }

    function highlightBody(body, labels){
        var h = esc(body);
        h = h.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" class="tc-link">$1</a>');
        if (labels && labels.length){
            labels.slice().sort(function (a, b) { return b.length - a.length; }).forEach(function (l) {
                var tok = '@' + esc(l);
                h = h.split(tok).join('<span class="tc-mention">' + tok + '</span>');
            });
        }
        return h;
    }

    function attsHtml(list){
        if (!list || !list.length) return '';
        var imgs = list.filter(function (a) { return a.image; });
        var files = list.filter(function (a) { return !a.image; });
        var html = '';
        if (imgs.length){
            var extra = imgs.length - 4;
            html += '<div class="tc-att-grid tc-att-grid--' + Math.min(imgs.length, 4) + '">'
                + imgs.map(function (a, i) {
                    var more = (extra > 0 && i === 3) ? '<span class="tc-att-more">+' + extra + '</span>' : '';
                    return '<a class="tc-att-img" href="' + a.url + '" data-lightbox><img src="' + a.url + '" alt="' + esc(a.name) + '" loading="lazy">' + more + '</a>';
                }).join('') + '</div>';
        }
        html += files.map(function (a) {
            return '<a class="tc-att-file" href="' + a.download + '">'
                + '<span class="tc-att-ic">' + FILE_SVG + '</span>'
                + '<span class="tc-att-meta"><span class="tc-att-name">' + esc(a.name) + '</span><span class="tc-att-size">' + esc(a.size) + '</span></span>'
                + DL_SVG + '</a>';
        }).join('');
        return '<div class="tc-atts">' + html + '</div>';
    }

    // Full inner HTML of a message row — kept in step with partials/team-message.blade.php.
    function bubbleInner(m){
        var atts = (!m.deleted && m.attachments && m.attachments.length) ? m.attachments : [];
        var onlyMedia = atts.length && !m.body;
        var h = '<div class="tc-bubble' + (m.deleted ? ' deleted' : '') + (onlyMedia ? ' tc-bubble--media' : '') + '">';
        if (m.reply && !m.deleted) h += '<div class="tc-quote" data-goto="' + (m.reply.id || '') + '"><span class="tc-quote-author">' + esc(m.reply.author)
            + '</span><span class="tc-quote-text">' + esc(m.reply.text) + '</span></div>';
        if (m.forwarded && !m.deleted) h += '<div class="tc-fwd">↪ Forwarded</div>';
        h += attsHtml(atts);
        if (m.deleted) h += '<div class="tc-text tc-deleted-text">🚫 ' + deletedLabel(m.deletedBy) + '</div>';
        else if (m.body) h += '<div class="tc-text">' + highlightBody(m.body, m.mentionLabels) + '</div>';
        h += '</div>';
        var tick = (m.mine && !m.deleted) ? '<span class="tc-btick">' + TICK + '</span>' : '';
        var pin = (m.pinned && !m.deleted) ? '<span class="tc-pin-ic" title="Pinned">📌</span>' : '';
        var edited = (m.edited && !m.deleted) ? '<span class="tc-edited">(edited)</span>' : '';
        h += '<div class="tc-time">' + pin + esc(m.at) + edited + tick + '</div>';
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

    // Build a single message row element (system or normal) — shared by append (newest) and
    // prependOlder (pagination), so both render identically to the initial Blade paint.
    function buildRow(m){
        var el = document.createElement('div');
        if (m.system){
            el.className = 'tc-sys'; el.dataset.id = m.id; el.innerHTML = '<span>' + esc(m.body) + '</span>';
            return el;
        }
        el.className = 'tc-msg' + (m.mine ? ' mine' : '') + (IS_GROUP && !m.mine ? ' tc-msg--grp' : '') + (m.mentionsMe ? ' tc-mentions-me' : '');
        el.dataset.id = m.id;
        el.dataset.pinned = m.pinned ? 1 : 0;
        el.innerHTML = senderChip(m) + bubbleInner(m);
        return el;
    }

    function append(m){
        // Dedupe: a message can arrive from both the 3s poll and a realtime-forced poll.
        if (m.id && box.querySelector('[data-id="' + m.id + '"]')){ if (m.id > lastId) lastId = m.id; return; }
        var empty = box.querySelector('.tc-thread-empty'); if (empty) empty.remove();
        var el = buildRow(m);
        box.appendChild(el);
        if (m.id > lastId) lastId = m.id;
        if (m.system) return;
        // A lazy-loaded image resolves its height AFTER we scroll — re-stick to the bottom when it
        // loads, but only if the reader was already at the bottom.
        el.querySelectorAll('.tc-att-img img').forEach(function (img) {
            var wasBottom = atBottom();
            img.addEventListener('load', function () { if (wasBottom) toBottom(); }, { once: true });
        });
        updatePreview(m);
    }

    // ---------- Load-earlier pagination ----------
    var firstId  = parseInt(box.dataset.first, 10) || 0;
    var hasOlder = box.dataset.more === '1';
    var loadingOlder = false;
    var olderPill = document.getElementById('tcLoadOlder');

    function makeDaysep(key, label){
        var sep = document.createElement('div');
        sep.className = 'tc-daysep'; sep.setAttribute('data-daykey', key);
        sep.innerHTML = '<span>' + esc(label) + '</span>';
        return sep;
    }

    // Prepend a chronological batch of older messages above the current thread, inserting day
    // separators as the day changes and preserving the reader's scroll position. `afterInsert`
    // runs inside the scroll-preserving window (so e.g. removing the pill doesn't cause a jump).
    function prependOlder(list, afterInsert){
        if (!list || !list.length){ if (afterInsert) afterInsert(); return; }
        var anchor = olderPill ? olderPill.nextSibling : box.firstChild;   // insert batch before this
        var existingSep = box.querySelector('.tc-daysep');
        var existingKey = existingSep ? existingSep.getAttribute('data-daykey') : null;
        // A stable reference element to pin the scroll to (the thread's current first message).
        var pin = box.querySelector('.tc-msg, .tc-sys');
        var pinBefore = pin ? pin.offsetTop : 0;

        var frag = document.createDocumentFragment();
        var prevDay = null, newFirst = firstId;
        list.forEach(function (m) {
            if (m.dayKey && m.dayKey !== prevDay){ frag.appendChild(makeDaysep(m.dayKey, m.day)); prevDay = m.dayKey; }
            frag.appendChild(buildRow(m));
            var id = parseInt(m.id, 10) || 0; if (id && (!newFirst || id < newFirst)) newFirst = id;
        });
        // Boundary dedupe: if the batch ends on the same day the thread already opened with, that
        // pre-existing separator is now redundant.
        if (existingSep && prevDay && existingKey === prevDay) existingSep.remove();

        box.insertBefore(frag, anchor);
        if (afterInsert) afterInsert();
        // Shift the viewport by exactly how far the pinned message moved — keeps it under the cursor
        // regardless of pill/separator/padding heights.
        if (pin) box.scrollTop += pin.offsetTop - pinBefore;
        firstId = newFirst;
    }

    function loadOlder(){
        if (loadingOlder || !hasOlder || !firstId) return;
        loadingOlder = true;
        if (olderPill) olderPill.classList.add('loading');
        var url = OLDER_URL + '?c=' + encodeURIComponent(CONV || '') + '&before=' + firstId;
        fetch(url, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (res) {
                if (res) prependOlder(res.messages, function () {
                    hasOlder = !!res.hasMore;
                    if (!hasOlder && olderPill){ olderPill.remove(); olderPill = null; }
                });
            })
            .catch(function () {})
            .then(function () { loadingOlder = false; if (olderPill) olderPill.classList.remove('loading'); });
    }

    if (olderPill){
        var btn = olderPill.querySelector('button');
        if (btn) btn.addEventListener('click', loadOlder);
    }
    // Auto-fetch when the reader scrolls near the very top of the thread.
    box.addEventListener('scroll', function () { if (hasOlder && !loadingOlder && box.scrollTop < 120) loadOlder(); });

    // Notification sound for reactions on my own messages.
    var reactAudio = null;
    function reactChime(){
        try {
            if (!reactAudio) { reactAudio = new Audio(@json(asset('sounds/notify.mp3'))); reactAudio.volume = 0.55; }
            reactAudio.currentTime = 0;
            var p = reactAudio.play(); if (p && p.catch) p.catch(function () {});
        } catch (e) {}
    }
    function reactSum(list){ var n = 0; if (list) list.forEach(function (r) { n += (r.count || 1); }); return n; }
    function domReactSum(rr){
        var n = 0;
        rr.querySelectorAll('.tc-react').forEach(function (p) {
            var c = p.querySelector('.tc-react-n'); n += c ? (parseInt(c.textContent, 10) || 1) : 1;
        });
        return n;
    }

    // Live-sync reactions and deletions on messages already on screen.
    function applyStates(states){
        if (!states) return;
        states.forEach(function (s) {
            var el = box.querySelector('.tc-msg[data-id="' + s.id + '"]');
            if (!el) return;
            var rr = el.querySelector('.tc-reacts');
            if (rr){
                var rh = reactsHtml(s.reactions);
                if (rr.innerHTML !== rh){
                    // Someone reacted to one of my messages → play the notification sound.
                    if (el.classList.contains('mine') && reactSum(s.reactions) > domReactSum(rr)) reactChime();
                    rr.innerHTML = rh;
                }
            }
            if (s.deleted){
                var b = el.querySelector('.tc-bubble');
                if (b && !b.classList.contains('deleted')){
                    b.classList.add('deleted');
                    b.innerHTML = '<div class="tc-text tc-deleted-text">🚫 ' + deletedLabel(s.deletedBy) + '</div>';
                    var el0 = box.querySelector('.tc-msg[data-id="' + s.id + '"] .tc-dots'); if (el0) el0.remove();
                }
            } else if (s.edited && s.body != null){
                var txt = el.querySelector('.tc-bubble .tc-text');
                if (txt) txt.innerHTML = highlightBody(s.body, s.mentionLabels);
                var tl = el.querySelector('.tc-time');
                if (tl && !tl.querySelector('.tc-edited')){ var ed = document.createElement('span'); ed.className = 'tc-edited'; ed.textContent = '(edited)'; tl.insertBefore(ed, tl.querySelector('.tc-btick')); }
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
    // Restore a draft stashed during a reconnect, so nothing typed is ever lost.
    try { var _d = sessionStorage.getItem('tc-draft'); if (_d && input) { input.value = _d; sessionStorage.removeItem('tc-draft'); grow(); } } catch (e) {}
    updateSeen();

    // ---------- @mention autocomplete ----------
    var mentionPop = document.getElementById('tcMentionPop');
    if (mentionPop) TCB(mentionPop);
    var pendingMentions = [], mentionState = null, mentionActive = 0, lastMentionOpts = [];

    function mentionOptions(query){
        var q = query.toLowerCase();
        var opts = MENTIONABLES.filter(function (x) { return x.name.toLowerCase().indexOf(q) >= 0; })
            .map(function (x) { return { key: String(x.id), label: x.name, avatar: x.avatar }; });
        if (IS_GROUP && ('everyone'.indexOf(q) === 0)) opts.unshift({ key: 'everyone', label: 'everyone', ev: true });
        return opts.slice(0, 8);
    }
    function currentMention(){
        var pos = input.selectionStart, upto = input.value.slice(0, pos);
        var m = upto.match(/(^|\s)@([^\s@]{0,30})$/);
        return m ? { query: m[2], start: pos - m[2].length - 1 } : null;
    }
    function mentionOnInput(){
        var st = currentMention();
        if (!st){ hideMentions(); return; }
        var opts = mentionOptions(st.query);
        if (!opts.length){ hideMentions(); return; }
        mentionState = st; mentionActive = 0; renderMentions(opts);
    }
    function renderMentions(opts){
        lastMentionOpts = opts;
        mentionPop.innerHTML = opts.map(function (o, i) {
            var av = o.ev ? '<span class="tc-me-ev">@</span>'
                : (o.avatar ? '<span class="tc-avatar has-img"><img src="' + o.avatar + '"></span>'
                            : '<span class="tc-avatar" style="background:#6366f1">' + esc((o.label[0] || '?').toUpperCase()) + '</span>');
            return '<div class="tc-mention-opt' + (i === mentionActive ? ' active' : '') + '" data-i="' + i + '">' + av
                + '<span class="tc-me-name">' + (o.ev ? 'Everyone' : esc(o.label)) + '</span></div>';
        }).join('');
        var r = input.getBoundingClientRect();
        mentionPop.style.left = '0px'; mentionPop.style.top = '0px'; mentionPop.hidden = false;
        var pr = mentionPop.getBoundingClientRect();
        var wantL = r.left, wantT = r.top - pr.height - 6;
        mentionPop.style.left = wantL + 'px'; mentionPop.style.top = wantT + 'px';
        var act = mentionPop.getBoundingClientRect();
        mentionPop.style.left = (2 * wantL - act.left) + 'px';
        mentionPop.style.top = (2 * wantT - act.top) + 'px';
    }
    function hideMentions(){ if (mentionPop) mentionPop.hidden = true; mentionState = null; }
    function pickMention(o){
        if (!mentionState || !o) return;
        var val = input.value, pos = input.selectionStart;
        var before = val.slice(0, mentionState.start), after = val.slice(pos);
        var insert = '@' + o.label + ' ';
        input.value = before + insert + after;
        var caret = (before + insert).length; input.setSelectionRange(caret, caret);
        if (!pendingMentions.some(function (p) { return p.key === o.key; })) pendingMentions.push({ key: o.key, label: o.label });
        hideMentions(); input.focus(); grow();
    }
    if (mentionPop) mentionPop.addEventListener('mousedown', function (e) {
        var it = e.target.closest('.tc-mention-opt'); if (!it) return;
        e.preventDefault(); pickMention(lastMentionOpts[+it.dataset.i]);
    });

    // Auto-grow + Enter to send (with mention-aware keys).
    function grow(){ input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; }
    input.addEventListener('input', function () { grow(); pingTyping(); mentionOnInput(); });
    input.addEventListener('keydown', function (e) {
        if (mentionPop && !mentionPop.hidden && lastMentionOpts.length){
            if (e.key === 'ArrowDown'){ e.preventDefault(); mentionActive = (mentionActive + 1) % lastMentionOpts.length; renderMentions(lastMentionOpts); return; }
            if (e.key === 'ArrowUp'){ e.preventDefault(); mentionActive = (mentionActive - 1 + lastMentionOpts.length) % lastMentionOpts.length; renderMentions(lastMentionOpts); return; }
            if (e.key === 'Enter' || e.key === 'Tab'){ e.preventDefault(); pickMention(lastMentionOpts[mentionActive]); return; }
            if (e.key === 'Escape'){ hideMentions(); return; }
        }
        if (e.key === 'Escape' && editingId){ e.preventDefault(); cancelEdit(); return; }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    // ---------- Per-chat notification setting (bell) ----------
    var bell = document.getElementById('tcBell'), bellPop = document.getElementById('tcBellPop');
    if (bell && bellPop){
        TCB(bellPop);
        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            bellPop.querySelectorAll('.tc-bell-opt').forEach(function (o) { o.classList.toggle('sel', o.dataset.level === bell.dataset.level); });
            var r = bell.getBoundingClientRect();
            bellPop.style.left = '0px'; bellPop.style.top = '0px'; bellPop.hidden = false;
            var pr = bellPop.getBoundingClientRect();
            var wl = Math.min(r.left, window.innerWidth - pr.width - 8), wt = r.bottom + 6;
            bellPop.style.left = wl + 'px'; bellPop.style.top = wt + 'px';
            var a = bellPop.getBoundingClientRect();
            bellPop.style.left = (2 * wl - a.left) + 'px'; bellPop.style.top = (2 * wt - a.top) + 'px';
        });
        bellPop.addEventListener('click', function (e) {
            var o = e.target.closest('.tc-bell-opt'); if (!o) return;
            var lvl = o.dataset.level; bell.dataset.level = lvl; bell.classList.toggle('muted', lvl === 'none'); bellPop.hidden = true;
            if (!CONV) return;
            var fd = new FormData(); fd.append('_token', csrf); fd.append('conversation_id', CONV); fd.append('level', lvl);
            fetch(@js(route('admin.team-messages.notify')), { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).catch(function () {});
        });
        TCD('click', function (e) { if (!bellPop.hidden && !bellPop.contains(e.target) && !bell.contains(e.target)) bellPop.hidden = true; });
    }

    // ---------- Attachments ----------
    var fileInput = document.getElementById('tcFile');
    var attachBtn = document.getElementById('tcAttach');
    var pendingBox = document.getElementById('tcPending');
    var progressBox = document.getElementById('tcProgress');
    var progressBar = progressBox ? progressBox.querySelector('i') : null;
    var IMG_EXT = ['jpg','jpeg','png','gif','webp'];
    var MAX_BYTES = 51200 * 1024;   // 50 MB per file
    var pending = [];

    function extOf(name){ var i = name.lastIndexOf('.'); return i >= 0 ? name.slice(i + 1).toLowerCase() : ''; }

    // A pasted screenshot arrives as a nameless image blob; give it a real filename +
    // extension (from its MIME type) so it passes the same validation as a picked file.
    var MIME_EXT = { 'image/png':'png', 'image/jpeg':'jpg', 'image/jpg':'jpg', 'image/gif':'gif', 'image/webp':'webp' };
    function namedFile(f){
        if (f.name && extOf(f.name)) return f;   // already has a usable name + extension
        var ext = MIME_EXT[f.type] || (f.type && f.type.indexOf('/') >= 0 ? f.type.split('/')[1] : 'png');
        var name = 'pasted-' + Date.now() + '.' + ext;
        try { return new File([f], name, { type: f.type || 'application/octet-stream' }); }
        catch (err) { try { f.name = name; } catch (e2) {} return f; }
    }

    function addFiles(list){
        Array.prototype.slice.call(list || []).forEach(function (f) {
            if (pending.length >= 10) { toast('Up to 10 files per message'); return; }
            if (f.size > MAX_BYTES) { toast(f.name + ': larger than 50 MB'); return; }
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

    // Paste an image or file straight into the composer (Ctrl+V) — screenshots included.
    function handlePaste(e){
        var dt = e.clipboardData || window.clipboardData; if (!dt) return;
        var files = [];
        if (dt.files && dt.files.length) {
            // Files copied from the OS file manager arrive here with real names.
            Array.prototype.forEach.call(dt.files, function (f) { files.push(namedFile(f)); });
        } else if (dt.items && dt.items.length) {
            // A pasted screenshot/image comes through as an item of kind "file", no name.
            Array.prototype.forEach.call(dt.items, function (it) {
                if (it.kind === 'file') { var f = it.getAsFile(); if (f) files.push(namedFile(f)); }
            });
        }
        if (files.length) { e.preventDefault(); addFiles(files); }   // let plain-text paste through untouched
    }
    // Attach to ONE element only. The thread panel catches a composer paste as it bubbles
    // up from the textarea, so a single listener handles it exactly once.
    var pasteTarget = thread || input;
    if (pasteTarget) pasteTarget.addEventListener('paste', handlePaste);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (editingId){ doEdit(); return; }   // editing an existing message, not sending a new one
        var body = input.value.trim();
        if (!body && !pending.length) return;
        var btn = form.querySelector('.tc-send'); btn.disabled = true;

        var fd = new FormData();
        fd.append('_token', csrf);
        if (CONV) fd.append('conversation_id', CONV); else if (PEER_ID) fd.append('recipient_id', PEER_ID);
        if (body) fd.append('body', body);
        if (replyId) fd.append('reply_to_id', replyId);
        pending.forEach(function (it) { fd.append('attachments[]', it.file, it.file.name); });
        pendingMentions.forEach(function (pm) { if (body.indexOf('@' + pm.label) >= 0) fd.append('mentions[]', pm.key); });

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
                    // Tell the always-on sidebar layer so replies count as the OPEN chat (M2).
                    window.dispatchEvent(new CustomEvent('apex:conv-adopted', { detail: { conv: res.conversation_id, peer: PEER_ID } }));
                }
                append(res.message); input.value = ''; grow(); cancelReply(); clearPending(); pendingMentions = []; updateSeen(); toBottom(); input.focus();
                // If the message carried files, refresh an open Files/Photos tab.
                if (res.message && res.message.attachments && res.message.attachments.length && typeof window.tcGalleryDirty === 'function') window.tcGalleryDirty();
            } else if (xhr.status === 419 || xhr.status === 401 || xhr.status === 403 || xhr.status === 302 || xhr.status === 0) {
                reconnect(body);   // session/token went stale (e.g. just after an update) — reconnect smoothly
            } else {
                toast(res && res.message ? res.message : 'Could not send message');
            }
        };
        xhr.onerror = function () { btn.disabled = false; if (progressBox) progressBox.hidden = true; toast('Network error — try again'); };
        // Never leave the composer permanently locked if the connection stalls.
        xhr.timeout = pending.length ? 60000 : 20000;   // allow longer for uploads
        xhr.ontimeout = function () { btn.disabled = false; if (progressBox) progressBox.hidden = true; toast('Send timed out — try again'); };
        xhr.send(fd);
    });

    // Live poll for new incoming messages. cache:'no-store' + a buster stop the
    // browser from serving a stale empty response for the same ?after= URL.
    // Guard poll responses: on an auth failure (session expired → redirect to login HTML) don't
    // silently swallow r.json()'s throw — tell the user once so they can reload/reconnect.
    // Session/token went stale (e.g. right after a deploy) — reconnect smoothly instead of
    // showing an error: keep what was typed and reload, which lands back on the chat (or,
    // if the session is truly gone, the sign-in). No scary "CSRF token mismatch".
    var reconnecting = false;
    function reconnect(draft){
        if (reconnecting) return; reconnecting = true;
        try { if (draft) sessionStorage.setItem('tc-draft', draft); } catch (e) {}
        if (window.apexToast) apexToast('Reconnecting…');
        setTimeout(function () { location.reload(); }, 500);
    }

    var authWarned = false;
    function okJson(r){
        if (r.ok && !r.redirected){ authWarned = false; return r.json(); }
        if (!authWarned && (r.status === 401 || r.status === 419 || r.status === 403 || r.redirected)){
            authWarned = true;
            reconnect();
        }
        return null;
    }
    var statesSince = '';   // round-trips the server's statesToken so edits/reactions/deletes on ANY message sync
    function poll(force){
        if (!force && document.hidden) return;   // background tab: wait for a realtime wake instead
        if (!CONV) return;   // a brand-new DM with no conversation yet — nothing to poll
        fetch(THREAD + '?c=' + encodeURIComponent(CONV) + '&after=' + lastId
                + '&statesSince=' + encodeURIComponent(statesSince) + '&_=' + Date.now(),
            { cache:'no-store', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(okJson)
            .then(function (res) {
                if (!res) return;
                if (res.messages && res.messages.length) {
                    var stick = atBottom();
                    res.messages.forEach(append);
                    if (stick) toBottom();
                }
                applyReadReceipts(res.readUpTo);
                applyStates(res.states);
                if (res.statesToken) statesSince = res.statesToken;
                updateTyping(res.typing);
                if (res.watermarks) { WATERMARKS = res.watermarks; updateSeen(); }
                updateHeaderPresence(res.presence);
            })
            .catch(function () {});
    }
    TCI(poll, 3000);

    // The always-on sidebar block (defined earlier, runs even with no chat open) drives the
    // sidebar + notify wiring. When a message lands in THIS open conversation it asks the
    // thread to fetch the full message(s).
    TCW('apex:thread-poll', function () { poll(true); });

    // ---------- Message actions: menu, react, reply, copy, forward, delete ----------
    var menu     = document.getElementById('tcMenu');
    var fwdModal = document.getElementById('tcForward');
    var replyBar = document.getElementById('tcReply');
    if (menu) TCB(menu);         // detach so position:fixed is exact
    if (fwdModal) TCB(fwdModal);
    var menuMsg = null, replyId = null, forwardId = null, guardUntil = 0, emojiTargetId = null;

    // ---------- Emoji reactions: recent quick-bar + full picker ----------
    var DEFAULT_EMOJI = ['👍','❤️','😂','😮','😢','🙏'];
    // Each category: { key, tab (icon glyph), name, items:[[emoji, 'search keywords'], ...] }
    var EMOJI_CATS = [
        { key:'smileys', tab:'😀', name:'Smileys & people', items:[
            ['😀','grin happy smile'],['😃','happy smile joy'],['😄','happy laugh smile'],['😁','grin beam'],['😆','laugh haha'],['😅','sweat laugh nervous'],['🤣','rofl rolling laugh'],['😂','joy tears laugh cry'],['🙂','slight smile'],['🙃','upside down silly'],['😉','wink'],['😊','blush smile happy'],['😇','angel innocent halo'],['🥰','love hearts adore'],['😍','love heart eyes'],['🤩','star struck wow'],['😘','kiss blow'],['😗','kiss'],['😚','kiss closed'],['😙','kiss smile'],['😋','yum tasty tongue'],['😛','tongue playful'],['😜','wink tongue'],['🤪','zany crazy silly'],['😝','tongue squint'],['🤑','money mouth rich'],['🤗','hug hands'],['🤭','giggle oops hand'],['🤫','shush quiet secret'],['🤔','thinking hmm think'],['🤐','zipper quiet'],['😐','neutral meh'],['😑','expressionless blank'],['😶','no mouth silent'],['😏','smirk'],['😒','unamused annoyed'],['🙄','eye roll'],['😬','grimace awkward'],['🤥','lying pinocchio'],['😌','relieved calm'],['😔','sad pensive'],['😪','sleepy tired'],['🤤','drool'],['😴','sleep zzz'],['😷','mask sick'],['🤒','sick thermometer'],['🤕','hurt bandage injured'],['🤢','sick nausea gross'],['🤮','vomit puke sick'],['🤧','sneeze sick'],['🥵','hot heat sweat'],['🥶','cold freeze'],['🥴','woozy drunk dizzy'],['😵','dizzy dead ko'],['🤯','mind blown shocked'],['🤠','cowboy'],['🥳','party celebrate hat'],['😎','cool sunglasses'],['🤓','nerd geek glasses'],['🧐','monocle inspect'],['😕','confused'],['😟','worried'],['🙁','frown sad'],['☹️','frown sad'],['😮','wow surprised oh'],['😯','hushed surprised'],['😲','astonished shocked'],['😳','flushed embarrassed'],['🥺','pleading puppy eyes beg'],['😦','frown anguish'],['😧','anguished'],['😨','fearful scared'],['😰','anxious sweat scared'],['😥','sad relieved'],['😢','cry sad tear'],['😭','sob cry bawl'],['😱','scream shocked fear'],['😖','confounded'],['😣','persevere struggle'],['😞','disappointed sad'],['😓','sweat sad'],['😩','weary tired'],['😫','tired exhausted'],['🥱','yawn bored'],['😤','huff triumph frustrated'],['😡','angry mad rage red'],['😠','angry mad'],['🤬','cursing swear angry'],['😈','devil imp evil'],['👿','angry devil'],['💀','skull dead'],['💩','poop'],['🤡','clown'],['👻','ghost boo'],['👽','alien'],['🤖','robot bot']] },
        { key:'gestures', tab:'✋', name:'Gestures & body', items:[
            ['👋','wave hi bye hello'],['🤚','raised hand'],['✋','hand stop high five'],['🖖','vulcan spock'],['👌','ok perfect'],['🤏','pinch small'],['✌️','peace victory'],['🤞','fingers crossed luck'],['🤟','love you'],['🤘','rock horns'],['🤙','call me shaka'],['👈','point left'],['👉','point right'],['👆','point up'],['👇','point down'],['☝️','point up index'],['👍','thumbs up like yes good approve'],['👎','thumbs down dislike no bad'],['✊','fist raised power'],['👊','fist bump punch'],['🤛','fist left'],['🤜','fist right'],['👏','clap applause bravo'],['🙌','raise hands celebrate praise'],['👐','open hands'],['🤲','palms up'],['🙏','pray thanks please please high five'],['✍️','write hand'],['💅','nails polish'],['🤳','selfie'],['💪','muscle strong flex'],['🔥','fire lit hot flame'],['💯','hundred perfect score'],['✔️','check tick yes done'],['➕','plus add'],['✖️','cross multiply'],['🎉','party celebrate tada'],['🎊','confetti celebrate'],['⭐','star'],['🌟','glow star sparkle'],['✨','sparkles shiny magic'],['⚡','bolt lightning fast'],['💥','boom explode'],['💫','dizzy star'],['💦','sweat water splash']] },
        { key:'hearts', tab:'❤️', name:'Hearts', items:[
            ['❤️','red heart love'],['🧡','orange heart'],['💛','yellow heart'],['💚','green heart'],['💙','blue heart'],['💜','purple heart'],['🤎','brown heart'],['🖤','black heart'],['🤍','white heart'],['💔','broken heart'],['❣️','heart exclamation'],['💕','two hearts love'],['💞','revolving hearts'],['💓','beating heart'],['💗','growing heart'],['💖','sparkling heart'],['💘','arrow heart cupid'],['💝','heart gift ribbon']] },
        { key:'animals', tab:'🐻', name:'Animals & nature', items:[
            ['🐶','dog puppy'],['🐱','cat kitten'],['🐭','mouse'],['🐹','hamster'],['🐰','rabbit bunny'],['🦊','fox'],['🐻','bear'],['🐼','panda'],['🐨','koala'],['🐯','tiger'],['🦁','lion'],['🐷','pig'],['🐸','frog'],['🐵','monkey'],['🐔','chicken'],['🐧','penguin'],['🦄','unicorn'],['🐝','bee'],['🦋','butterfly'],['🌸','blossom flower'],['🌹','rose flower'],['🌻','sunflower'],['🌈','rainbow'],['☀️','sun sunny'],['🌙','moon night'],['⛄','snowman winter'],['🌊','wave ocean water'],['🌍','earth globe world']] },
        { key:'food', tab:'🍔', name:'Food & drink', items:[
            ['🍏','green apple'],['🍎','apple'],['🍐','pear'],['🍊','orange tangerine'],['🍋','lemon'],['🍌','banana'],['🍉','watermelon'],['🍓','strawberry'],['🫐','blueberry'],['🍒','cherry'],['🍑','peach'],['🥭','mango'],['🍍','pineapple'],['🥥','coconut'],['🍔','burger'],['🍟','fries chips'],['🍕','pizza'],['🌭','hot dog'],['🍿','popcorn'],['🎂','cake birthday'],['🍰','cake slice'],['🍩','donut'],['🍪','cookie'],['🍫','chocolate'],['🍭','lollipop candy'],['☕','coffee tea'],['🍺','beer'],['🍻','beers cheers'],['🥂','champagne cheers toast'],['🍷','wine']] },
        { key:'activity', tab:'⚽', name:'Activity & travel', items:[
            ['⚽','soccer football'],['🏀','basketball'],['🏈','american football'],['⚾','baseball'],['🎾','tennis'],['🏆','trophy win champion'],['🥇','gold medal first'],['🎯','target bullseye dart'],['🎮','game controller'],['🎲','dice'],['🎸','guitar music'],['🎧','headphones music'],['🚀','rocket launch ship fast'],['✈️','plane travel flight'],['🚗','car'],['🏠','house home'],['🏝️','island beach'],['🗽','statue liberty'],['🎡','ferris wheel'],['⛱️','beach umbrella'],['🏖️','beach'],['🗺️','map'],['🧳','luggage travel'],['⛰️','mountain']] },
        { key:'symbols', tab:'🔣', name:'Objects & symbols', items:[
            ['💬','speech chat message'],['💭','thought bubble'],['📌','pin'],['📎','paperclip clip'],['✅','check done tick green'],['❌','cross no wrong'],['❗','exclamation important'],['❓','question'],['💡','idea light bulb'],['📁','folder file'],['📣','megaphone announce'],['🛠️','tools fix'],['🏆','trophy'],['🎯','target'],['💼','briefcase work'],['⏰','alarm clock time'],['🔒','lock secure'],['🔑','key'],['💸','money fly cash'],['💰','money bag'],['📈','chart up growth'],['📉','chart down'],['✏️','pencil edit'],['📝','memo note write'],['🔔','bell notify'],['⚠️','warning caution'],['🚫','no prohibited ban'],['♻️','recycle'],['☑️','checkbox ticked'],['🆗','ok'],['🆕','new'],['🔝','top up'],['💤','sleep zzz'],['🙏','thanks pray']] }
    ];

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
    var lbPrev = document.getElementById('tcLbPrev'), lbNext = document.getElementById('tcLbNext');
    var lbImages = [], lbIndex = 0;
    if (lightbox) TCB(lightbox);
    function closeLightbox(){ if (lightbox) { lightbox.hidden = true; lbImg.src = ''; } }
    function lbShow(){
        if (!lbImages.length) return;
        lbImg.src = lbImages[lbIndex];
        var multi = lbImages.length > 1;
        if (lbPrev) lbPrev.hidden = !multi;
        if (lbNext) lbNext.hidden = !multi;
    }
    function lbNav(d){
        if (lbImages.length < 2) return;
        lbIndex = (lbIndex + d + lbImages.length) % lbImages.length;   // wrap around
        lbShow();
    }
    box.addEventListener('click', function (e) {
        var img = e.target.closest('.tc-att-img'); if (!img) return;
        e.preventDefault();
        // Every image in the thread becomes navigable (including the hidden "+N" ones).
        lbImages = Array.prototype.map.call(box.querySelectorAll('.tc-att-img'), function (a) { return a.getAttribute('href'); });
        lbIndex = Math.max(0, lbImages.indexOf(img.getAttribute('href')));
        lightbox.hidden = false; lbShow();
    });
    if (lightbox){
        document.getElementById('tcLbClose').addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function (e) { if (e.target === lightbox) closeLightbox(); });
        if (lbPrev) lbPrev.addEventListener('click', function (e) { e.stopPropagation(); lbNav(-1); });
        if (lbNext) lbNext.addEventListener('click', function (e) { e.stopPropagation(); lbNav(1); });
    }

    // ---------- Header search: messages + file names across every chat ----------
    (function () {
        var hs = document.getElementById('tcHeaderSearch'); if (!hs) return;
        var hsClear = document.getElementById('tcHeaderSearchClear');
        var hsRes = document.getElementById('tcHeaderSearchResults');
        var HS_URL = @json(route('admin.team-messages.search'));
        var HS_SA = @json(request()->boolean('standalone')) ? '&standalone=1' : '';
        var CHAT_IC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        var FILE_IC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        var hsT, hsLast = '';
        function hsRender(res){
            var m = res.messages || [], f = res.files || [];
            if (!m.length && !f.length){ hsRes.innerHTML = '<div class="tc-hs-empty">No messages or files found.</div>'; hsRes.hidden = false; return; }
            var h = '';
            if (m.length){ h += '<div class="tc-hs-group">Messages</div>' + m.map(function (x) {
                return '<a class="tc-hs-item" href="?c=' + x.conversation_id + HS_SA + '"><span class="tc-hs-ic">' + CHAT_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.title) + '</span><span class="tc-hs-sub">' + esc(x.sender) + ': ' + esc(x.snippet) + '</span></span></a>';
            }).join(''); }
            if (f.length){ h += '<div class="tc-hs-group">Files</div>' + f.map(function (x) {
                return '<a class="tc-hs-item" href="?c=' + x.conversation_id + HS_SA + '"><span class="tc-hs-ic">' + FILE_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.name) + '</span><span class="tc-hs-sub">' + esc(x.title) + ' · ' + esc(x.size) + '</span></span></a>';
            }).join(''); }
            hsRes.innerHTML = h; hsRes.hidden = false;
        }
        function hsRun(){
            var q = hs.value.trim();
            hsClear.hidden = !q;
            if (q.length < 2){ hsRes.hidden = true; hsRes.innerHTML = ''; hsLast = ''; return; }
            clearTimeout(hsT);
            hsT = setTimeout(function () {
                if (q === hsLast) return; hsLast = q;
                fetch(HS_URL + '?q=' + encodeURIComponent(q), { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
                    .then(function (r) { return r.json(); }).then(function (res) { if (hs.value.trim() === q) hsRender(res); }).catch(function () {});
            }, 250);
        }
        hs.addEventListener('input', hsRun);
        hs.addEventListener('focus', function () { if (hs.value.trim().length >= 2) hsRun(); });
        hsClear.addEventListener('click', function () { hs.value = ''; hsRes.hidden = true; hsRes.innerHTML = ''; hsClear.hidden = true; hs.focus(); });
        TCD('click', function (e) { if (!hsRes.hidden && !e.target.closest('.tc-hsearch')) hsRes.hidden = true; });
    })();

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
        var editItem = menu.querySelector('[data-act="edit"]');
        if (editItem) editItem.hidden = !(menuMsg.mine && menuMsg.text && menuMsg.text.trim());
        var pinLbl = menu.querySelector('[data-pin-label]');
        if (pinLbl) pinLbl.textContent = el.dataset.pinned === '1' ? 'Unpin' : 'Pin';
        renderQuickEmojis();
        menu.style.left = '0px'; menu.style.top = '0px';
        menu.hidden = false;
        var mrect = menu.getBoundingClientRect();
        var mw = mrect.width, mh = mrect.height;

        // Desired VIEWPORT position: beside the message's dots (which hug the bubble).
        var trig = el.querySelector('.tc-dots');
        var tr = trig ? trig.getBoundingClientRect() : { left: x, right: x, top: y, bottom: y };
        var mine = el.classList.contains('mine');
        var want = mine ? (tr.left - mw - 4) : (tr.right + 4);
        want = Math.max(8, Math.min(want, window.innerWidth - mw - 8));
        var wantTop = Math.max(8, Math.min(tr.top, window.innerHeight - mh - 8));

        // Two-pass: whatever coordinate offset the ancestor imposes, measure and cancel it.
        menu.style.left = want + 'px'; menu.style.top = wantTop + 'px';
        var act = menu.getBoundingClientRect();
        menu.style.left = (2 * want - act.left) + 'px';
        menu.style.top  = (2 * wantTop - act.top) + 'px';
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

    // ---------- Edit message ----------
    var editingId = null;
    var editBar = document.getElementById('tcEditBar');
    function startEdit(){
        if (!menuMsg) return;
        cancelReply();
        editingId = menuMsg.id;
        var el = box.querySelector('.tc-msg[data-id="' + editingId + '"]');
        var t = el ? el.querySelector('.tc-bubble .tc-text') : null;
        input.value = t ? t.textContent : menuMsg.text;
        if (editBar) editBar.hidden = false;
        grow(); input.focus(); input.setSelectionRange(input.value.length, input.value.length);
    }
    function cancelEdit(){ editingId = null; if (editBar) editBar.hidden = true; input.value = ''; grow(); }
    function doEdit(){
        var body = input.value.trim(); if (!body){ cancelEdit(); return; }
        var id = editingId;
        var fd = new FormData(); fd.append('_token', csrf); fd.append('_method', 'PUT'); fd.append('body', body);
        pendingMentions.forEach(function (pm) { if (body.indexOf('@' + pm.label) >= 0) fd.append('mentions[]', pm.key); });
        fetch(BASE + '/' + id, { method:'POST', cache:'no-store', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok){ toast('Could not edit'); return; }
                var el = box.querySelector('.tc-msg[data-id="' + id + '"]');
                if (el){
                    var t = el.querySelector('.tc-bubble .tc-text');
                    if (t) t.innerHTML = highlightBody(res.message.body, res.message.mentionLabels);
                    var tl = el.querySelector('.tc-time');
                    if (tl && !tl.querySelector('.tc-edited')){ var ed = document.createElement('span'); ed.className = 'tc-edited'; ed.textContent = '(edited)'; tl.insertBefore(ed, tl.querySelector('.tc-btick')); }
                }
                cancelEdit(); pendingMentions = []; input.focus();
            })
            .catch(function () { toast('Could not edit'); });
    }
    var ecBtn = document.getElementById('tcEditCancel'); if (ecBtn) ecBtn.addEventListener('click', cancelEdit);

    // ---------- Shared files gallery ----------
    var galleryBtn = document.getElementById('tcGalleryBtn');
    var galleryModal = document.getElementById('tcGallery');
    if (galleryModal) TCB(galleryModal);
    if (galleryBtn && galleryModal){
        galleryBtn.addEventListener('click', openGallery);
        document.getElementById('tcGalleryClose').addEventListener('click', function () { galleryModal.hidden = true; });
        galleryModal.addEventListener('click', function (e) {
            if (e.target === galleryModal){ galleryModal.hidden = true; return; }
            var a = e.target.closest('a[data-lightbox]'); if (a){ e.preventDefault(); lbImg.src = a.getAttribute('href'); lightbox.hidden = false; }
        });
    }
    function openGallery(){
        if (!CONV){ toast('No shared files yet'); return; }
        galleryModal.hidden = false;
        var gbody = document.getElementById('tcGalleryBody'); gbody.innerHTML = '<div class="tc-gallery-empty">Loading…</div>';
        fetch(BASE + '/gallery?c=' + CONV, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }, cache:'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; }).then(function (res) { if (res) renderGallery(res.files || []); }).catch(function () {});
    }
    function renderGallery(files){
        var gbody = document.getElementById('tcGalleryBody');
        if (!files.length){ gbody.innerHTML = '<div class="tc-gallery-empty">No files shared in this chat yet.</div>'; return; }
        var imgs = files.filter(function (f) { return f.image; }), docs = files.filter(function (f) { return !f.image; });
        var html = '';
        if (imgs.length) html += '<div class="tc-gallery-sub">Images</div><div class="tc-gallery-grid">' + imgs.map(function (f) {
            return '<a href="' + f.url + '" data-lightbox><img src="' + f.url + '" loading="lazy"></a>';
        }).join('') + '</div>';
        if (docs.length) html += '<div class="tc-gallery-sub">Files</div>' + docs.map(function (f) {
            return '<a class="tc-gallery-file" href="' + f.download + '"><span class="tc-att-ic">' + FILE_SVG + '</span><span class="tc-gallery-meta"><b>'
                + esc(f.name) + '</b><span>' + esc(f.size) + ' · ' + esc(f.by) + ' · ' + esc(f.at) + '</span></span></a>';
        }).join('');
        gbody.innerHTML = html;
    }

    // ---------- Header tabs: Chat / Files / Photos ----------
    var thread = document.querySelector('.tc-thread');
    var tabsNav = document.getElementById('tcTabs');
    var filesScroll = document.getElementById('tcFilesScroll');
    var photosScroll = document.getElementById('tcPhotosScroll');
    var galleryData = null, currentTab = 'chat';

    function extIcon(name, isImg){
        if (isImg) return '<span style="background:#eef2ff">🖼️</span>';
        var m = (name || '').toLowerCase().match(/\.([a-z0-9]+)$/), e = m ? m[1] : '';
        var map = { zip:['📦','#fef3c7'], rar:['📦','#fef3c7'], '7z':['📦','#fef3c7'],
            pdf:['📕','#fee2e2'], doc:['📘','#dbeafe'], docx:['📘','#dbeafe'],
            xls:['📗','#dcfce7'], xlsx:['📗','#dcfce7'], csv:['📗','#dcfce7'],
            ppt:['📙','#ffedd5'], pptx:['📙','#ffedd5'], txt:['📄','#f1f5f9'] };
        var v = map[e] || ['📄','#f1f5f9'];
        return '<span style="background:' + v[1] + '">' + v[0] + '</span>';
    }

    // Sequential, popup-safe downloads via a reused hidden iframe.
    var dlFrame = null;
    function downloadUrls(urls){
        if (!urls.length) return;
        if (!dlFrame){ dlFrame = document.createElement('iframe'); dlFrame.style.display = 'none'; TCB(dlFrame); }
        var i = 0;
        (function next(){ if (i >= urls.length) return; dlFrame.src = urls[i++]; setTimeout(next, 500); })();
    }

    var fSort = { key: 'ts', dir: -1 };
    var fSelected = {};   // url -> true
    function selCount(){ return Object.keys(fSelected).length; }
    function updateSelBar(){
        var bar = document.getElementById('tcFilesSelBar'), n = selCount();
        if (bar){ bar.hidden = n === 0; var c = document.getElementById('tcFilesSelCount'); if (c) c.textContent = n; }
    }
    function renderFiles(){
        if (!filesScroll) return;
        var files = (galleryData || []).slice();
        if (!files.length){ filesScroll.innerHTML = '<div class="tc-panel-empty">No files shared in this chat yet.<br>Use <b>Upload</b> to share one.</div>'; return; }
        files.sort(function (a, b) {
            var r = 0;
            if (fSort.key === 'name') r = String(a.name).toLowerCase().localeCompare(String(b.name).toLowerCase());
            else if (fSort.key === 'by') r = String(a.by).toLowerCase().localeCompare(String(b.by).toLowerCase());
            else r = (a.ts || 0) - (b.ts || 0);
            return r * fSort.dir;
        });
        function arrow(k){ return fSort.key === k ? (fSort.dir === 1 ? ' ↑' : ' ↓') : ''; }
        var rows = files.map(function (f) {
            var sel = fSelected[f.url] ? ' sel' : '';
            return '<tr class="' + sel.trim() + '" data-url="' + esc(f.url) + '" data-dl="' + esc(f.download) + '">'
                + '<td class="tc-fcheck"><input type="checkbox" ' + (fSelected[f.url] ? 'checked' : '') + '></td>'
                + '<td class="tc-ficon">' + extIcon(f.name, f.image) + '</td>'
                + '<td class="tc-fname"><a href="' + esc(f.image ? f.url : f.download) + '"' + (f.image ? ' data-lightbox' : '') + '>' + esc(f.name) + '</a></td>'
                + '<td class="tc-fcol-when tc-fmeta">' + esc(f.at) + ' · ' + esc(f.time || '') + '</td>'
                + '<td class="tc-fcol-by"><span class="tc-fby">' + esc(f.by) + '</span></td>'
                + '<td style="width:38px;text-align:right"><button type="button" class="tc-fdl" title="Download"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button></td>'
                + '</tr>';
        }).join('');
        var allSel = files.length && files.every(function (f) { return fSelected[f.url]; });
        filesScroll.innerHTML = '<table class="tc-ftable"><thead><tr>'
            + '<th class="tc-fcheck"><input type="checkbox" id="tcFilesAll" ' + (allSel ? 'checked' : '') + '></th><th class="tc-ficon"></th>'
            + '<th class="tc-fsort" data-sort="name">Name' + arrow('name') + '</th>'
            + '<th class="tc-fsort tc-fcol-when" data-sort="ts">Shared on' + arrow('ts') + '</th>'
            + '<th class="tc-fsort tc-fcol-by" data-sort="by">Sent by' + arrow('by') + '</th><th></th>'
            + '</tr></thead><tbody>' + rows + '</tbody></table>';
        updateSelBar();
    }
    function renderPhotos(){
        if (!photosScroll) return;
        var imgs = (galleryData || []).filter(function (f) { return f.image; });
        if (!imgs.length){ photosScroll.innerHTML = '<div class="tc-panel-empty">No photos shared in this chat yet.</div>'; return; }
        imgs.sort(function (a, b) { return (b.ts || 0) - (a.ts || 0); });
        var now = new Date(), groups = [], gmap = {};
        function label(ts){
            var d = new Date(ts * 1000), diff = (now - d) / 86400000;
            if (d.toDateString() === now.toDateString()) return 'Today';
            var y = new Date(now); y.setDate(y.getDate() - 1);
            if (d.toDateString() === y.toDateString()) return 'Yesterday';
            if (diff < 7) return 'Earlier this week';
            if (d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()) return 'Earlier this month';
            return d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        }
        imgs.forEach(function (f) {
            var l = label(f.ts || 0);
            if (!gmap[l]){ gmap[l] = []; groups.push(l); }
            gmap[l].push(f);
        });
        photosScroll.innerHTML = groups.map(function (l) {
            return '<div class="tc-photos-group">' + esc(l) + '</div><div class="tc-photos-grid">' + gmap[l].map(function (f) {
                return '<a class="tc-photo" href="' + esc(f.url) + '" data-lightbox><img src="' + esc(f.url) + '" loading="lazy"><span class="tc-photo-cap">' + esc(f.by) + '</span></a>';
            }).join('') + '</div>';
        }).join('');
    }
    function loadGallery(after){
        if (!CONV){ galleryData = []; after(); return; }
        fetch(BASE + '/gallery?c=' + CONV, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }, cache:'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (res) { galleryData = (res && res.files) || []; after(); })
            .catch(function () { galleryData = galleryData || []; after(); });
    }
    function switchTab(tab){
        currentTab = tab;
        if (tabsNav) tabsNav.querySelectorAll('.tc-tab').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
        thread.classList.remove('tcv-files', 'tcv-photos');
        var pf = document.getElementById('tcPanelFiles'), pp = document.getElementById('tcPanelPhotos');
        if (pf) pf.hidden = tab !== 'files';
        if (pp) pp.hidden = tab !== 'photos';
        if (tab === 'files'){ thread.classList.add('tcv-files'); loadGallery(renderFiles); }
        else if (tab === 'photos'){ thread.classList.add('tcv-photos'); loadGallery(renderPhotos); }
    }
    if (tabsNav){
        tabsNav.addEventListener('click', function (e) { var b = e.target.closest('.tc-tab'); if (b) switchTab(b.dataset.tab); });
    }
    // Re-fetch the active panel (e.g. after an upload).
    window.tcGalleryDirty = function () {
        if (currentTab === 'files') loadGallery(renderFiles);
        else if (currentTab === 'photos') loadGallery(renderPhotos);
    };
    // Files panel interactions.
    if (filesScroll){
        filesScroll.addEventListener('click', function (e) {
            var img = e.target.closest('a[data-lightbox]');
            if (img){ e.preventDefault(); lbImg.src = img.getAttribute('href'); lightbox.hidden = false; return; }
            var dl = e.target.closest('.tc-fdl');
            if (dl){ var tr = dl.closest('tr'); if (tr) downloadUrls([tr.dataset.dl]); return; }
            var all = e.target.closest('#tcFilesAll');
            if (all){
                var on = all.checked;
                (galleryData || []).forEach(function (f) { if (on) fSelected[f.url] = true; else delete fSelected[f.url]; });
                renderFiles(); return;
            }
            var cb = e.target.closest('.tc-fcheck input:not(#tcFilesAll)');
            if (cb){ var row = cb.closest('tr'), u = row.dataset.url; if (cb.checked) fSelected[u] = true; else delete fSelected[u]; row.classList.toggle('sel', cb.checked); updateSelBar(); return; }
            var th = e.target.closest('.tc-fsort');
            if (th){ var k = th.dataset.sort; if (fSort.key === k) fSort.dir *= -1; else { fSort.key = k; fSort.dir = k === 'ts' ? -1 : 1; } renderFiles(); }
        });
    }
    var upBtn = document.getElementById('tcFilesUpload');
    if (upBtn){ var fileInput = document.getElementById('tcFile'); if (fileInput) upBtn.addEventListener('click', function () { fileInput.click(); }); }
    var dlSel = document.getElementById('tcFilesDownload');
    if (dlSel) dlSel.addEventListener('click', function () {
        var urls = (galleryData || []).filter(function (f) { return fSelected[f.url]; }).map(function (f) { return f.download; });
        if (urls.length){ downloadUrls(urls); toast('Downloading ' + urls.length + ' file' + (urls.length > 1 ? 's' : '')); }
    });
    var clrSel = document.getElementById('tcFilesClearSel');
    if (clrSel) clrSel.addEventListener('click', function () { fSelected = {}; renderFiles(); });

    // ---------- Reply-quote → jump to the original message ----------
    box.addEventListener('click', function (e) {
        var q = e.target.closest('.tc-quote[data-goto]'); if (!q || !q.dataset.goto) return;
        var t = box.querySelector('.tc-msg[data-id="' + q.dataset.goto + '"]');
        if (t){ t.scrollIntoView({ behavior:'smooth', block:'center' }); t.classList.add('tc-flash'); setTimeout(function () { t.classList.remove('tc-flash'); }, 1300); }
    });

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
            .then(function (res) { if (res && res.ok) location.reload(); else toast('Could not update pin — try again'); });
    }

    // Pinned banner: expand/collapse, jump to a pinned message, unpin.
    var pinnedHead = document.getElementById('tcPinnedHead');
    var pinnedDrop = document.getElementById('tcPinnedDrop');
    if (pinnedHead && pinnedDrop){
        pinnedHead.addEventListener('click', function () { pinnedDrop.hidden = !pinnedDrop.hidden; });
        pinnedDrop.addEventListener('click', function (e) {
            var un = e.target.closest('[data-unpin]');
            if (un){ e.stopPropagation(); postJson(BASE + '/pin', { message_id: un.dataset.unpin, pinned: 0 }).then(function (res) { if (res && res.ok) location.reload(); else toast('Could not unpin — try again'); }); return; }
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
        var id = menuMsg.id, mine = menuMsg.mine;
        window.tcDelete(mine).then(function (mode) {
            if (!mode) return;
            postJson(BASE + '/' + id, { mode: mode }, 'DELETE').then(function (res) {
                if (!res || !res.ok) return;
                var el = box.querySelector('.tc-msg[data-id="' + id + '"]'); if (!el) return;
                if (mode === 'me'){ el.remove(); return; }
                var b = el.querySelector('.tc-bubble'); b.classList.add('deleted');
                b.innerHTML = '<div class="tc-text tc-deleted-text">🚫 You deleted this message</div>';
                var rr = el.querySelector('.tc-reacts'); if (rr) rr.innerHTML = '';
                var dots = el.querySelector('.tc-dots'); if (dots) dots.remove();
                el.dataset.pinned = 0;
            });
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
            toast(res && res.ok ? 'Forwarded to ' + name : 'Could not forward — try again');
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

    // ---- Teams-style hover quick-react bar: emojis appear the instant you hover a message ----
    var qr = document.getElementById('tcQuickReact');
    if (qr) TCB(qr);
    var qrId = null, qrEl = null, qrHideT = null;

    function buildQR(){
        var h = getRecent().slice(0, 6).map(function (e) {
            return '<button type="button" data-emoji="' + e + '">' + e + '</button>';
        }).join('');
        h += '<button type="button" class="tc-qr-more" data-qr-more title="More emojis">＋</button>'
           + '<span class="tc-qr-sep"></span>'
           + '<button type="button" class="tc-qr-dots" data-qr-dots title="More actions">⋯</button>';
        qr.innerHTML = h;
    }
    function positionQR(el){
        qr.style.left = '0px'; qr.style.top = '0px'; qr.hidden = false;
        var qw = qr.offsetWidth, qh = qr.offsetHeight;
        var bub = el.querySelector('.tc-bubble') || el;
        var br = bub.getBoundingClientRect();
        var mine = el.classList.contains('mine');
        var wantTop = Math.max(8, br.top - qh - 6);              // float just above the bubble
        var want = mine ? (br.right - qw) : br.left;            // align to the bubble's outer edge
        want = Math.max(8, Math.min(want, window.innerWidth - qw - 8));
        // Two-pass offset correction for any transformed ancestor (same trick as the menu).
        qr.style.left = want + 'px'; qr.style.top = wantTop + 'px';
        var a = qr.getBoundingClientRect();
        qr.style.left = (2 * want - a.left) + 'px';
        qr.style.top  = (2 * wantTop - a.top) + 'px';
    }
    function showQR(el){
        if (!qr || !el) return;
        var bub = el.querySelector('.tc-bubble');
        if (!bub || bub.classList.contains('deleted')) { hideQR(); return; }   // nothing to react to
        clearTimeout(qrHideT);
        qrId = parseInt(el.dataset.id, 10); qrEl = el;
        buildQR(); positionQR(el);
    }
    function hideQR(){ if (qr) qr.hidden = true; qrId = null; qrEl = null; }
    function scheduleHideQR(){ clearTimeout(qrHideT); qrHideT = setTimeout(hideQR, 220); }

    if (qr){
        box.addEventListener('mouseover', function (e) {
            var el = e.target.closest('.tc-msg'); if (!el) return;
            if (el !== qrEl) showQR(el); else clearTimeout(qrHideT);
        });
        box.addEventListener('mouseout', function (e) {
            var el = e.target.closest('.tc-msg'); if (!el) return;
            var rt = e.relatedTarget;
            if (!rt || (!el.contains(rt) && !qr.contains(rt))) scheduleHideQR();   // keep open over msg or bar
        });
        qr.addEventListener('mouseenter', function () { clearTimeout(qrHideT); });
        qr.addEventListener('mouseleave', scheduleHideQR);
        qr.addEventListener('click', function (e) {
            var em = e.target.closest('[data-emoji]');
            if (em){ if (qrId){ react(qrId, em.dataset.emoji); recordRecent(em.dataset.emoji); } hideQR(); return; }
            if (e.target.closest('[data-qr-more]')){ emojiTargetId = qrId; openPicker(qr.querySelector('[data-qr-more]')); hideQR(); return; }
            if (e.target.closest('[data-qr-dots]')){ var el = qrEl; hideQR();
                if (el){ var d = el.querySelector('.tc-dots'); var r = (d || el).getBoundingClientRect(); openMenu(r.left, r.bottom + 4, el); } }
        });
        box.addEventListener('scroll', hideQR);
    }

    // ---- Teams-style emoji picker (search + recent + categories + bottom tabs). Moved to <body>. ----
    var picker = document.getElementById('tcEmojiPicker');
    if (picker) TCB(picker);
    var pkScroll = picker && picker.querySelector('#tcEmojiScroll');
    var pkTabs   = picker && picker.querySelector('#tcEmojiTabs');
    var pkSearch = picker && picker.querySelector('#tcEmojiSearch');
    var tabsBuilt = false;

    function cellHtml(pair){ // pair = [emoji, keywords]
        return '<button type="button" data-emoji="' + pair[0] + '" title="' + pair[1].split(' ')[0] + '">' + pair[0] + '</button>';
    }
    function buildTabs(){
        if (tabsBuilt || !pkTabs) return; tabsBuilt = true;
        var h = '<button type="button" data-cat="recent" title="Recent">🕘</button>';
        EMOJI_CATS.forEach(function (c) { h += '<button type="button" data-cat="' + c.key + '" title="' + c.name + '">' + c.tab + '</button>'; });
        pkTabs.innerHTML = h;
    }
    function renderSections(){ // full list: Recent + every category, each with an anchor for tab-jump
        var rec = getRecent();
        var h = '';
        if (rec.length){
            h += '<div class="tc-emoji-cat" data-sec="recent">Recent</div><div class="tc-emoji-grid">'
               + rec.map(function (e) { return cellHtml([e, e]); }).join('') + '</div>';
        }
        EMOJI_CATS.forEach(function (c) {
            h += '<div class="tc-emoji-cat" data-sec="' + c.key + '">' + c.name + '</div><div class="tc-emoji-grid">'
               + c.items.map(cellHtml).join('') + '</div>';
        });
        pkScroll.innerHTML = h;
    }
    function renderSearch(q){
        q = q.trim().toLowerCase();
        if (!q){ renderSections(); return; }
        var seen = {}, hits = [];
        EMOJI_CATS.forEach(function (c) {
            c.items.forEach(function (p) {
                if (!seen[p[0]] && (p[1].indexOf(q) !== -1 || p[0] === q)){ seen[p[0]] = 1; hits.push(p); }
            });
        });
        pkScroll.innerHTML = hits.length
            ? '<div class="tc-emoji-grid" style="margin-top:6px">' + hits.map(cellHtml).join('') + '</div>'
            : '<div class="tc-emoji-empty">No emoji found for “' + q.replace(/[<>&]/g, '') + '”</div>';
    }
    function syncActiveTab(){
        if (!pkTabs || pkScroll.querySelector('.tc-emoji-empty')) return;
        var secs = pkScroll.querySelectorAll('[data-sec]'), top = pkScroll.getBoundingClientRect().top, cur = null;
        secs.forEach(function (s) { if (s.getBoundingClientRect().top - top <= 12) cur = s.getAttribute('data-sec'); });
        pkTabs.querySelectorAll('button').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-cat') === cur); });
    }

    var pickerMode = 'react';
    function place(anchor){
        var r = anchor.getBoundingClientRect(), vw = window.innerWidth, vh = window.innerHeight;
        var pw = picker.offsetWidth, ph = picker.offsetHeight;
        var left = Math.max(8, Math.min(r.left, vw - pw - 8));
        // Teams pops UP from the composer/anchor; fall back to down only if there's no room above.
        var top = (r.top > ph + 12) ? (r.top - ph - 8) : Math.min(r.bottom + 8, vh - ph - 8);
        top = Math.max(8, top);
        picker.style.left = left + 'px'; picker.style.top = top + 'px';
        // Two-pass: correct for any transformed ancestor so position:fixed lands true to the viewport.
        var got = picker.getBoundingClientRect(), dx = left - got.left, dy = top - got.top;
        if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5){ picker.style.left = (left + dx) + 'px'; picker.style.top = (top + dy) + 'px'; }
    }
    function openPicker(anchor, mode){
        pickerMode = mode || 'react';
        buildTabs(); if (pkSearch) pkSearch.value = '';
        renderSections();
        picker.hidden = false;
        place(anchor);
        syncActiveTab();
    }
    function closePicker(){ if (picker) picker.hidden = true; if (pickerMode !== 'compose') emojiTargetId = null; }

    function pickEmoji(emoji){
        if (pickerMode === 'compose'){
            var s = input.selectionStart, en = input.selectionEnd, v = input.value;
            input.value = v.slice(0, s) + emoji + v.slice(en);
            var caret = s + emoji.length; input.setSelectionRange(caret, caret);
            input.focus(); grow(); recordRecent(emoji); closePicker();
        } else {
            if (emojiTargetId){ react(emojiTargetId, emoji); recordRecent(emoji); }
            closePicker();
        }
    }
    if (picker){
        pkScroll.addEventListener('click', function (e) {
            var b = e.target.closest('[data-emoji]'); if (b) pickEmoji(b.dataset.emoji);
        });
        if (pkTabs) pkTabs.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cat]'); if (!b) return;
            if (pkSearch && pkSearch.value){ pkSearch.value = ''; renderSections(); }
            var sec = pkScroll.querySelector('[data-sec="' + b.getAttribute('data-cat') + '"]');
            if (sec) pkScroll.scrollTop = sec.offsetTop - 4;
            syncActiveTab();
        });
        pkScroll.addEventListener('scroll', syncActiveTab, { passive: true });
        if (pkSearch){
            pkSearch.addEventListener('input', function () { renderSearch(pkSearch.value); syncActiveTab(); });
            pkSearch.addEventListener('keydown', function (e) {
                if (e.key === 'Enter'){ e.preventDefault(); var first = pkScroll.querySelector('[data-emoji]'); if (first) pickEmoji(first.dataset.emoji); }
                else if (e.key === 'Escape'){ closePicker(); }
            });
        }
        TCD('click', function (e) {
            if (picker.hidden) return;
            if (!picker.contains(e.target) && !e.target.closest('[data-more]') && !e.target.closest('[data-qr-more]') && e.target.id !== 'tcEmojiBtn' && !e.target.closest('#tcEmojiBtn')) closePicker();
        });
    }
    // Composer emoji button (compose mode). Focus search so you can type to find one instantly.
    var emojiBtn = document.getElementById('tcEmojiBtn');
    if (emojiBtn && picker) emojiBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!picker.hidden){ closePicker(); return; }
        openPicker(emojiBtn, 'compose');
        setTimeout(function () { try { pkSearch.focus(); } catch (err) {} }, 30);
    });

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
            else if (act === 'edit') startEdit();
            else if (act === 'forward') openForward();
            else if (act === 'pin') pinMsg();
            else if (act === 'delete') delMsg();
            closeMenu();
        });
    }

    TCD('click', function (e) {
        if (!menu || menu.hidden) return;
        if (Date.now() < guardUntil) return;
        if (!menu.contains(e.target)) closeMenu();
    });
    box.addEventListener('scroll', function () { if (menu && !menu.hidden) closeMenu(); });
    TCD('keydown', function (e) {
        if (e.key === 'Escape'){ closeMenu(); if (fwdModal) fwdModal.hidden = true; closeLightbox(); closePicker(); }
        else if (lightbox && !lightbox.hidden){ if (e.key === 'ArrowLeft') lbNav(-1); else if (e.key === 'ArrowRight') lbNav(1); }
    });

    var rc = document.getElementById('tcReplyCancel'); if (rc) rc.addEventListener('click', cancelReply);
    if (fwdModal){
        var fc = document.getElementById('tcFwdClose'); if (fc) fc.addEventListener('click', function () { fwdModal.hidden = true; });
        fwdModal.addEventListener('click', function (e) { if (e.target === fwdModal) fwdModal.hidden = true; });
    }

    // ---------- Group members panel (active group only) ----------
    var membersModal = document.getElementById('tcMembers');
    var membersBtn = document.getElementById('tcMembersBtn');
    if (membersModal) TCB(membersModal);
    if (membersModal && membersBtn){
        var GID = membersModal.dataset.group;
        function gpost(path, extra){
            var fd = new FormData(); fd.append('_token', csrf);
            if (extra) extra(fd);
            return fetch(GBASE + '/' + GID + path, { method:'POST', body:fd,
                headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).then(function (r) { return r.json(); });
        }
        membersBtn.addEventListener('click', function () { membersModal.hidden = false; });

        // Inline group-name edit (Teams-style pencil in the header).
        var nameEl = document.getElementById('tcGroupName');
        var nameEdit = document.getElementById('tcGroupNameEdit');
        if (nameEl && nameEdit){
            var editing = false;
            function startNameEdit(){
                if (editing) return; editing = true;
                var cur = nameEl.textContent.trim();
                var inp = document.createElement('input');
                inp.type = 'text'; inp.className = 'tc-th-name-input'; inp.maxLength = 80; inp.value = cur;
                nameEl.hidden = true; nameEdit.hidden = true;
                nameEl.parentNode.insertBefore(inp, nameEl);
                inp.focus(); inp.select();
                var done = false;
                function finish(save){
                    if (done) return; done = true;
                    var nm = inp.value.trim();
                    inp.remove(); nameEl.hidden = false; nameEdit.hidden = false; editing = false;
                    if (save && nm && nm !== cur){
                        gpost('/rename', function (fd) { fd.append('name', nm); }).then(function (res) {
                            if (res && res.ok){
                                nameEl.textContent = nm;
                                var rn = document.getElementById('tcRenameName'); if (rn) rn.value = nm;
                                var row = document.querySelector('.tc-contact[data-conversation="' + GID + '"] .tc-c-name');
                                if (row) row.textContent = nm;
                                if (typeof toast === 'function') toast('Group renamed');
                            } else { toast('Could not rename'); }
                        });
                    }
                }
                inp.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter'){ e.preventDefault(); finish(true); }
                    else if (e.key === 'Escape'){ e.preventDefault(); finish(false); }
                });
                inp.addEventListener('blur', function () { finish(true); });
            }
            nameEdit.addEventListener('click', startNameEdit);
        }
        document.getElementById('tcMembersClose').addEventListener('click', function () { membersModal.hidden = true; });
        membersModal.addEventListener('click', function (e) { if (e.target === membersModal) membersModal.hidden = true; });

        membersModal.addEventListener('click', function (e) {
            var rem = e.target.closest('.tc-member-remove'); if (!rem) return;
            window.tcConfirm('Remove this member from the group?', 'Remove').then(function (ok) {
                if (!ok) return;
                gpost('/members/' + rem.dataset.admin, function (fd) { fd.append('_method', 'DELETE'); })
                    .then(function (res) { if (res && res.ok) location.reload(); else toast('Could not remove — try again'); });
            });
        });
        var addBtn = document.getElementById('tcAddMembersBtn');
        if (addBtn) addBtn.addEventListener('click', function () {
            var ids = Array.prototype.map.call(membersModal.querySelectorAll('.tc-member-add-list input:checked'), function (c) { return c.value; });
            if (!ids.length) { toast('Select teammates to add'); return; }
            gpost('/members', function (fd) { ids.forEach(function (i) { fd.append('members[]', i); }); })
                .then(function (res) { if (res && res.ok) location.reload(); else toast('Could not add members — try again'); });
        });
        var renameBtn = document.getElementById('tcRenameBtn');
        if (renameBtn) renameBtn.addEventListener('click', function () {
            var nm = document.getElementById('tcRenameName').value.trim(); if (!nm) { toast('Name required'); return; }
            gpost('/rename', function (fd) { fd.append('name', nm); }).then(function (res) { if (res && res.ok) location.reload(); else toast('Could not rename — try again'); });
        });
        document.getElementById('tcLeaveBtn').addEventListener('click', function () {
            window.tcConfirm('Leave this group?', 'Leave').then(function (ok) {
                if (!ok) return;
                gpost('/leave').then(function (res) { if (res && res.ok) location.href = @js(route('admin.team-messages.index')); else toast('Could not leave — try again'); });
            });
        });
    }
})();

// New-group modal — lives outside the thread scope so it works with no chat open.
(function () {
    var modal = document.getElementById('tcGroupModal');
    var openBtn = document.getElementById('tcNewGroup');
    if (!modal || !openBtn) return;
    TCB(modal);
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
            .then(function (res) {
                if (!res || !res.ok) { toast('Could not create group'); return; }
                var url = '?c=' + res.conversation_id + (@js(request()->boolean('standalone')) ? '&standalone=1' : '');
                if (window.tcNav) window.tcNav(url, true); else location.href = url;
            });
    });
})();

// Styled confirm + delete dialogs (global helpers; moved to <body> for exact overlay).
(function () {
    var cm = document.getElementById('tcConfirmModal');
    var dm = document.getElementById('tcDeleteModal');
    if (cm) TCB(cm);
    if (dm) TCB(dm);

    window.tcConfirm = function (message, okLabel) {
        return new Promise(function (resolve) {
            if (!cm) { resolve(window.confirm(message)); return; }
            document.getElementById('tcConfirmMsg').textContent = message;
            var ok = document.getElementById('tcConfirmOk'); ok.textContent = okLabel || 'Confirm';
            var ca = document.getElementById('tcConfirmCancel');
            cm.hidden = false;
            function fin(v){ cm.hidden = true; ok.removeEventListener('click', okH); ca.removeEventListener('click', caH); cm.removeEventListener('click', bg); document.removeEventListener('keydown', kh); resolve(v); }
            function okH(){ fin(true); } function caH(){ fin(false); } function bg(e){ if (e.target === cm) fin(false); } function kh(e){ if (e.key === 'Escape') fin(false); }
            ok.addEventListener('click', okH); ca.addEventListener('click', caH); cm.addEventListener('click', bg); document.addEventListener('keydown', kh);
        });
    };

    window.tcDelete = function (mine) {
        return new Promise(function (resolve) {
            if (!dm) { resolve(window.confirm('Delete this message?') ? (mine ? 'everyone' : 'me') : null); return; }
            var ev = document.getElementById('tcDelEveryone'); ev.hidden = !mine;
            var meB = document.getElementById('tcDelMe'), ca = document.getElementById('tcDelCancel');
            dm.hidden = false;
            function fin(v){ dm.hidden = true; ev.removeEventListener('click', evH); meB.removeEventListener('click', meH); ca.removeEventListener('click', caH); dm.removeEventListener('click', bg); document.removeEventListener('keydown', kh); resolve(v); }
            function evH(){ fin('everyone'); } function meH(){ fin('me'); } function caH(){ fin(null); } function bg(e){ if (e.target === dm) fin(null); } function kh(e){ if (e.key === 'Escape') fin(null); }
            ev.addEventListener('click', evH); meB.addEventListener('click', meH); ca.addEventListener('click', caH); dm.addEventListener('click', bg); document.addEventListener('keydown', kh);
        });
    };
})();

// Sidebar organize — search, filters, favorite, mute (works with no chat open).
(function () {
    var search = document.getElementById('tcSearch'); if (!search) return;
    var contacts = document.getElementById('tcContacts');
    var clearBtn = document.getElementById('tcSearchClear');
    var srBox = document.getElementById('tcSearchResults');
    var noRes = document.getElementById('tcNoResults');
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
            if (curFilter === 'groups' && r.dataset.group !== '1') show = false;
            if (searching && r.dataset.name.indexOf(q) < 0) show = false;
            r.hidden = !show; if (show) anyRow = true;
        });
        if (searching && q.length >= 2) scheduleSearch(search.value.trim());
        else { srBox.hidden = true; srBox.innerHTML = ''; lastQuery = ''; noRes.hidden = anyRow || !searching; }
    }
    // Re-apply the active filter/search when a live poll changes unread/order, so (e.g.) the
    // "Unread" filter reveals a chat that just became unread instead of leaving it hidden.
    TCW('apex:sidebar-changed', applyView);

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
            var sa = @js(request()->boolean('standalone')) ? '&standalone=1' : '';
            srBox.innerHTML = '<div class="tc-section">Messages</div>' + msgs.map(function (m) {
                return '<a class="tc-sr-item" href="?c=' + m.conversation_id + sa + '"><span class="tc-sr-title">' + esc(m.title)
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
            if (on) chatList.insertBefore(row, chatList.firstChild);   // float favorites to the top
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
        if (document.hidden) return;   // don't poll a backgrounded tab
        fetch(URL + '?_=' + Date.now(), { cache:'no-store', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r) { return r.ok && !r.redirected ? r.json() : null; })
            .then(function (res) {
                if (!res || !res.presence) return;
                res.presence.forEach(function (p) { var d = document.querySelector('.tc-dot[data-dot="' + p.id + '"]'); if (d) d.classList.toggle('on', !!p.online); });
            }).catch(function () {});
    }
    TCI(tick, 20000);
})();
</script>

{{-- SPA navigation — open chats without a full page reload. Persists across nav
     (NOT tagged data-tc), re-running only the chat script for the new thread. --}}
<script>
(function () {
    if (window.__tcNavInit) return; window.__tcNavInit = true;

    function samePath(href){ try { return new URL(href, location.href).pathname === location.pathname; } catch (e) { return false; } }

    window.tcNav = function (url, push) {
        var wrap = document.querySelector('.tc-wrap'); if (!wrap) { location.href = url; return; }
        wrap.style.opacity = '0.55';
        fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nw = doc.querySelector('.tc-wrap'), cur = document.querySelector('.tc-wrap');
                if (!nw || !cur) { location.href = url; return; }
                cur.replaceWith(nw);
                if (doc.title) document.title = doc.title;
                if (push !== false) history.pushState({ tc: 1 }, '', url);
                var old = document.querySelector('script[data-tc]'); if (old && old.parentNode) old.parentNode.removeChild(old);
                var s = doc.querySelector('script[data-tc]');
                if (s){ var el = document.createElement('script'); el.setAttribute('data-tc', ''); el.textContent = s.textContent; document.body.appendChild(el); }
                var box = document.getElementById('tcMessages'); if (box){ box.scrollTop = box.scrollHeight; }
            })
            .catch(function () { location.href = url; });
    };

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a.tc-contact, a.tc-sr-item');
        if (!a || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.getAttribute('target') === '_blank' || !samePath(a.href)) return;
        e.preventDefault();
        window.tcNav(a.href, true);
    });
    window.addEventListener('popstate', function () { window.tcNav(location.href, false); });
})();
</script>
@endpush
@endsection
