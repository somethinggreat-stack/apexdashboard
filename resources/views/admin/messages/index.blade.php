@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Messages')
@section('subtitle', 'Conversation with ' . $client->business_name)

@push('head')
<style>
.chat-shell { display:flex; flex-direction:column; height:calc(100vh - 220px); min-height:480px; background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; position:relative; }
.chat-header { padding:12px 18px; border-bottom:1px solid var(--border); background:var(--surface-2); display:flex; align-items:center; justify-content:space-between; gap:12px; }
.chat-header-text strong { display:block; font-size:14px; }
.chat-header-text span { color:var(--muted); font-size:12px; }

/* Selection toolbar */
.chat-select-toolbar { display:none; align-items:center; gap:10px; }
.chat-shell.selecting .chat-select-toolbar { display:flex; }
.chat-shell.selecting .chat-header-text { display:none; }
.chat-select-count { font-size:13px; color:var(--text); font-weight:500; }
.chat-select-toolbar button { background:var(--surface); border:1px solid var(--muted); border-radius:6px; padding:6px 12px; font-size:12px; cursor:pointer; color:var(--text); }
.chat-select-toolbar button:hover { background:var(--surface-2); }
.chat-select-toolbar button.danger { color:#dc2626; border-color:#fecaca; }

/* Pinned strip */
.chat-pinned-strip { padding:8px 18px; background:#fefce8; border-bottom:1px solid #fde68a; font-size:12px; color:#854d0e; display:none; }
.chat-shell.has-pinned .chat-pinned-strip { display:block; }
.chat-pinned-strip strong { font-weight:600; }
.chat-pinned-strip a { color:#854d0e; text-decoration:underline; }

.chat-scroll { flex:1; overflow-y:auto; padding:18px; background:var(--surface-2); }

/* Bubble layout */
.chat-msg { margin-bottom:14px; max-width:75%; position:relative; }
.chat-msg.from-admin { margin-left:auto; }
.chat-msg.from-client { margin-right:auto; }
.chat-msg.from-system { max-width:100%; text-align:center; margin:14px 0; }
.chat-bubble-wrap { position:relative; display:flex; align-items:flex-start; gap:6px; }
.from-admin .chat-bubble-wrap { flex-direction:row-reverse; }

/* Checkbox in selection mode */
.chat-select-box { display:none; align-self:center; flex-shrink:0; }
.chat-shell.selecting .chat-select-box { display:inline-flex; }
.chat-select-box input[type=checkbox] { width:18px; height:18px; cursor:pointer; }

.chat-bubble { padding:10px 14px; border-radius:12px; font-size:14px; line-height:1.45; word-break:break-word; position:relative; }
.from-admin .chat-bubble { background:#1a6fc4; color:var(--on-accent); border-bottom-right-radius:4px; }
.from-client .chat-bubble { background:var(--surface); color:var(--text); border:1px solid var(--border); border-bottom-left-radius:4px; }
.from-system .chat-bubble { background:#fef3c7; color:#92400e; border:1px solid #fde68a; display:inline-block; font-style:italic; font-size:13px; }

/* Highlight when selected */
.chat-msg.is-selected .chat-bubble { outline:2px solid #1a6fc4; outline-offset:2px; }
.chat-msg.is-flash .chat-bubble { animation: flashPulse 1.4s ease; }
@keyframes flashPulse {
    0%, 100% { box-shadow:0 0 0 0 rgba(26,111,196,0); }
    30% { box-shadow:0 0 0 6px rgba(26,111,196,0.35); }
}

/* Quoted reply preview inside bubble */
.chat-quote {
    border-left:3px solid currentColor;
    padding:6px 8px;
    margin-bottom:6px;
    background:rgba(0,0,0,0.06);
    border-radius:6px;
    font-size:12px;
    opacity:0.85;
    cursor:pointer;
}
.from-admin .chat-quote { background:rgba(255,255,255,0.18); border-left-color:#dbeafe; }
.from-client .chat-quote { background:rgba(15,32,67,0.04); border-left-color:#1a6fc4; }
.chat-quote-sender { font-weight:600; display:block; margin-bottom:2px; }
.chat-quote-body { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }

.chat-meta { font-size:11px; color:var(--muted); margin-top:4px; display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.from-admin .chat-meta { justify-content:flex-end; }

/* WhatsApp-style delivery ticks */
.chat-ticks { display:inline-flex; align-items:center; color:var(--muted); font-size:13px; line-height:1; margin-left:2px; }
.chat-ticks.read { color:#1a6fc4; }
.chat-ticks svg { width:16px; height:11px; }
.chat-ticks .tick { stroke:currentColor; stroke-width:2; fill:none; stroke-linecap:round; stroke-linejoin:round; }
.chat-ticks .tick.second { transform:translateX(-4px); }
.chat-meta .badge { display:inline-flex; align-items:center; gap:3px; padding:1px 6px; border-radius:10px; background:var(--border); color:var(--text-soft); font-weight:500; }
.chat-meta .badge.pinned { background:#fef3c7; color:#92400e; }
.chat-meta .badge.starred { background:#fef9c3; color:#a16207; }
.chat-meta .badge.noted { background:#dbeafe; color:#1e40af; cursor:pointer; }

/* 3-dot menu trigger */
.chat-actions-btn {
    background:rgba(255,255,255,0.85);
    border:1px solid var(--muted);
    border-radius:50%;
    width:24px; height:24px;
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer;
    color:var(--text-soft);
    font-size:14px; line-height:1;
    opacity:0; transition:opacity 0.15s;
    flex-shrink:0;
    align-self:flex-start;
    margin-top:6px;
}
.chat-msg:hover .chat-actions-btn,
.chat-msg.menu-open .chat-actions-btn { opacity:1; }
.chat-shell.selecting .chat-actions-btn { display:none; }

/* Floating menu */
.chat-actions-menu {
    position:absolute;
    z-index:50;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:8px;
    box-shadow:0 8px 24px rgba(15,32,67,0.12);
    padding:6px 0;
    min-width:180px;
    display:none;
}
.chat-actions-menu.open { display:block; }
.chat-actions-menu button {
    width:100%;
    background:none;
    border:none;
    text-align:left;
    padding:9px 14px;
    font:inherit;
    font-size:13px;
    color:var(--text);
    cursor:pointer;
    display:flex; align-items:center; gap:10px;
}
.chat-actions-menu button:hover { background:var(--surface-2); }
.chat-actions-menu button .ico { width:16px; display:inline-block; text-align:center; font-size:14px; }

/* Reply preview above input */
.chat-reply-preview {
    display:none;
    padding:10px 14px;
    background:var(--surface-2);
    border-top:1px solid var(--border);
    align-items:center;
    gap:10px;
}
.chat-reply-preview.open { display:flex; }
.chat-reply-preview-body { flex:1; border-left:3px solid #1a6fc4; padding:4px 10px; font-size:12px; color:var(--text-soft); overflow:hidden; }
.chat-reply-preview-body strong { display:block; color:#1a6fc4; font-size:11px; margin-bottom:2px; }
.chat-reply-preview-body em { font-style:normal; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block; }
.chat-reply-preview-close { background:none; border:none; font-size:18px; cursor:pointer; color:var(--muted); padding:4px 8px; }
.chat-reply-preview-close:hover { color:#dc2626; }

.chat-form { display:flex; gap:8px; padding:12px; background:var(--surface); border-top:1px solid var(--border); }
.chat-form textarea { flex:1; resize:none; padding:10px; border:1px solid var(--muted); border-radius:6px; font:inherit; }
.chat-empty { color:var(--muted); text-align:center; margin-top:60px; font-size:14px; }

/* Note modal */
.note-modal-overlay {
    position:fixed; inset:0; background:rgba(15,32,67,0.5); z-index:200;
    display:none; align-items:center; justify-content:center; padding:20px;
}
.note-modal-overlay.open { display:flex; }
.note-modal-card { background:var(--surface); border-radius:10px; width:100%; max-width:480px; padding:24px; box-shadow:0 20px 60px rgba(15,32,67,0.25); }
.note-modal-card h3 { margin:0 0 6px; font-size:18px; }
.note-modal-card .muted { color:var(--muted); font-size:12px; margin-bottom:14px; display:block; }
.note-modal-card .quoted {
    background:var(--surface-2); border-left:3px solid #1a6fc4; padding:6px 10px;
    border-radius:6px; font-size:12px; margin-bottom:14px; color:var(--text-soft);
    max-height:80px; overflow:hidden;
}
.note-modal-card textarea { width:100%; min-height:120px; padding:10px; border:1px solid var(--muted); border-radius:6px; font:inherit; resize:vertical; }
.note-modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:14px; }
.note-modal-actions button { padding:8px 16px; border-radius:6px; font:inherit; font-size:13px; cursor:pointer; border:1px solid transparent; }
.note-modal-actions .cancel { background:var(--surface); border-color:var(--muted); color:var(--text-soft); }
.note-modal-actions .save { background:#1a6fc4; color:var(--on-accent); }
.note-modal-actions .clear { background:var(--surface); border-color:#fecaca; color:#dc2626; margin-right:auto; }

/* Toast */
.chat-toast {
    position:fixed; bottom:30px; left:50%; transform:translateX(-50%) translateY(20px);
    background:var(--text); color:var(--surface); padding:10px 18px; border-radius:24px;
    font-size:13px; box-shadow:0 8px 24px rgba(15,32,67,0.25);
    opacity:0; transition:opacity 0.25s, transform 0.25s; z-index:300; pointer-events:none;
}
.chat-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
:root[data-theme="dark"] .chat-shell,:root[data-theme="dark"] .chat-header,:root[data-theme="dark"] .chat-scroll,:root[data-theme="dark"] .from-admin .chat-bubble,:root[data-theme="dark"] .chat-form,:root[data-theme="dark"] .chat-form textarea,:root[data-theme="dark"] .note-modal-card,:root[data-theme="dark"] .note-modal-card textarea{background:var(--pro-card);border-color:var(--pro-line);color:var(--pro-text);}
:root[data-theme="dark"] .from-client .chat-bubble{background:rgba(37,99,235,.24);color:#e6eeff;}
:root[data-theme="dark"] .chat-header-text span{color:var(--pro-text);}
:root[data-theme="dark"] .chat-meta,:root[data-theme="dark"] .chat-select-count,:root[data-theme="dark"] .chat-empty,:root[data-theme="dark"] .note-modal-card .muted{color:var(--pro-muted);}
</style>
@endpush

@section('content')
<div class="chat-shell" id="chatShell" data-messages-url="{{ route('admin.messages.index') }}">
    <div class="chat-header">
        <div class="chat-header-text">
            <strong>Conversation with {{ $client->business_name }}</strong>
            <span>{{ $client->email }}</span>
        </div>
        <div class="chat-select-toolbar" id="selectToolbar">
            <span class="chat-select-count" id="selectCount">0 selected</span>
            <button type="button" data-bulk="copy">Copy</button>
            <button type="button" data-bulk="star">Star</button>
            <button type="button" data-bulk="cancel">Cancel</button>
        </div>
    </div>

    @php
        $pinnedMessages = $messages->whereNotNull('pinned_at')->sortByDesc('pinned_at')->values();
    @endphp
    <div class="chat-pinned-strip" id="pinnedStrip">
        <strong>📌 Pinned ({{ $pinnedMessages->count() }})</strong>
        @foreach ($pinnedMessages as $pm)
            &middot; <a href="#msg-{{ $pm->id }}" data-jump="{{ $pm->id }}">{{ \Illuminate\Support\Str::limit($pm->body, 60) }}</a>
        @endforeach
    </div>

    <div class="chat-scroll" id="chatScroll" data-chat-scroll>
        @forelse ($messages as $msg)
            @php
                $cls = $msg->isFromAdmin() ? 'from-admin' : ($msg->isFromClient() ? 'from-client' : 'from-system');
                $who = $msg->isFromAdmin() ? 'You' : ($msg->isFromClient() ? $client->business_name : 'System');
                $parent = $msg->replyTo;
                $parentWho = $parent ? ($parent->isFromAdmin() ? 'You' : ($parent->isFromClient() ? $client->business_name : 'System')) : null;
            @endphp
            <div class="chat-msg {{ $cls }}"
                 id="msg-{{ $msg->id }}"
                 data-id="{{ $msg->id }}"
                 data-body="{{ $msg->body }}"
                 data-sender="{{ $who }}"
                 data-system="{{ $msg->isSystem() ? '1' : '0' }}">
                <div class="chat-bubble-wrap">
                    <label class="chat-select-box">
                        <input type="checkbox" data-select="{{ $msg->id }}">
                    </label>
                    <div class="chat-bubble">
                        @if ($parent)
                            <div class="chat-quote" data-jump="{{ $parent->id }}">
                                <span class="chat-quote-sender">{{ $parentWho }}</span>
                                <span class="chat-quote-body">{{ \Illuminate\Support\Str::limit($parent->body, 90) }}</span>
                            </div>
                        @endif
                        {{ $msg->body }}
                    </div>
                    @unless ($msg->isSystem())
                        <button type="button" class="chat-actions-btn" aria-label="Message actions" data-actions="{{ $msg->id }}">&vellip;</button>
                    @endunless
                </div>
                <div class="chat-meta">
                    {{ $who }} &middot; {{ $msg->created_at->diffForHumans() }}
                    @if ($msg->isFromAdmin())
                        @php $isRead = (bool) $msg->client_read_at; @endphp
                        <span class="chat-ticks {{ $isRead ? 'read' : '' }}"
                              title="{{ $isRead ? 'Read ' . $msg->client_read_at->diffForHumans() : 'Delivered' }}"
                              aria-label="{{ $isRead ? 'Read' : 'Delivered' }}">
                            <svg viewBox="0 0 18 11" xmlns="http://www.w3.org/2000/svg">
                                <polyline class="tick" points="1,6 4.5,9.5 10,1"/>
                                <polyline class="tick second" points="8,6 11.5,9.5 17,1"/>
                            </svg>
                        </span>
                    @endif
                    @if ($msg->pinned_at) <span class="badge pinned" title="Pinned">📌 Pinned</span> @endif
                    @if ($msg->starred_at) <span class="badge starred" title="Starred">★ Starred</span> @endif
                    @if ($msg->note) <span class="badge noted" title="Has comment" data-open-note="{{ $msg->id }}">📝 Comment added</span> @endif
                </div>
            </div>
        @empty
            <div class="chat-empty">No messages yet. Send the first one.</div>
        @endforelse
    </div>

    <div class="chat-reply-preview" id="replyPreview">
        <div class="chat-reply-preview-body">
            <strong id="replyPreviewSender"></strong>
            <em id="replyPreviewBody"></em>
        </div>
        <button type="button" class="chat-reply-preview-close" id="replyPreviewClose" aria-label="Cancel reply">&times;</button>
    </div>

    <form method="POST" action="{{ route('admin.messages.store') }}" class="chat-form" id="chatForm">
        @csrf
        <input type="hidden" name="reply_to_id" id="replyToInput" value="">
        <textarea name="body" rows="2" maxlength="5000" placeholder="Type a message&hellip;" required></textarea>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</div>

{{-- Floating actions menu (single instance, repositioned per message) --}}
<div class="chat-actions-menu" id="actionsMenu" role="menu">
    <button type="button" data-action="reply"><span class="ico">↩</span> Reply</button>
    <button type="button" data-action="copy"><span class="ico">📋</span> Copy</button>
    <button type="button" data-action="pin"><span class="ico">📌</span> <span data-label="pin">Pin</span></button>
    <button type="button" data-action="star"><span class="ico">★</span> <span data-label="star">Star</span></button>
    <button type="button" data-action="note"><span class="ico">📝</span> <span data-label="note">Add text to note</span></button>
    <button type="button" data-action="select"><span class="ico">☑</span> Select</button>
</div>

{{-- Note modal --}}
<div class="note-modal-overlay" id="noteModal" role="dialog" aria-modal="true">
    <div class="note-modal-card">
        <h3>Comment on message</h3>
        <span class="muted">Comments are stored against the specific message and visible the next time you open it.</span>
        <div class="quoted" id="noteModalQuote"></div>
        <textarea id="noteModalText" placeholder="Write a note about this message&hellip;"></textarea>
        <div class="note-modal-actions">
            <button type="button" class="clear" id="noteModalClear">Clear note</button>
            <button type="button" class="cancel" id="noteModalCancel">Cancel</button>
            <button type="button" class="save" id="noteModalSave">Save note</button>
        </div>
    </div>
</div>

<div class="chat-toast" id="chatToast">Copied</div>

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const shell = document.getElementById('chatShell');
    const scroll = document.getElementById('chatScroll');
    const menu = document.getElementById('actionsMenu');
    const replyPreview = document.getElementById('replyPreview');
    const replyPreviewSender = document.getElementById('replyPreviewSender');
    const replyPreviewBody = document.getElementById('replyPreviewBody');
    const replyPreviewClose = document.getElementById('replyPreviewClose');
    const replyInput = document.getElementById('replyToInput');
    const chatForm = document.getElementById('chatForm');
    const toast = document.getElementById('chatToast');
    const noteModal = document.getElementById('noteModal');
    const noteModalQuote = document.getElementById('noteModalQuote');
    const noteModalText = document.getElementById('noteModalText');
    const noteModalSave = document.getElementById('noteModalSave');
    const noteModalCancel = document.getElementById('noteModalCancel');
    const noteModalClear = document.getElementById('noteModalClear');
    const selectToolbar = document.getElementById('selectToolbar');
    const selectCount = document.getElementById('selectCount');
    const pinnedStrip = document.getElementById('pinnedStrip');

    let activeMessageId = null;
    let activeMenuTrigger = null;
    let activeNoteId = null;
    let selected = new Set();

    if (pinnedStrip && pinnedStrip.querySelector('a')) {
        shell.classList.add('has-pinned');
    }

    // ============ TOAST ============
    function showToast(text) {
        toast.textContent = text;
        toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => toast.classList.remove('show'), 1800);
    }

    // ============ MENU OPEN / CLOSE ============
    function openMenu(messageId, triggerBtn) {
        activeMessageId = messageId;
        activeMenuTrigger = triggerBtn;
        const msgEl = document.getElementById('msg-' + messageId);
        if (!msgEl) return;

        // Toggle Pin/Star/Note labels based on current state
        const pinned = msgEl.querySelector('.badge.pinned');
        const starred = msgEl.querySelector('.badge.starred');
        const hasNote = msgEl.querySelector('.badge.noted');
        menu.querySelector('[data-label="pin"]').textContent = pinned ? 'Unpin' : 'Pin';
        menu.querySelector('[data-label="star"]').textContent = starred ? 'Unstar' : 'Star';
        menu.querySelector('[data-label="note"]').textContent = hasNote ? 'Edit comment' : 'Add text to comment';

        // Position menu near the trigger
        const rect = triggerBtn.getBoundingClientRect();
        menu.classList.add('open');
        // Measure after display
        const menuRect = menu.getBoundingClientRect();
        let top = rect.bottom + window.scrollY + 4;
        let left = rect.left + window.scrollX;
        if (left + menuRect.width > window.innerWidth - 8) {
            left = window.innerWidth - menuRect.width - 8;
        }
        if (top + menuRect.height > window.innerHeight + window.scrollY - 8) {
            top = rect.top + window.scrollY - menuRect.height - 4;
        }
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        msgEl.classList.add('menu-open');
    }

    function closeMenu() {
        menu.classList.remove('open');
        document.querySelectorAll('.chat-msg.menu-open').forEach(el => el.classList.remove('menu-open'));
        activeMessageId = null;
        activeMenuTrigger = null;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-actions]');
        if (btn) {
            e.preventDefault();
            const id = btn.getAttribute('data-actions');
            if (activeMessageId === id) {
                closeMenu();
            } else {
                closeMenu();
                openMenu(id, btn);
            }
            return;
        }
        if (!e.target.closest('#actionsMenu')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
            closeNoteModal();
        }
    });

    // Long-press on mobile opens the menu
    let touchTimer = null;
    scroll.addEventListener('touchstart', function (e) {
        const msgEl = e.target.closest('.chat-msg[data-id]');
        if (!msgEl || msgEl.dataset.system === '1') return;
        clearTimeout(touchTimer);
        touchTimer = setTimeout(() => {
            const trigger = msgEl.querySelector('[data-actions]');
            if (trigger) openMenu(msgEl.dataset.id, trigger);
        }, 450);
    }, { passive: true });
    scroll.addEventListener('touchend', () => clearTimeout(touchTimer));
    scroll.addEventListener('touchmove', () => clearTimeout(touchTimer));

    // Right-click also opens the menu
    scroll.addEventListener('contextmenu', function (e) {
        const msgEl = e.target.closest('.chat-msg[data-id]');
        if (!msgEl || msgEl.dataset.system === '1') return;
        const trigger = msgEl.querySelector('[data-actions]');
        if (trigger) {
            e.preventDefault();
            openMenu(msgEl.dataset.id, trigger);
        }
    });

    // ============ MENU ACTIONS ============
    menu.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-action]');
        if (!btn || !activeMessageId) return;
        const action = btn.getAttribute('data-action');
        const messageId = activeMessageId;
        closeMenu();

        switch (action) {
            case 'reply':  return setReply(messageId);
            case 'copy':   return copyMessage(messageId);
            case 'pin':    return toggleFlag(messageId, 'pin');
            case 'star':   return toggleFlag(messageId, 'star');
            case 'note':   return openNoteModal(messageId);
            case 'select': return enterSelectionMode(messageId);
        }
    });

    // ============ REPLY ============
    function setReply(messageId) {
        const msgEl = document.getElementById('msg-' + messageId);
        if (!msgEl) return;
        replyInput.value = messageId;
        replyPreviewSender.textContent = 'Replying to ' + msgEl.dataset.sender;
        replyPreviewBody.textContent = msgEl.dataset.body;
        replyPreview.classList.add('open');
        chatForm.querySelector('textarea').focus();
    }
    replyPreviewClose.addEventListener('click', clearReply);
    function clearReply() {
        replyInput.value = '';
        replyPreview.classList.remove('open');
    }

    // ============ COPY ============
    function copyMessage(messageId) {
        const msgEl = document.getElementById('msg-' + messageId);
        if (!msgEl) return;
        copyText(msgEl.dataset.body, 'Copied');
    }
    function copyText(text, label) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => showToast(label || 'Copied'));
        } else {
            // Fallback
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed'; ta.style.top = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); showToast(label || 'Copied'); } catch (_) {}
            document.body.removeChild(ta);
        }
    }

    // ============ PIN / STAR ============
    function toggleFlag(messageId, kind) {
        const url = "{{ url('admin/messages') }}/" + messageId + '/' + kind;
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(r => r.ok ? r.json() : Promise.reject(r))
          .then(() => location.reload())
          .catch(() => showToast('Could not update'));
    }

    // ============ NOTE MODAL ============
    function openNoteModal(messageId) {
        const msgEl = document.getElementById('msg-' + messageId);
        if (!msgEl) return;
        activeNoteId = messageId;
        noteModalQuote.textContent = msgEl.dataset.body.substring(0, 240);
        // Pre-fill with existing note if there is one (stored in dataset.note set below)
        noteModalText.value = msgEl.dataset.note || '';
        noteModal.classList.add('open');
        setTimeout(() => noteModalText.focus(), 50);
    }
    function closeNoteModal() {
        noteModal.classList.remove('open');
        activeNoteId = null;
    }
    noteModalCancel.addEventListener('click', closeNoteModal);
    noteModal.addEventListener('click', (e) => { if (e.target === noteModal) closeNoteModal(); });
    noteModalSave.addEventListener('click', () => saveNote(noteModalText.value));
    noteModalClear.addEventListener('click', () => saveNote(''));

    function saveNote(noteText) {
        if (!activeNoteId) return;
        const url = "{{ url('admin/messages') }}/" + activeNoteId + '/note';
        const formData = new FormData();
        formData.append('note', noteText);
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        }).then(r => r.ok ? r.json() : Promise.reject(r))
          .then(() => {
              showToast(noteText ? 'Comment saved' : 'Comment cleared');
              closeNoteModal();
              setTimeout(() => location.reload(), 400);
          })
          .catch(() => showToast('Could not save note'));
    }

    // Hydrate notes into dataset so the modal can pre-fill
    @foreach ($messages as $msg)
        @if ($msg->note)
            (function(){
                const el = document.getElementById('msg-{{ $msg->id }}');
                if (el) el.dataset.note = @json($msg->note);
            })();
        @endif
    @endforeach

    // Open note modal from the badge directly
    document.querySelectorAll('[data-open-note]').forEach(badge => {
        badge.addEventListener('click', (e) => {
            e.stopPropagation();
            openNoteModal(badge.getAttribute('data-open-note'));
        });
    });

    // ============ SELECTION MODE ============
    function enterSelectionMode(initialId) {
        shell.classList.add('selecting');
        selected.clear();
        if (initialId) toggleSelected(initialId, true);
        renderSelection();
    }
    function exitSelectionMode() {
        shell.classList.remove('selecting');
        selected.clear();
        document.querySelectorAll('.chat-msg.is-selected').forEach(el => el.classList.remove('is-selected'));
        document.querySelectorAll('[data-select]').forEach(cb => cb.checked = false);
    }
    function toggleSelected(id, force) {
        const want = (force === undefined) ? !selected.has(id) : force;
        if (want) selected.add(id); else selected.delete(id);
        const msgEl = document.getElementById('msg-' + id);
        if (msgEl) msgEl.classList.toggle('is-selected', want);
        const cb = msgEl?.querySelector('[data-select]');
        if (cb) cb.checked = want;
        renderSelection();
    }
    function renderSelection() {
        selectCount.textContent = selected.size + ' selected';
    }

    scroll.addEventListener('click', function (e) {
        if (!shell.classList.contains('selecting')) return;
        const cb = e.target.closest('[data-select]');
        if (cb) {
            toggleSelected(cb.getAttribute('data-select'), cb.checked);
            return;
        }
        // Clicking on the bubble in selection mode also toggles
        const msgEl = e.target.closest('.chat-msg[data-id]');
        if (msgEl && msgEl.dataset.system !== '1' && !e.target.closest('.chat-quote')) {
            e.preventDefault();
            toggleSelected(msgEl.dataset.id);
        }
    });

    selectToolbar.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-bulk]');
        if (!btn) return;
        const kind = btn.getAttribute('data-bulk');
        if (kind === 'cancel') return exitSelectionMode();
        if (selected.size === 0) { showToast('Nothing selected'); return; }

        if (kind === 'copy') {
            const lines = [];
            selected.forEach(id => {
                const el = document.getElementById('msg-' + id);
                if (el) lines.push(el.dataset.sender + ': ' + el.dataset.body);
            });
            copyText(lines.join('\n\n'), 'Copied ' + selected.size + ' message' + (selected.size === 1 ? '' : 's'));
            exitSelectionMode();
            return;
        }
        if (kind === 'star') {
            const ids = Array.from(selected);
            Promise.all(ids.map(id => fetch("{{ url('admin/messages') }}/" + id + '/star', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })))
            .then(() => { showToast('Updated ' + ids.length + ' message' + (ids.length === 1 ? '' : 's')); location.reload(); })
            .catch(() => showToast('Could not update'));
        }
    });

    // ============ JUMP TO QUOTED MESSAGE ============
    document.addEventListener('click', function (e) {
        const jump = e.target.closest('[data-jump]');
        if (!jump) return;
        const id = jump.getAttribute('data-jump');
        const target = document.getElementById('msg-' + id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('is-flash');
        setTimeout(() => target.classList.remove('is-flash'), 1500);
    });

    // ============ AUTO-SCROLL TO BOTTOM ON LOAD ============
    scroll.scrollTop = scroll.scrollHeight;
})();
</script>
@endpush
@endsection
