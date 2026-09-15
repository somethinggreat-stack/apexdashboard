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
                <button type="button" class="tc-me-av" id="tcMeAvatar" title="Change your photo" aria-label="Change your photo">
                    {!! $avatar($me, 'sm') !!}<i class="tc-me-dot"></i>
                    <span class="tc-me-av-edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
                </button>
                <div class="tc-me-info">
                    <span class="tc-me-name">{{ $me->full_name }}</span>
                </div>
                <button type="button" class="tc-newgroup" id="tcRefresh" title="Refresh" aria-label="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </button>
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

            {{-- Downloads dock — shows live progress + a saved/failed state (moved to <body> by JS) --}}
            <div class="tc-dl-dock" id="tcDlDock" hidden></div>

            {{-- Profile-photo menu + cropper (moved to <body> by JS) --}}
            <div class="tc-avm" id="tcAvMenu" hidden>
                <button type="button" id="tcAvChange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Change photo
                </button>
                <button type="button" class="danger" id="tcAvRemove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Remove photo
                </button>
            </div>
            <input type="file" id="tcAvFile" accept="image/png,image/jpeg,image/webp" hidden>
            <div class="tc-crop-mask" id="tcCropMask" hidden>
                <div class="tc-crop-card">
                    <h3 class="tc-crop-title">Adjust your photo</h3>
                    <p class="tc-crop-hint">Drag to move · use the slider to zoom. Keep your face inside the circle.</p>
                    <div class="tc-crop-stage" id="tcCropStage">
                        <img id="tcCropImg" alt="">
                        <div class="tc-crop-ring"></div>
                    </div>
                    <div class="tc-crop-zoom">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        <input type="range" id="tcCropZoom" min="1" max="4" step="0.01" value="1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="11" y1="8" x2="11" y2="14"/></svg>
                    </div>
                    <div class="tc-crop-actions">
                        <button type="button" class="tc-crop-cancel" id="tcCropCancel">Cancel</button>
                        <button type="button" class="tc-crop-save" id="tcCropSave">Save photo</button>
                    </div>
                </div>
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
    <link rel="stylesheet" href="{{ asset('css/team-chat.css') }}?v={{ is_file(public_path('css/team-chat.css')) ? filemtime(public_path('css/team-chat.css')) : '1' }}">
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
    // Coming back to the window (focus) or on visibility restore → refresh the OPEN thread now,
    // so its newest messages are there instantly without waiting for the 3s timer or a click.
    TCW('focus', function () { poll(true); });
    TCD('visibilitychange', function () { if (!document.hidden) poll(true); });

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

    // ---------- Downloads with visible progress + a saved / failed state ----------
    // File clicks used to hand off to the webview and download silently. Now every
    // download streams through here so the user sees it start, progress, and finish.
    var dlDock = document.getElementById('tcDlDock');
    if (dlDock) TCB(dlDock);
    var DL_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    var DONE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    var FAIL_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    function fmtBytes(n){
        if (!n && n !== 0) return '';
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(0) + ' KB';
        if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
        return (n / 1073741824).toFixed(2) + ' GB';
    }
    // Prefer the real filename the server declares (Content-Disposition), else the hint.
    function nameFromDisposition(cd, fallback){
        if (cd){
            var star = /filename\*=(?:UTF-8'')?([^;]+)/i.exec(cd);
            if (star && star[1]) { try { return decodeURIComponent(star[1].trim().replace(/^"|"$/g, '')); } catch (e) {} }
            var plain = /filename="?([^";]+)"?/i.exec(cd);
            if (plain && plain[1]) return plain[1].trim();
        }
        return fallback || 'download';
    }
    function dlCard(name){
        var el = document.createElement('div');
        el.className = 'tc-dl-card indet';
        el.innerHTML = '<span class="tc-dl-ic">' + DL_ICON + '</span>'
            + '<span class="tc-dl-body"><span class="tc-dl-name"></span><span class="tc-dl-sub">Starting…</span>'
            + '<span class="tc-dl-track"><i></i></span></span>'
            + '<button type="button" class="tc-dl-x" aria-label="Dismiss">&times;</button>';
        el.querySelector('.tc-dl-name').textContent = name || 'File';
        dlDock.appendChild(el); dlDock.hidden = false;
        requestAnimationFrame(function () { el.classList.add('in'); });
        var bar = el.querySelector('.tc-dl-track i'), sub = el.querySelector('.tc-dl-sub');
        var api = {
            el: el,
            setName: function (n) { el.querySelector('.tc-dl-name').textContent = n; },
            progress: function (loaded, total) {
                el.classList.remove('indet');
                if (total) { bar.style.width = Math.round(loaded / total * 100) + '%'; sub.textContent = fmtBytes(loaded) + ' / ' + fmtBytes(total); }
                else sub.textContent = fmtBytes(loaded) + ' downloaded';
            },
            done: function () {
                el.classList.remove('indet'); el.classList.add('done');
                bar.style.width = '100%'; sub.textContent = 'Saved to your Downloads';
                el.querySelector('.tc-dl-ic').innerHTML = DONE_ICON;
                dismiss(4500);
            },
            fail: function (msg) {
                el.classList.remove('indet'); el.classList.add('fail');
                sub.textContent = msg || 'Download failed'; el.querySelector('.tc-dl-ic').innerHTML = FAIL_ICON;
            }
        };
        function remove(){ el.classList.add('out'); setTimeout(function () { el.remove(); if (!dlDock.children.length) dlDock.hidden = true; }, 220); }
        function dismiss(after){ setTimeout(remove, after); }
        el.querySelector('.tc-dl-x').addEventListener('click', remove);
        return api;
    }

    // Stream a file to the user's Downloads with a live progress card.
    window.tcDownload = function (url, nameHint){
        var card = dlCard(nameHint);
        // Streaming fetch (WebView2 / Chromium) gives us byte-level progress.
        if (window.fetch && window.ReadableStream){
            fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (resp){
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var name = nameFromDisposition(resp.headers.get('Content-Disposition'), nameHint);
                card.setName(name);
                var total = parseInt(resp.headers.get('Content-Length') || '0', 10) || 0;
                if (!resp.body || !resp.body.getReader) return resp.blob().then(function (b){ return { blob: b, name: name }; });
                var reader = resp.body.getReader(), chunks = [], received = 0;
                return (function pump(){
                    return reader.read().then(function (r){
                        if (r.done) return { blob: new Blob(chunks), name: name };
                        chunks.push(r.value); received += r.value.length; card.progress(received, total);
                        return pump();
                    });
                })();
            }).then(function (out){
                saveBlob(out.blob, out.name); card.done();
            }).catch(function (err){
                card.fail('Couldn’t download — tap the file to retry');
                console && console.warn && console.warn('download failed', err);
            });
        } else {
            // Old fallback: let the browser handle it, just show a generic "saved" note.
            var a = document.createElement('a'); a.href = url; a.rel = 'noopener'; TCB(a); a.click(); a.remove();
            card.el.classList.remove('indet'); card.done();
        }
    };
    function saveBlob(blob, name){
        var u = URL.createObjectURL(blob);
        var a = document.createElement('a'); a.href = u; a.download = name || 'download';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(u); }, 8000);
    }

    // Intercept file-card clicks in the thread so downloads go through the progress dock.
    box.addEventListener('click', function (e){
        var f = e.target.closest('.tc-att-file'); if (!f) return;
        e.preventDefault();
        var nm = f.querySelector('.tc-att-name'); nm = nm ? nm.textContent : '';
        window.tcDownload(f.getAttribute('href'), nm);
    });

    // ---------- Profile photo: change (with a face-crop frame) / remove ----------
    (function () {
        var avBtn = document.getElementById('tcMeAvatar'); if (!avBtn) return;
        var menu = document.getElementById('tcAvMenu'), fileInp = document.getElementById('tcAvFile');
        var mask = document.getElementById('tcCropMask'), stage = document.getElementById('tcCropStage');
        var cImg = document.getElementById('tcCropImg'), zoom = document.getElementById('tcCropZoom');
        var saveBtn = document.getElementById('tcCropSave'), cancelBtn = document.getElementById('tcCropCancel');
        var chBtn = document.getElementById('tcAvChange'), rmBtn = document.getElementById('tcAvRemove');
        [menu, mask, fileInp].forEach(function (n) { if (n) TCB(n); });
        var UP_URL = @json(route('admin.team-messages.avatar.update'));
        var RM_URL = @json(route('admin.team-messages.avatar.remove'));
        var CSRF = (document.querySelector('meta[name=csrf-token]') || {}).content || (typeof csrf !== 'undefined' ? csrf : '');
        var STAGE = 280;   // stage px (matches CSS)

        // ----- menu open / close -----
        function openMenu(){
            var r = avBtn.getBoundingClientRect();
            menu.style.left = r.left + 'px'; menu.style.top = (r.bottom + 8) + 'px';
            menu.hidden = false;
        }
        function closeMenu(){ menu.hidden = true; }
        avBtn.addEventListener('click', function (e){ e.stopPropagation(); if (menu.hidden) openMenu(); else closeMenu(); });
        document.addEventListener('click', function (e){ if (!menu.hidden && !menu.contains(e.target) && e.target !== avBtn) closeMenu(); });
        chBtn.addEventListener('click', function (){ closeMenu(); fileInp.click(); });
        rmBtn.addEventListener('click', function (){ closeMenu(); removePhoto(); });

        // ----- cropper state -----
        var img = new Image(), natW = 0, natH = 0, base = 1, tx = 0, ty = 0;
        function scale(){ return base * parseFloat(zoom.value || '1'); }
        function clamp(){
            var s = scale(), iw = natW * s, ih = natH * s;
            tx = Math.min(0, Math.max(STAGE - iw, tx));
            ty = Math.min(0, Math.max(STAGE - ih, ty));
        }
        function paint(){ clamp(); cImg.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale() + ')'; }

        fileInp.addEventListener('change', function (){
            var f = fileInp.files && fileInp.files[0]; if (!f) return;
            if (!/^image\//.test(f.type)) { toast('Please choose an image'); return; }
            var url = URL.createObjectURL(f);
            img = new Image();
            img.onload = function (){
                natW = img.naturalWidth; natH = img.naturalHeight;
                base = Math.max(STAGE / natW, STAGE / natH);   // cover the stage
                cImg.src = url; cImg.style.width = natW + 'px'; cImg.style.height = natH + 'px';
                zoom.value = '1';
                tx = (STAGE - natW * base) / 2; ty = (STAGE - natH * base) / 2;
                paint(); mask.hidden = false;
            };
            img.onerror = function (){ toast('That image could not be opened'); };
            img.src = url;
            fileInp.value = '';
        });
        zoom.addEventListener('input', function (){
            // Zoom around the stage centre so the face stays put.
            var cx = STAGE / 2, cy = STAGE / 2, prev = cImg._s || scale();
            var ns = scale(), k = ns / prev;
            tx = cx - (cx - tx) * k; ty = cy - (cy - ty) * k;
            cImg._s = ns; paint();
        });

        // ----- drag to pan (mouse + touch via pointer events) -----
        var dragging = false, px = 0, py = 0;
        stage.addEventListener('pointerdown', function (e){ dragging = true; px = e.clientX; py = e.clientY; stage.classList.add('drag'); stage.setPointerCapture(e.pointerId); });
        stage.addEventListener('pointermove', function (e){ if (!dragging) return; tx += e.clientX - px; ty += e.clientY - py; px = e.clientX; py = e.clientY; paint(); });
        function endDrag(){ dragging = false; stage.classList.remove('drag'); }
        stage.addEventListener('pointerup', endDrag); stage.addEventListener('pointercancel', endDrag);

        cancelBtn.addEventListener('click', function (){ mask.hidden = true; });
        mask.addEventListener('click', function (e){ if (e.target === mask) mask.hidden = true; });

        // ----- export the crop → upload -----
        saveBtn.addEventListener('click', function (){
            var OUT = 320, s = scale();
            var cv = document.createElement('canvas'); cv.width = OUT; cv.height = OUT;
            var ctx = cv.getContext('2d');
            // Map the visible stage square back to source-image pixels.
            var sx = -tx / s, sy = -ty / s, sSide = STAGE / s;
            ctx.drawImage(img, sx, sy, sSide, sSide, 0, 0, OUT, OUT);
            saveBtn.disabled = true; saveBtn.textContent = 'Saving…';
            cv.toBlob(function (blob){
                if (!blob) { saveBtn.disabled = false; saveBtn.textContent = 'Save photo'; toast('Could not prepare the image'); return; }
                var fd = new FormData();
                fd.append('_token', CSRF); fd.append('photo', blob, 'avatar.jpg');
                fetch(UP_URL, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: fd })
                    .then(function (r){ return r.ok ? r.json() : Promise.reject(r.status); })
                    .then(function (){ toast('Photo updated'); setTimeout(function(){ location.reload(); }, 400); })
                    .catch(function (){ saveBtn.disabled = false; saveBtn.textContent = 'Save photo'; toast('Could not save photo — try again'); });
            }, 'image/jpeg', 0.9);
        });

        function removePhoto(){
            fetch(RM_URL, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-HTTP-Method-Override': 'DELETE', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(CSRF) + '&_method=DELETE' })
                .then(function (r){ return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (){ toast('Photo removed'); setTimeout(function(){ location.reload(); }, 400); })
                .catch(function (){ toast('Could not remove photo'); });
        }
    })();

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
            return '<a class="tc-gallery-file" href="' + f.download + '" data-dlname="' + esc(f.name) + '"><span class="tc-att-ic">' + FILE_SVG + '</span><span class="tc-gallery-meta"><b>'
                + esc(f.name) + '</b><span>' + esc(f.size) + ' · ' + esc(f.by) + ' · ' + esc(f.at) + '</span></span></a>';
        }).join('');
        gbody.innerHTML = html;
        gbody.onclick = function (e){
            var lb = e.target.closest('a[data-lightbox]');
            if (lb){ e.preventDefault(); lbImg.src = lb.getAttribute('href'); lightbox.hidden = false; return; }
            var fl = e.target.closest('a[data-dlname]');
            if (fl){ e.preventDefault(); downloadUrls([{ url: fl.getAttribute('href'), name: fl.getAttribute('data-dlname') }]); }
        };
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

    // Route Files-tab downloads through the same progress dock (window.tcDownload).
    // Accepts either a list of URLs or a list of {url, name} so each card is labelled.
    var dlFrame = null;
    function downloadUrls(urls){
        if (!urls || !urls.length) return;
        if (window.tcDownload){
            urls.forEach(function (u){
                if (typeof u === 'string') window.tcDownload(u, '');
                else window.tcDownload(u.url, u.name || '');
            });
            return;
        }
        // Legacy fallback (no fetch/streaming): reuse a hidden iframe, sequentially.
        var list = urls.map(function (u){ return typeof u === 'string' ? u : u.url; });
        if (!dlFrame){ dlFrame = document.createElement('iframe'); dlFrame.style.display = 'none'; TCB(dlFrame); }
        var i = 0;
        (function next(){ if (i >= list.length) return; dlFrame.src = list[i++]; setTimeout(next, 500); })();
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
            return '<tr class="' + sel.trim() + '" data-url="' + esc(f.url) + '" data-dl="' + esc(f.download) + '" data-name="' + esc(f.name) + '">'
                + '<td class="tc-fcheck"><input type="checkbox" ' + (fSelected[f.url] ? 'checked' : '') + '></td>'
                + '<td class="tc-ficon">' + extIcon(f.name, f.image) + '</td>'
                + '<td class="tc-fname"><a href="' + esc(f.image ? f.url : f.download) + '"' + (f.image ? ' data-lightbox' : ' data-dlname="' + esc(f.name) + '"') + '>' + esc(f.name) + '</a></td>'
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
            var nameLink = e.target.closest('a[data-dlname]');
            if (nameLink){ e.preventDefault(); downloadUrls([{ url: nameLink.getAttribute('href'), name: nameLink.getAttribute('data-dlname') }]); return; }
            var dl = e.target.closest('.tc-fdl');
            if (dl){ var tr = dl.closest('tr'); if (tr) downloadUrls([{ url: tr.dataset.dl, name: tr.dataset.name }]); return; }
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
        var urls = (galleryData || []).filter(function (f) { return fSelected[f.url]; }).map(function (f) { return { url: f.download, name: f.name }; });
        if (urls.length){ downloadUrls(urls); }
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

    // ---------- Group members panel (works across light swaps between groups) ----------
    // A persistent modal shell (server-rendered one if present, else created) + a mutable
    // GID, so switching groups in place just re-renders the roster and re-points GID.
    // Every add/remove/rename is still authorised server-side.
    var GID = '';
    var membersModal = document.getElementById('tcMembers');
    if (!membersModal){
        membersModal = document.createElement('div');
        membersModal.className = 'tc-modal'; membersModal.id = 'tcMembers'; membersModal.hidden = true;
        membersModal.innerHTML = '<div class="tc-modal-card"></div>';
    }
    TCB(membersModal);
    if (membersModal.dataset.group) GID = membersModal.dataset.group;   // initial server-rendered group
    else if (box.dataset.group === '1' && CONV) GID = CONV;

    function gpost(path, extra){
        var fd = new FormData(); fd.append('_token', csrf);
        if (extra) extra(fd);
        return fetch(GBASE + '/' + GID + path, { method:'POST', body:fd,
            headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }).then(function (r) { return r.json(); });
    }

    // Render the members-modal card from an /open group payload + re-point GID.
    function renderMembersModal(g){
        GID = String(g.id);
        membersModal.dataset.group = GID; membersModal.dataset.admin = g.isAdmin ? '1' : '0';
        var rows = (g.members || []).map(function (mp){
            var av = mp.avatar ? '<span class="tc-avatar sm has-img"><img src="' + mp.avatar + '" alt=""></span>'
                : '<span class="tc-avatar sm" style="background:' + mp.color + '">' + esc(mp.mono) + '</span>';
            return '<div class="tc-member-row" data-admin="' + mp.id + '">' + av
                + '<span class="tc-member-name">' + esc(mp.name) + (mp.you ? ' (you)' : '') + '</span>'
                + (mp.admin ? '<span class="tc-member-badge">Admin</span>' : '')
                + ((g.isAdmin && !mp.you) ? '<button type="button" class="tc-member-remove" data-admin="' + mp.id + '" title="Remove">&times;</button>' : '')
                + '</div>';
        }).join('');
        var tools = g.isAdmin
            ? '<div class="tc-member-tools"><input type="text" id="tcRenameName" class="tc-inp" value="' + esc(g.name) + '" maxlength="80" placeholder="Group name"><button type="button" class="tc-btn-mini" id="tcRenameBtn">Rename</button></div>'
            : '';
        var addSection = (g.isAdmin && (g.addable || []).length)
            ? '<div class="tc-member-add"><div class="tc-member-add-title">Add members</div><div class="tc-member-add-list">'
                + g.addable.map(function (t){ return '<label class="tc-addable"><input type="checkbox" value="' + t.id + '"> ' + esc(t.name) + '</label>'; }).join('')
                + '</div><button type="button" class="tc-btn-mini" id="tcAddMembersBtn">Add selected</button></div>'
            : '';
        membersModal.querySelector('.tc-modal-card').innerHTML =
            '<div class="tc-modal-head"><span>' + esc(g.name) + ' · ' + (g.members || []).length + ' members</span><button type="button" id="tcMembersClose" aria-label="Close">&times;</button></div>'
            + tools + '<div class="tc-modal-list">' + rows + '</div>' + addSection
            + '<button type="button" class="tc-leave-btn" id="tcLeaveBtn">Leave group</button>';
    }

    // Inline group-name edit (header pencil) — queries the current #tcGroupName each time.
    function startNameEdit(){
        var nameEl = document.getElementById('tcGroupName'); if (!nameEl || nameEl.dataset.editing) return;
        var nameEdit = document.getElementById('tcGroupNameEdit');
        nameEl.dataset.editing = '1';
        var cur = nameEl.textContent.trim();
        var inp = document.createElement('input');
        inp.type = 'text'; inp.className = 'tc-th-name-input'; inp.maxLength = 80; inp.value = cur;
        nameEl.hidden = true; if (nameEdit) nameEdit.hidden = true;
        nameEl.parentNode.insertBefore(inp, nameEl); inp.focus(); inp.select();
        var done = false;
        function finish(save){
            if (done) return; done = true;
            var nm = inp.value.trim();
            inp.remove(); nameEl.hidden = false; if (nameEdit) nameEdit.hidden = false; delete nameEl.dataset.editing;
            if (save && nm && nm !== cur){
                gpost('/rename', function (fd) { fd.append('name', nm); }).then(function (res) {
                    if (res && res.ok){
                        nameEl.textContent = nm;
                        var rn = document.getElementById('tcRenameName'); if (rn) rn.value = nm;
                        var row = document.querySelector('.tc-contact[data-conversation="' + GID + '"] .tc-c-name'); if (row) row.textContent = nm;
                        toast('Group renamed');
                    } else toast('Could not rename');
                });
            }
        }
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter'){ e.preventDefault(); finish(true); }
            else if (e.key === 'Escape'){ e.preventDefault(); finish(false); }
        });
        inp.addEventListener('blur', function () { finish(true); });
    }

    // Header controls survive rebuilds via delegation on the (stable) thread section.
    var threadHeadSec = box.closest('.tc-thread');
    if (threadHeadSec) threadHeadSec.addEventListener('click', function (e){
        if (e.target.closest('#tcMembersBtn')){ if (GID) membersModal.hidden = false; return; }
        if (e.target.closest('#tcGroupNameEdit')){ startNameEdit(); }
    });

    // Modal actions (close, remove, add, rename, leave) — delegated, read GID dynamically.
    membersModal.addEventListener('click', function (e){
        if (e.target === membersModal || e.target.closest('#tcMembersClose')){ membersModal.hidden = true; return; }
        var rem = e.target.closest('.tc-member-remove');
        if (rem){
            window.tcConfirm('Remove this member from the group?', 'Remove').then(function (ok){ if (!ok) return;
                gpost('/members/' + rem.dataset.admin, function (fd){ fd.append('_method', 'DELETE'); })
                    .then(function (res){ if (res && res.ok) location.reload(); else toast('Could not remove — try again'); });
            });
            return;
        }
        if (e.target.closest('#tcAddMembersBtn')){
            var ids = Array.prototype.map.call(membersModal.querySelectorAll('.tc-member-add-list input:checked'), function (c){ return c.value; });
            if (!ids.length){ toast('Select teammates to add'); return; }
            gpost('/members', function (fd){ ids.forEach(function (i){ fd.append('members[]', i); }); })
                .then(function (res){ if (res && res.ok) location.reload(); else toast('Could not add members — try again'); });
            return;
        }
        if (e.target.closest('#tcRenameBtn')){
            var rn = document.getElementById('tcRenameName'); var nm = rn ? rn.value.trim() : '';
            if (!nm){ toast('Name required'); return; }
            gpost('/rename', function (fd){ fd.append('name', nm); }).then(function (res){ if (res && res.ok) location.reload(); else toast('Could not rename — try again'); });
            return;
        }
        if (e.target.closest('#tcLeaveBtn')){
            window.tcConfirm('Leave this group?', 'Leave').then(function (ok){ if (!ok) return;
                gpost('/leave').then(function (res){ if (res && res.ok) location.href = @js(route('admin.team-messages.index')); else toast('Could not leave — try again'); });
            });
        }
    });

    // ============================================================
    // Tier 1 — open a chat WITHOUT a full page reload (in-app swap).
    // Scope: DM ↔ DM ↔ self ↔ GROUP — all handled in place. The header identity
    // block is rebuilt for the target type and the group members panel is rendered
    // from the payload; only a fetch error falls back to the reload-free tcNav.
    // ============================================================
    var isSelf = @js($isSelf ?? false);
    var OPEN_URL = @js(route('admin.team-messages.open'));

    // Every conversation (DM, self, group) can now be swapped in place.
    function swapEligible(targetIsGroup){ return true; }

    function renderThreadMessages(list, hasMore){
        box.innerHTML = '';
        olderPill = null;
        if (hasMore){
            olderPill = document.createElement('div');
            olderPill.className = 'tc-load-older'; olderPill.id = 'tcLoadOlder';
            olderPill.innerHTML = '<button type="button">Load earlier messages</button>';
            box.appendChild(olderPill);
        }
        if (!list || !list.length){
            var empty = document.createElement('div');
            empty.className = 'tc-thread-empty';
            empty.innerHTML = IS_GROUP
                ? '<div class="tc-thread-empty-emoji">🎉</div><p>Group created — say hello to the team!</p>'
                : '<div class="tc-thread-empty-emoji">👋</div><p>No messages yet — say hello!</p>';
            box.appendChild(empty);
            firstId = 0; lastId = 0; hasOlder = false; return;
        }
        var prevDay = null;
        list.forEach(function (m){
            if (m.dayKey && m.dayKey !== prevDay){ box.appendChild(makeDaysep(m.dayKey, m.day)); prevDay = m.dayKey; }
            box.appendChild(buildRow(m));
        });
        firstId = parseInt(list[0].id, 10) || 0;
        lastId  = parseInt(list[list.length - 1].id, 10) || 0;
        hasOlder = !!hasMore;
    }

    function renderPins(pins){
        var head = document.querySelector('.tc-thread-head');
        var old = document.getElementById('tcPinned'); if (old) old.remove();
        if (!pins || !pins.length || !head) return;
        var latest = pins[0];
        var items = pins.map(function (p){
            return '<div class="tc-pinned-item" data-goto="' + p.id + '"><span class="tc-pinned-text">' + esc(p.text) + '</span>'
                + '<button type="button" class="tc-pinned-x" data-unpin="' + p.id + '" title="Unpin">&times;</button></div>';
        }).join('');
        var bar = document.createElement('div'); bar.className = 'tc-pinned'; bar.id = 'tcPinned';
        bar.innerHTML = '<button type="button" class="tc-pinned-head" id="tcPinnedHead">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>'
            + '<span class="tc-pinned-latest">' + esc(latest.text) + '</span>'
            + (pins.length > 1 ? '<span class="tc-pinned-count">' + pins.length + '</span>' : '')
            + '</button><div class="tc-pinned-drop" id="tcPinnedDrop" hidden>' + items + '</div>';
        head.parentNode.insertBefore(bar, head.nextSibling);
    }
    // Delegated pinned interactions on the (stable) thread section survive a rebuilt bar.
    var threadSection = box.closest('.tc-thread');
    if (threadSection) threadSection.addEventListener('click', function (e){
        var hd = e.target.closest('#tcPinnedHead');
        if (hd){ var dp = document.getElementById('tcPinnedDrop'); if (dp) dp.hidden = !dp.hidden; return; }
        var un = e.target.closest('[data-unpin]');
        if (un){ e.stopPropagation(); postJson(BASE + '/pin', { message_id: un.dataset.unpin, pinned: 0 }).then(function (res){ if (res && res.ok){ var it = un.closest('.tc-pinned-item'); if (it) it.remove(); } else toast('Could not unpin — try again'); }); return; }
        var go = e.target.closest('.tc-pinned-drop [data-goto]');
        if (go){ var t = box.querySelector('.tc-msg[data-id="' + go.dataset.goto + '"]'); if (t){ t.scrollIntoView({ behavior:'smooth', block:'center' }); t.classList.add('tc-flash'); setTimeout(function(){ t.classList.remove('tc-flash'); }, 1300); } var dp2 = document.getElementById('tcPinnedDrop'); if (dp2) dp2.hidden = true; }
    });
    // Swap-created "Load earlier" pills work via delegation (guarded loadOlder is idempotent).
    box.addEventListener('click', function (e){ if (e.target.closest('#tcLoadOlder button')) loadOlder(); });

    var EDIT_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>';
    var MEMBERS_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';

    // Rebuild the header's IDENTITY block (everything before the Chat/Files/Photos tabs),
    // for a DM/self OR a group. The shared tabs/search/bell after it stay bound.
    function headerIdentity(data){
        if (data.isGroup){
            return '<span class="tc-avatar sm tc-avatar--group">' + esc(data.groupIcon || '💬') + '</span>'
                + '<div class="tc-th-info"><div class="tc-th-nameline">'
                + '<span class="tc-th-name" id="tcGroupName">' + esc(data.title) + '</span>'
                + '<button type="button" class="tc-th-edit" id="tcGroupNameEdit" title="Edit group name" aria-label="Edit group name">' + EDIT_SVG + '</button>'
                + '</div><div class="tc-th-role">' + (data.membersCount || 0) + ' members<span id="tcOnlineCount">' + ((data.onlineCount || 0) > 0 ? ' · ' + data.onlineCount + ' online' : '') + '</span></div></div>'
                + '<button type="button" class="tc-th-btn" id="tcMembersBtn" title="Members">' + MEMBERS_SVG + '<span>' + (data.membersCount || 0) + '</span></button>';
        }
        var av = data.avatar
            ? '<span class="tc-avatar sm has-img"><img src="' + data.avatar + '" alt=""></span>'
            : '<span class="tc-avatar sm" style="background:' + (data.color || '#64748b') + '">' + esc(data.mono || '?') + '</span>';
        var dot = data.isSelf ? '' : '<i class="tc-dot ' + (data.online ? 'on' : '') + '" id="tcHeaderDot"></i>';
        return '<span class="tc-av">' + av + dot + '</span>'
            + '<div class="tc-th-info"><div class="tc-th-name">' + esc(data.title) + '</div>'
            + '<div class="tc-th-role tc-presence" id="tcHeaderSeen">' + esc(data.subtitle || '') + '</div></div>';
    }
    function setHeader(data){
        var head = document.querySelector('.tc-thread-head'); if (!head) return;
        var tabs = head.querySelector('.tc-tabs'); if (!tabs) return;   // no tabs → unexpected layout, bail
        while (head.firstChild && head.firstChild !== tabs) head.removeChild(head.firstChild);
        var tmp = document.createElement('div'); tmp.innerHTML = headerIdentity(data);
        while (tmp.firstChild) head.insertBefore(tmp.firstChild, tabs);
        var bell = document.getElementById('tcBell'); if (bell) bell.dataset.level = data.notifyLevel || 'all';
    }

    function updateComposerTarget(data){
        var conv = form.querySelector('input[name="conversation_id"]');
        var rcpt = form.querySelector('input[name="recipient_id"]');
        if (data.conversation_id){
            if (rcpt) rcpt.remove();
            if (!conv){ conv = document.createElement('input'); conv.type = 'hidden'; conv.name = 'conversation_id'; form.appendChild(conv); }
            conv.value = data.conversation_id;
        } else {
            if (conv) conv.remove();
            if (!rcpt){ rcpt = document.createElement('input'); rcpt.type = 'hidden'; rcpt.name = 'recipient_id'; form.appendChild(rcpt); }
            rcpt.value = data.peer_id || '';
        }
        input.placeholder = data.placeholder || 'Message…';
    }

    function markActiveRow(){
        document.querySelectorAll('.tc-contact.active').forEach(function (r){ r.classList.remove('active'); });
        var row = CONV ? document.querySelector('.tc-contact[data-conversation="' + CONV + '"]')
                       : (PEER_ID ? document.querySelector('.tc-contact[data-peer="' + PEER_ID + '"]') : null);
        if (row){
            row.classList.add('active');
            // Opening marks it read — clear its unread/mention affordances immediately.
            var b = row.querySelector('[data-badge]'); if (b) b.remove();
            row.dataset.unread = '0';
            row.querySelectorAll('.tc-c-preview, .tc-c-time').forEach(function (el){ el.classList.remove('unread'); });
            var mb = row.querySelector('.tc-mention-badge'); if (mb) mb.remove();
        }
    }

    // Apply an open() payload to the open thread panel, in place (no reload).
    function applyOpen(data, url, push){
        CONV = data.conversation_id ? String(data.conversation_id) : '';
        PEER_ID = data.peer_id ? String(data.peer_id) : '';
        IS_GROUP = !!data.isGroup;
        PEER_NAME = (data.isSelf || data.isGroup) ? '' : (data.title || '');
        isSelf = !!data.isSelf;
        WATERMARKS = data.watermarks || {};
        MENTIONABLES = data.mentionables || [];
        statesSince = '';
        box.dataset.conversation = CONV;
        if (PEER_ID) box.dataset.peer = PEER_ID; else box.removeAttribute('data-peer');
        box.dataset.group = IS_GROUP ? '1' : '0';

        setHeader(data);
        // Group members panel: render it from the payload; hide it for DMs/self.
        if (IS_GROUP && data.group && typeof renderMembersModal === 'function') renderMembersModal(data.group);
        else if (typeof membersModal !== 'undefined' && membersModal) membersModal.hidden = true;
        updateComposerTarget(data);
        cancelReply(); if (typeof cancelEdit === 'function') cancelEdit();
        if (typeof clearPending === 'function') clearPending();
        pendingMentions = [];

        renderThreadMessages(data.messages || [], data.hasMore);
        renderPins(data.pinned || []);
        applyReadReceipts(data.readUpTo);
        updateSeen();
        toBottom();

        if (typeof switchTab === 'function') switchTab('chat');
        galleryData = null;   // per-conversation; re-fetched when Files/Photos is opened

        markActiveRow();
        if (window.ApexRealtime && CONV){
            var looking = !document.hidden && document.hasFocus();
            window.ApexRealtime.setActive(CONV, looking);
            if (looking) window.ApexRealtime.markConversationRead(CONV);
        }
        if (CONV) window.dispatchEvent(new CustomEvent('apex:conv-adopted', { detail: { conv: CONV, peer: PEER_ID } }));
        if (push && url && window.history && history.pushState){ try { history.pushState({ tcUrl: url }, '', url); } catch (e) {} }
        input.focus();
    }

    // Expose for the sidebar click interceptor (and later the prefetch/cache tiers).
    window.tcApplyOpen   = applyOpen;
    window.tcSwapEligible = swapEligible;
    window.tcOpenUrl     = OPEN_URL;
})();

// Tier 1 — open DMs / self-notes with a LIGHT in-place swap (JSON, no script re-run),
// taking over from the heavier full-HTML tcNav for the common 1:1 case. Groups and any
// error fall through to tcNav (still reload-free), never a hard page reload.
// Tier 2 — prefetch the /open payload on hover/idle so the click is already loaded.
(function () {
    var contacts = document.getElementById('tcContacts');
    if (!contacts) return;

    function toTcNav(url){ if (window.tcNav) window.tcNav(url, true); else location.href = url; }

    // The /open query for a sidebar row, or null if this row can't be swapped in place.
    function rowQuery(a){
        if (!a) return null;
        if (a.dataset.self === '1') return 'self=1';
        if (a.dataset.conversation) return 'c=' + encodeURIComponent(a.dataset.conversation);   // DM or group
        if (a.dataset.peer) return 'with=' + encodeURIComponent(a.dataset.peer);
        return null;
    }

    // ---- Tier 2 prefetch cache: q -> { data, ts } (short TTL; a real open reconciles via poll) ----
    var PREFETCH_TTL = 25000;
    var cache = {};
    function fresh(q){ var c = cache[q]; return c && (Date.now() - c.ts) < PREFETCH_TTL ? c.data : null; }
    function prefetch(a){
        if (!window.tcOpenUrl) return;
        var q = rowQuery(a); if (!q) return;
        if (fresh(q)) return;                       // already warm
        if (a.classList.contains('active')) return; // no point warming the open chat
        cache[q] = cache[q] || { ts: 0 };           // de-dupe concurrent hovers
        if (cache[q].pending) return; cache[q].pending = true;
        // prefetch=1 tells the server NOT to mark the thread read (hovering must not mark read).
        fetch(window.tcOpenUrl + '?' + q + '&prefetch=1&_=' + Date.now(),
            { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r){ return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data){ persist(q, data); })
            .catch(function (){ delete cache[q]; });
    }

    // ---- Tier 3: persistent on-disk cache (IndexedDB) so opens are instant even on
    // app restart / for chats the idle-warm didn't reach. Best-effort: any failure just
    // falls back to the network path. ----
    var idbP = null;
    function idb(){
        if (idbP) return idbP;
        idbP = new Promise(function (resolve){
            try {
                var rq = indexedDB.open('apexChat', 1);
                rq.onupgradeneeded = function (){ try { rq.result.createObjectStore('threads'); } catch (e) {} };
                rq.onsuccess = function (){ resolve(rq.result); };
                rq.onerror = function (){ resolve(null); };
            } catch (e){ resolve(null); }
        });
        return idbP;
    }
    function idbGet(key){
        return idb().then(function (db){ if (!db) return null; return new Promise(function (res){
            try { var t = db.transaction('threads','readonly').objectStore('threads').get(key);
                t.onsuccess = function (){ res(t.result || null); }; t.onerror = function (){ res(null); };
            } catch (e){ res(null); } }); });
    }
    function idbSet(key, val){
        idb().then(function (db){ if (!db) return; try {
            db.transaction('threads','readwrite').objectStore('threads').put(val, key);
        } catch (e){} });
    }
    function sig(d){ if (!d) return ''; var ms = d.messages || []; return [d.conversation_id, ms.length, ms.length?ms[ms.length-1].id:0, d.readUpTo, (d.pinned||[]).length, d.subtitle, d.title].join('|'); }

    function persist(q, data){ cache[q] = { data: data, ts: Date.now() }; idbSet(q, { data: data, ts: Date.now() }); }

    function applyData(data, url){
        window.tcApplyOpen(data, url, true);
        // A cached/real open still needs the server to mark it read + surface anything newer:
        // force one thread poll (it calls markRead and appends any messages past the cache).
        window.dispatchEvent(new CustomEvent('apex:thread-poll'));
    }

    function fetchFresh(q, url, onData){
        return fetch(window.tcOpenUrl + '?' + q + '&_=' + Date.now(),
            { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
            .then(function (r){ return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data){ persist(q, data); onData(data); });
    }

    function openInPlace(a){
        var q = rowQuery(a); if (!q) return false;
        var url = a.getAttribute('href');

        var warm = fresh(q);
        if (warm){ delete cache[q]; applyData(warm, url); persist(q, warm); return true; }   // in-memory (Tier 2)

        // Tier 3: paint from the on-disk snapshot immediately, then refresh in the background.
        idbGet(q).then(function (snap){
            if (snap && snap.data){
                var before = sig(snap.data);
                window.tcApplyOpen(snap.data, url, true);
                // Real refresh: marks read + authoritative header/pins/messages; re-apply only if changed.
                fetchFresh(q, url, function (fresh2){ if (sig(fresh2) !== before) window.tcApplyOpen(fresh2, url, true); })
                    .catch(function (){ window.dispatchEvent(new CustomEvent('apex:thread-poll')); });
            } else {
                // No snapshot: straight network open (Tier 1).
                fetchFresh(q, url, function (data){ window.tcApplyOpen(data, url, true); })
                    .catch(function (){ toTcNav(url); });
            }
        });
        return true;
    }

    // Warm on hover (desktop) and on touchstart (mobile), only for swap-eligible rows.
    var hoverT = null;
    contacts.addEventListener('mouseover', function (e){
        if (!window.tcApplyOpen) return;
        var a = e.target.closest('a.tc-contact'); if (!a || !window.tcSwapEligible(false)) return;
        clearTimeout(hoverT); hoverT = setTimeout(function (){ prefetch(a); }, 90);
    });
    contacts.addEventListener('touchstart', function (e){
        if (!window.tcApplyOpen) return;
        var a = e.target.closest('a.tc-contact'); if (a && window.tcSwapEligible(false)) prefetch(a);
    }, { passive: true });

    // Idle-warm the first few visible DM rows so the very first click is instant too.
    function idleWarm(){
        if (!window.tcApplyOpen || !window.tcSwapEligible(false)) return;
        var rows = contacts.querySelectorAll('a.tc-contact:not(.active)');
        var n = 0;
        for (var i = 0; i < rows.length && n < 5; i++){ if (rowQuery(rows[i])){ prefetch(rows[i]); n++; } }
    }
    if (window.requestIdleCallback) requestIdleCallback(idleWarm, { timeout: 2000 }); else setTimeout(idleWarm, 1200);

    contacts.addEventListener('click', function (e){
        // Only when the in-place machinery is present (a thread is open) and eligible.
        if (!window.tcApplyOpen || !window.tcOpenUrl) return;   // no thread yet → let tcNav open it
        if (e.defaultPrevented) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;   // let "open in new tab" work
        if (e.target.closest('.tc-row-actions')) return;   // fav/mute buttons keep working
        var a = e.target.closest('a.tc-contact'); if (!a) return;
        var targetIsGroup = a.dataset.group === '1';
        if (!window.tcSwapEligible(targetIsGroup)) return;   // groups (either side) → let tcNav handle (reload-free)
        // Handle it here AND stop the event so the heavier tcNav doesn't also run.
        e.preventDefault();
        e.stopPropagation();
        if (a.classList.contains('active')) return;   // already open
        openInPlace(a);
    });
    // Back/forward is handled by tcNav's own popstate (reload-free) — no handler needed here.
})();

// New-group modal — lives outside the thread scope so it works with no chat open.
// Refresh button — reload the app fresh (clears the local thread cache) WITHOUT signing out.
(function () {
    var btn = document.getElementById('tcRefresh'); if (!btn) return;
    btn.addEventListener('click', function () {
        if (btn.dataset.busy) return; btn.dataset.busy = '1';
        btn.classList.add('spinning');
        var done = false;
        function reload(){ if (done) return; done = true; location.reload(); }
        // Clear the on-disk thread cache (Tier 3) + any Cache Storage, then reload.
        var jobs = [];
        try { jobs.push(new Promise(function (res){ var r = indexedDB.deleteDatabase('apexChat'); r.onsuccess = r.onerror = r.onblocked = function(){ res(); }; })); } catch (e) {}
        try { if (window.caches && caches.keys) jobs.push(caches.keys().then(function (ks){ return Promise.all(ks.map(function (k){ return caches.delete(k); })); })); } catch (e) {}
        Promise.all(jobs).then(reload).catch(reload);
        setTimeout(reload, 1200);   // never hang if a clear is slow/blocked
    });
})();

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
