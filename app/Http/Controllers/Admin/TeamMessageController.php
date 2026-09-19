<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\TeamMessage;
use App\Services\WebPushSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Team Chat — internal messaging between an org's admins (super + VAs; leads
 * excluded). Everything is a Conversation: a 1:1 ("dm") or a named ("group").
 * Org-scoped by dataOwnerId; never across orgs, never business owners.
 */
class TeamMessageController extends Controller
{
    /**
     * Team Chat runs on Pakistan time, always — the team is in Pakistan, and a chat where the
     * timestamp depends on who is reading it is worse than useless for "when did that land?".
     * This is the ONLY place the chat's timezone is written: every server-rendered stamp goes
     * through it, and it is handed to the page as `tz` so the browser formats the same way
     * instead of following whatever the PC's clock happens to be set to.
     */
    public const TZ = 'Asia/Karachi';

    private const GROUP_ICONS = ['💬', '🚀', '🔥', '⭐', '📁', '🎯', '💼', '📣'];

    // Any file type is accepted for upload. Only these image types are ever served INLINE;
    // everything else is force-downloaded as octet-stream (see attachment()).
    private const IMAGE_MIME = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    private const MAX_NAME = 200;
    private const MAX_KB = 51200;   // 50 MB per file
    private const MAX_FILES = 10;
    private const PAGE = 50;   // messages loaded per page (initial + each "load earlier")
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ---------------------------------------------------------------- pages

    public function index(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $teammates = $this->teammates($me)->orderBy('full_name')->get();
        $convos    = $this->myConversations($me);

        // Map each DM conversation by the other person's id.
        $dmByPeer = [];
        foreach ($convos as $c) {
            if ($c->isDm()) {
                $other = $c->participants->firstWhere('admin_id', '!=', $me->id);
                if ($other) $dmByPeer[$other->admin_id] = $c;
            }
        }

        // Preload the last message of every conversation for previews.
        $lastIds = $convos->pluck('last_message_id')->filter()->all();
        $lastMsgs = $lastIds
            ? TeamMessage::with('sender')->whereIn('id', $lastIds)->get()->keyBy('id')
            : collect();

        $unread = $this->unreadPerConversation($me);
        $mentions = $this->unreadMentionsPerConversation($me);

        // Build the unified sidebar: every teammate as a DM + every group I'm in.
        $items = [];
        foreach ($teammates as $t) {
            $c = $dmByPeer[$t->id] ?? null;
            $items[] = $this->dmItem($me, $t, $c, $lastMsgs, $unread, $mentions);
        }
        foreach ($convos as $c) {
            if ($c->isGroup()) $items[] = $this->groupItem($me, $c, $lastMsgs, $unread, $mentions);
        }

        // Most recent conversation first; never-used DMs fall to the bottom by name.
        usort($items, function ($a, $b) {
            $at = $a['ts']; $bt = $b['ts'];
            if ($at && $bt) return $bt <=> $at;
            if ($at) return -1;
            if ($bt) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        // Favorites float into their own section at the top.
        $favorites = array_values(array_filter($items, fn ($it) => $it['favorite']));
        $chats     = array_values(array_filter($items, fn ($it) => ! $it['favorite']));

        // Resolve the active thread: ?self=1 (notes), ?c=<conversation> or ?with=<teammate>.
        $active = null; $peer = null; $messages = collect(); $members = collect(); $addable = collect();
        $isSelf = false;

        if ($request->boolean('self')) {
            $active = $this->findOrCreateSelf($me);
            $isSelf = true;
        } elseif ($request->filled('c')) {
            $active = $convos->firstWhere('id', (int) $request->query('c'));
            $isSelf = $this->isSelfConv($active);
        } elseif ($request->filled('with')) {
            $peer = $teammates->firstWhere('id', (int) $request->query('with'));
            if ($peer) $active = $dmByPeer[$peer->id] ?? null;   // may be null → virtual DM
        }

        // The private "message yourself" notes thread (existing one, for the sidebar entry).
        $selfConv = $convos->first(fn ($c) => $this->isSelfConv($c));
        $selfLast = $selfConv && $selfConv->last_message_id ? $lastMsgs->get($selfConv->last_message_id) : null;

        $pinned = collect(); $mentionables = []; $notifyLevel = 'all'; $hasMoreOlder = false;

        $focusId = 0;

        if ($active) {
            // Normally the newest page; with ?m=<id> (a search result) the page AROUND that
            // message, so the app can jump straight to it. Older messages come in on demand.
            [$messages, $hasMoreOlder, $focusId] = $this->messagePage($active, $me, (int) $request->query('m'));
            $pinned = $active->messages()->visibleTo($me->id)->whereNull('deleted_at')->whereNotNull('pinned_at')->with('sender')
                ->orderByDesc('pinned_at')->limit(10)->get();
            $this->markRead($active, $me);
            $notifyLevel = optional($active->participantFor($me->id))->notify_level ?: 'all';

            if ($active->isDm()) {
                $peer = $isSelf ? $me : $active->otherAdmin($me->id);
                if ($peer && ! $isSelf) $mentionables[] = ['id' => $peer->id, 'name' => $peer->full_name, 'avatar' => $peer->avatarUrl()];
            } else {
                $members = $active->participants->load('admin');
                $inIds   = $active->participants->pluck('admin_id')->all();
                $addable = $teammates->whereNotIn('id', $inIds)->values();
                foreach ($members as $p) {
                    if ($p->admin_id !== $me->id && $p->admin) {
                        $mentionables[] = ['id' => $p->admin_id, 'name' => $p->admin->full_name, 'avatar' => $p->admin->avatarUrl()];
                    }
                }
            }
        }

        return view($this->adminView('admin.team-messages.index'), [
            'me' => $me, 'favorites' => $favorites, 'chats' => $chats, 'active' => $active, 'peer' => $peer,
            'messages' => $messages, 'members' => $members, 'addable' => $addable, 'pinned' => $pinned, 'hasMoreOlder' => $hasMoreOlder,
            'teammates' => $teammates, 'groupIcons' => self::GROUP_ICONS,
            'readUpTo' => $active ? $this->readUpTo($active, $me->id) : 0,
            'watermarks' => $active ? $this->watermarks($active, $me->id) : [],
            'peerOnline' => $peer ? $peer->isOnline() : false,
            'peerSeen' => $peer ? ($peer->isOnline() ? 'Online' : ($peer->lastSeenHuman() ?? 'Offline')) : null,
            'onlineCount' => $active && $active->isGroup()
                ? $active->participants->filter(fn ($p) => $p->admin_id !== $me->id && optional($p->admin)->isOnline())->count()
                : 0,
            'mentionables' => $mentionables, 'notifyLevel' => $notifyLevel,
            'isSelf' => $isSelf, 'selfConv' => $selfConv, 'selfLast' => $selfLast,
            'tz' => self::TZ,
            'uploadLimits' => self::uploadLimits(),
            'focusId' => $focusId,
        ]);
    }

    // ---------------------------------------------------------------- messages

    public function store(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'recipient_id'    => ['nullable', 'integer'],
            'body'            => ['nullable', 'string', 'max:5000'],
            'reply_to_id'     => ['nullable', 'integer'],
            'attachments'     => ['nullable', 'array'],
            'mentions'        => ['nullable', 'array', 'max:50'],
            'mentions.*'      => ['string', 'max:20'],
            'client_uuid'     => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
        ]);
        $uuid = $data['client_uuid'] ?? null;
        $this->checkAttachments((array) $request->file('attachments', []));

        // A repeat of a send that already went through (double Enter, or a retry after a
        // timeout whose first attempt actually reached us) → return that message, no duplicate.
        if ($uuid && ($existing = $this->sentWithUuid($me, $uuid))) {
            return $this->storedResponse($request, $existing, $me);
        }

        // Resolve the conversation. A brand-new DM is created inside the send transaction below.
        $conv = $peer = null;
        if (! empty($data['conversation_id'])) {
            $conv = $this->findConversation($me, (int) $data['conversation_id']);
        } elseif (! empty($data['recipient_id'])) {
            $peer = $this->teammates($me)->findOrFail($data['recipient_id']);
        } else {
            throw ValidationException::withMessages(['conversation_id' => 'No conversation.']);
        }

        $files = $request->file('attachments', []);
        $body  = trim((string) ($data['body'] ?? ''));

        if ($body === '' && empty($files)) {
            throw ValidationException::withMessages(['body' => 'Type a message or attach a file.']);
        }
        // Any file type is allowed (zip, pdf, images, docs, anything). It's safe because
        // non-images are always force-downloaded as octet-stream from the private disk —
        // see attachment() — so nothing here is ever executed or rendered inline.

        // Files go to disk FIRST (the slow part), so the message, its attachments and its
        // mentions can then be committed together: nobody ever sees a half-saved message.
        $stored = $this->storeFiles($files, $me->dataOwnerId());

        try {
            $msg = $this->serializedSend($me, function () use ($me, $uuid, &$conv, $peer, $body, $data, $stored, $request) {
                // Re-check under the lock: a concurrent duplicate may have just committed.
                if ($uuid && ($dup = $this->sentWithUuid($me, $uuid))) {
                    return $dup;
                }
                $conv ??= $this->findOrCreateDm($me, $peer);

                $replyToId = null;
                if (! empty($data['reply_to_id'])) {
                    $replyToId = $conv->messages()->find($data['reply_to_id'])?->id;
                }

                $msg = TeamMessage::create([
                    'conversation_id' => $conv->id,
                    'type'            => 'text',
                    'sender_id'       => $me->id,
                    'client_uuid'     => $uuid,
                    'reply_to_id'     => $replyToId,
                    'body'            => $body,
                ]);
                if ($stored) {
                    $msg->attachments()->createMany($stored);
                }
                $this->syncMentions($msg, $conv, $me, $request->input('mentions', []));
                $this->touchConversation($conv, $msg);
                $this->markReadTo($conv, $me->id, $msg->id);   // I've read my own message
                $conv->participants()->where('admin_id', $me->id)->update(['typing_at' => null]);

                return $msg;
            });
        } catch (\Throwable $e) {
            $this->discardFiles($stored);
            throw $e;
        }

        if ($uuid && $msg->wasRecentlyCreated === false) {
            $this->discardFiles($stored);   // the duplicate's copies aren't needed
            return $this->storedResponse($request, $msg, $me);
        }

        $conv ??= $msg->conversation;
        $msg->load('sender', 'replyTo.sender', 'attachments', 'mentionedAdmins');

        $this->queuePush($conv, $msg, $me);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $this->present($msg, $me->id), 'conversation_id' => $conv->id]);
        }

        return redirect()->route('admin.team-messages.index', ['c' => $conv->id]);
    }

    /**
     * The real upload limits for one chat message: the smallest of our own caps, PHP's
     * upload_max_filesize / post_max_size / max_file_uploads, and the edge (Cloudflare)
     * request ceiling. The client enforces the same numbers before uploading anything.
     *
     * @return array{file:int, total:int, count:int, php_upload:string, php_post:string}
     */
    public static function uploadLimits(): array
    {
        $bytes = function (string $v): int {
            $v = trim($v);
            if ($v === '' || $v === '-1' || $v === '0') return PHP_INT_MAX;   // unlimited
            $n = (float) $v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024;   // no break
                case 'm': $n *= 1024;   // no break
                case 'k': $n *= 1024;
            }
            return (int) $n;
        };
        $mb = 1024 * 1024;
        $edge = max(1, (int) config('team.chat.max_request_mb', 95)) * $mb;
        $post = $bytes((string) ini_get('post_max_size'));
        // Leave room for the multipart framing and the other form fields.
        $total = min($edge, $post) - (256 * 1024);
        $file = min(self::MAX_KB * 1024, $bytes((string) ini_get('upload_max_filesize')), $total);
        $count = min(self::MAX_FILES, (int) (ini_get('max_file_uploads') ?: self::MAX_FILES));

        return [
            'file' => $file, 'total' => $total, 'count' => $count,
            'php_upload' => (string) ini_get('upload_max_filesize'), 'php_post' => (string) ini_get('post_max_size'),
        ];
    }

    private static function mbLabel(int $bytes): string
    {
        $mb = $bytes / 1048576;

        return ($mb >= 10 ? (string) floor($mb) : rtrim(rtrim(number_format($mb, 1), '0'), '.')) . ' MB';
    }

    /** Reject files the server can't take, with a message that names the file and the limit. */
    private function checkAttachments(array $files): void
    {
        if (! $files) return;
        $lim = self::uploadLimits();

        if (count($files) > $lim['count']) {
            throw ValidationException::withMessages(['attachments' => "Up to {$lim['count']} files per message."]);
        }
        $sum = 0;
        foreach ($files as $f) {
            if (! $f instanceof \Illuminate\Http\UploadedFile) {
                throw ValidationException::withMessages(['attachments' => 'One of the files could not be read — attach it again.']);
            }
            $name = $f->getClientOriginalName() ?: 'A file';
            if (! $f->isValid()) {
                $tooBig = in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                throw ValidationException::withMessages(['attachments' => $tooBig
                    ? "{$name} is larger than " . self::mbLabel($lim['file']) . ', the most one file can be.'
                    : "{$name} didn't upload completely — please try again."]);
            }
            if ($f->getSize() > $lim['file']) {
                throw ValidationException::withMessages(['attachments' => "{$name} is larger than " . self::mbLabel($lim['file']) . ', the most one file can be.']);
            }
            $sum += $f->getSize();
        }
        if ($sum > $lim['total']) {
            throw ValidationException::withMessages(['attachments' => 'These files add up to ' . self::mbLabel($sum)
                . '. One message can carry up to ' . self::mbLabel($lim['total']) . ' — send them in separate messages.']);
        }
    }

    /** A fresh CSRF token for the open chat (lets a send retry after a session refresh, files intact). */
    public function csrf(Request $request)
    {
        return response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    /** Super-admin diagnostic: the upload limits this server actually enforces. */
    public function uploadLimitsReport()
    {
        $lim = self::uploadLimits();

        return response()->json([
            'chat_max_file'        => self::mbLabel($lim['file']),
            'chat_max_per_message' => self::mbLabel($lim['total']),
            'chat_max_files'       => $lim['count'],
            'edge_cap_mb'          => (int) config('team.chat.max_request_mb', 95),
            'php' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
                'max_file_uploads'    => ini_get('max_file_uploads'),
                'max_input_time'      => ini_get('max_input_time'),
                'max_execution_time'  => ini_get('max_execution_time'),
                'memory_limit'        => ini_get('memory_limit'),
                'sapi'                => PHP_SAPI,
                'version'             => PHP_VERSION,
                'user_ini_filename'   => ini_get('user_ini.filename'),
            ],
            'session' => [
                // What production actually runs with. SESSION_LIFETIME is the app-wide value;
                // chat requests keep their own session alive for chat_session_days so an idle
                // VA's next message never fails with "Reconnecting…".
                'app_lifetime_minutes' => (int) config('session.lifetime'),
                'chat_session_days'    => (int) config('team.chat.session_days', 30),
                'driver'               => config('session.driver'),
                'expire_on_close'      => (bool) config('session.expire_on_close'),
            ],
            'server' => request()->server('SERVER_SOFTWARE'),
            'host'   => request()->getHost(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * One page of a conversation: the newest messages, or — when `m` names a message in it —
     * the page around that message so a search result can be opened at the right spot.
     *
     * @return array{0:\Illuminate\Support\Collection,1:bool,2:int} [messages, hasMoreOlder, focusId]
     */
    private function messagePage(Conversation $conv, Admin $me, int $m = 0): array
    {
        $base = fn () => $conv->messages()->visibleTo($me->id)
            ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins');

        $focusId = 0;
        if ($m > 0 && $conv->messages()->visibleTo($me->id)->whereKey($m)->exists()) {
            $focusId = $m;
        }

        if ($focusId) {
            $half   = (int) floor(self::PAGE / 2);
            $before = $base()->where('id', '<=', $focusId)->orderByDesc('id')->limit($half)->get()->reverse()->values();
            $after  = $base()->where('id', '>', $focusId)->orderBy('id')->limit($half)->get();
            $messages = $before->concat($after)->values();
        } else {
            $messages = $base()->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
        }

        $hasMoreOlder = $messages->isNotEmpty()
            && $conv->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();

        return [$messages, $hasMoreOlder, $focusId];
    }

    /** A message I already sent with this client id, if I'm still in its conversation. */
    private function sentWithUuid(Admin $me, string $uuid): ?TeamMessage
    {
        $m = TeamMessage::where('sender_id', $me->id)->where('client_uuid', $uuid)->first();

        return $m && $this->isParticipant($me, $m->conversation_id) ? $m : null;
    }

    /** The normal send response, for a message that was saved by an earlier attempt. */
    private function storedResponse(Request $request, TeamMessage $msg, Admin $me)
    {
        $msg->load('sender', 'replyTo.sender', 'attachments', 'mentionedAdmins', 'deletedByAdmin');

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $this->present($msg, $me->id), 'conversation_id' => $msg->conversation_id, 'duplicate' => true]);
        }

        return redirect()->route('admin.team-messages.index', ['c' => $msg->conversation_id]);
    }

    /**
     * Run a message insert inside a transaction that first locks the org's owner row, so an
     * org's messages are committed in the same order as their ids. The polls read "everything
     * after id N"; if a lower id could commit after a higher one was already seen, that
     * message would be skipped for good (the "missing message in a busy group" bug).
     * Only the quick DB writes run under the lock — files are stored before it.
     */
    private function serializedSend(Admin $me, \Closure $work)
    {
        return DB::transaction(function () use ($me, $work) {
            Admin::whereKey($me->dataOwnerId())->lockForUpdate()->value('id');

            return $work();
        });
    }

    /**
     * Fire a Web Push to every recipient who should be notified (respecting notify_level
     * and mute; @mentions pierce both). Sent AFTER the response so it never slows the send.
     */
    private function queuePush(Conversation $conv, TeamMessage $msg, Admin $me): void
    {
        if (! WebPushSender::enabled()) {
            return;
        }

        $recipients = [];
        foreach ($conv->participants as $p) {
            if ($p->admin_id === $me->id) {
                continue;
            }
            $mention = $msg->mentions_all || $msg->mentionedAdmins->contains('id', $p->admin_id);
            $level   = $p->notify_level ?? 'all';
            $muted   = (bool) ($p->muted ?? false);
            $should  = $mention ? ($level !== 'none') : (! $muted && $level === 'all');
            if ($should) {
                $recipients[] = $p->admin_id;
            }
        }
        if (empty($recipients)) {
            return;
        }

        $snippet = $msg->body !== '' ? Str::limit($msg->body, 80) : 'Sent a file';
        $payload = [
            // For a DM this resolves (per the recipient) to the sender's name; for a group, the group name.
            'title' => $this->convTitle($conv, $recipients[0]),
            'body'  => ($msg->mentions_all ? '@ ' : '') . $this->senderInfo($msg->sender)['first'] . ': ' . $snippet,
            'url'   => route('admin.team-messages.index', ['c' => $conv->id, 'standalone' => 1]),
            'tag'   => 'apex-team-' . $conv->id,
            'conv'  => (string) $conv->id,
        ];

        app()->terminating(function () use ($recipients, $payload) {
            app(WebPushSender::class)->sendToAdmins($recipients, $payload);
        });
    }

    /** Poll: new messages, read watermarks, reaction/deletion state, typing + presence. */
    public function thread(Request $request)
    {
        $me   = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->query('c'));
        $after = (int) $request->query('after', 0);

        $msgs = $conv->messages()->visibleTo($me->id)->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
            ->where('id', '>', $after)->orderBy('id')->get();

        // Only mark read when the client says the VA is actually looking at this window
        // (`read=0` while the app sits in the tray or behind another app). Without this the
        // 3s poll kept marking an open chat read — the sender saw "Seen"/blue ticks for
        // messages nobody had looked at, and the chat never showed an unread badge.
        // Old clients don't send the flag at all; they keep the previous behaviour.
        if ($request->query('read', '1') !== '0') {
            $this->markRead($conv, $me);
        }

        // Reaction/edit/delete "states": on the first poll sync the visible window (latest 80);
        // after that, return only what CHANGED since the client's last poll — so edits/reactions/
        // deletes on ANY message (not just the newest 80) propagate live.
        $statesSince = (string) $request->query('statesSince', '');
        $stateToken  = now()->toDateTimeString();   // captured before the query, round-tripped by the client
        $statesQuery = $conv->messages()->with('deletedByAdmin', 'mentionedAdmins')->withCount('attachments');
        if ($statesSince !== '') {
            $statesQuery->where('updated_at', '>=', $statesSince)->latest('updated_at')->limit(300);
        } else {
            $statesQuery->latest('id')->limit(80);
        }
        $states = $statesQuery->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'deleted' => (bool) $m->deleted_at, 'reactions' => $this->reactionsOf($m, $me->id),
                'deletedBy' => $m->deleted_at ? ($m->deleted_by === $me->id ? 'You' : $this->senderInfo($m->deletedByAdmin)['first']) : null,
                'edited' => ! $m->deleted_at && (bool) $m->edited_at,
                // Carry the current text only for edited messages, so peers see edits live.
                'body' => (! $m->deleted_at && $m->edited_at) ? $m->body : null,
                'mentionLabels' => (! $m->deleted_at && $m->edited_at) ? $this->mentionLabels($m) : [],
                // How many files the message has. A client that drew it without them (it
                // reached an older build mid-save) notices the difference and redraws that one
                // message, instead of showing a file message as text-only until a reload.
                'atts' => (int) ($m->attachments_count ?? 0),
            ])->values();

        $others = $conv->participants->where('admin_id', '!=', $me->id);

        $typing = $others
            ->filter(fn ($p) => $p->typing_at && $p->typing_at->gt(now()->subSeconds(6)))
            ->map(fn ($p) => $this->senderInfo($p->admin)['first'])->values();

        return response()->json([
            'messages'    => $msgs->map(fn ($m) => $this->present($m, $me->id))->values(),
            'readUpTo'    => $this->readUpTo($conv, $me->id),
            'states'      => $states,
            'statesToken' => $stateToken,
            'typing'      => $typing,
            'watermarks'  => $this->watermarks($conv, $me->id),
            'presence'    => $this->presenceOf($others),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Older page: the messages just before `before`, oldest-first, for "load earlier". */
    public function older(Request $request)
    {
        $me   = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->query('c'));
        $before = (int) $request->query('before', 0);
        abort_if($before <= 0, 422);

        $msgs = $conv->messages()->visibleTo($me->id)
            ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
            ->where('id', '<', $before)->orderByDesc('id')->limit(self::PAGE)->get();

        $oldest  = $msgs->min('id');
        $hasMore = $oldest && $conv->messages()->visibleTo($me->id)->where('id', '<', $oldest)->exists();

        return response()->json([
            'messages' => $msgs->reverse()->values()->map(fn ($m) => $this->present($m, $me->id))->values(),
            'hasMore'  => (bool) $hasMore,
            'isGroup'  => $conv->isGroup(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Everything the client needs to OPEN a thread without a full page reload:
     * the newest page + header + composer state, as JSON. Mirrors what index()
     * computes for the active thread, but skips all the sidebar work — so it's
     * ~3x cheaper on the server and avoids the whole document/JS re-init on the
     * client. Accepts ?c=<id>, ?with=<peer>, or ?self=1 (same as index()).
     */
    public function open(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $active = null; $peer = null; $isSelf = false;
        if ($request->boolean('self')) {
            $active = $this->findOrCreateSelf($me);
            $isSelf = true;
            $peer = $me;
        } elseif ($request->filled('c')) {
            $active = $this->findConversation($me, (int) $request->query('c'));
            $isSelf = $this->isSelfConv($active);
            if ($isSelf) $peer = $me;
            elseif ($active->isDm()) $peer = $active->otherAdmin($me->id);
        } elseif ($request->filled('with')) {
            $peer = $this->teammates($me)->where('id', (int) $request->query('with'))->first();
            abort_unless($peer, 404);
            $active = $this->myConversations($me)->first(function ($c) use ($me, $peer) {
                return $c->isDm() && $c->participants->firstWhere('admin_id', $peer->id)
                    && ! $this->isSelfConv($c);
            });
        }
        abort_unless($active || $peer, 404);

        $messages = collect(); $hasMore = false; $pinned = collect(); $focusId = 0;
        $members = collect(); $addable = collect(); $mentionables = []; $notifyLevel = 'all';

        if ($active) {
            // ?m=<id> (a search result) opens the page AROUND that message instead of the newest.
            [$messages, $hasMore, $focusId] = $this->messagePage($active, $me, (int) $request->query('m'));
            $pinned = $active->messages()->visibleTo($me->id)->whereNull('deleted_at')->whereNotNull('pinned_at')->with('sender')
                ->orderByDesc('pinned_at')->limit(10)->get();
            // Prefetch (hover warm-up) must NOT mark the thread read — only a real open does.
            if (! $request->boolean('prefetch')) {
                $this->markRead($active, $me);
            }
            $notifyLevel = optional($active->participantFor($me->id))->notify_level ?: 'all';

            if ($active->isGroup()) {
                $members = $active->participants->load('admin');
                $inIds   = $active->participants->pluck('admin_id')->all();
                $addable = $this->teammates($me)->whereNotIn('id', $inIds)->orderBy('full_name')->get();
                foreach ($members as $p) {
                    if ($p->admin_id !== $me->id && $p->admin) {
                        $mentionables[] = ['id' => $p->admin_id, 'name' => $p->admin->full_name, 'avatar' => $p->admin->avatarUrl()];
                    }
                }
            } elseif ($peer && ! $isSelf) {
                $mentionables[] = ['id' => $peer->id, 'name' => $peer->full_name, 'avatar' => $peer->avatarUrl()];
            }
        }

        $isGroup = (bool) ($active && $active->isGroup());
        $title = $active ? $this->convTitle($active, $me->id) : ($peer->full_name ?? 'Direct message');
        $sInfo = $peer ? $this->senderInfo($peer) : null;

        // Group roster for the members panel (rendered client-side on a light swap).
        // Permissions are still enforced server-side on every add/remove/rename call.
        $groupData = null;
        if ($isGroup) {
            $iAmAdmin = optional($members->firstWhere('admin_id', $me->id))->role === 'admin';
            $groupData = [
                'id'      => $active->id,
                'name'    => $active->name,
                'icon'    => $active->icon ?: '💬',
                'isAdmin' => $iAmAdmin,
                'members' => $members->sortByDesc('role')->map(function ($p) use ($me) {
                    $s = $this->senderInfo($p->admin);
                    return [
                        'id' => $p->admin_id, 'name' => $s['name'], 'avatar' => $s['avatar'],
                        'mono' => $s['mono'], 'color' => $s['color'],
                        'admin' => $p->role === 'admin', 'you' => $p->admin_id === $me->id,
                    ];
                })->values(),
                'addable' => $addable->map(fn ($t) => ['id' => $t->id, 'name' => $t->full_name])->values(),
            ];
        }

        return response()->json([
            'group'      => $groupData,
            'conversation_id' => $active?->id,
            'isGroup'    => $isGroup,
            'isSelf'     => $isSelf,
            'peer_id'    => (! $isGroup && $peer && ! $isSelf) ? $peer->id : null,
            'title'      => $title,
            'avatar'     => $isGroup ? null : ($isSelf ? null : ($sInfo['avatar'] ?? null)),
            'groupIcon'  => $isGroup ? ($active->icon ?: '💬') : null,
            'mono'       => $sInfo['mono'] ?? '?',
            'color'      => $sInfo['color'] ?? '#64748b',
            'subtitle'   => $isSelf ? 'Notes · visible only to you'
                : ($isGroup ? trim($members->count() . ' members' . ($this->groupOnlineCount($active, $me->id) ? ' · ' . $this->groupOnlineCount($active, $me->id) . ' online' : ''))
                    : ($peer ? ($peer->isOnline() ? 'Online' : ($peer->lastSeenHuman() ?? 'Offline')) : '')),
            'online'     => $peer && ! $isSelf ? $peer->isOnline() : false,
            'membersCount' => $isGroup ? $members->count() : 0,
            'onlineCount'  => $isGroup ? $this->groupOnlineCount($active, $me->id) : 0,
            'messages'   => $messages->map(fn ($m) => $this->present($m, $me->id))->values(),
            'hasMore'    => (bool) $hasMore,
            // Expire when the oldest cached message, quote or pin reaches retention.
            // A snapshot's age alone cannot enforce a message's seven-day lifetime.
            'cacheExpiresAt' => $messages->concat($messages->pluck('replyTo')->filter())->concat($pinned)
                ->min(fn ($message) => $message->created_at->copy()->addDays(7)->getTimestampMs())
                ?? now()->addDay()->getTimestampMs(),
            'focusId'    => $focusId,   // a search result: jump to and highlight this message
            'readUpTo'   => $active ? $this->readUpTo($active, $me->id) : 0,
            'watermarks' => $active ? $this->watermarks($active, $me->id) : [],
            'pinned'     => $pinned->map(fn ($m) => [
                'id' => $m->id,
                'author' => $m->sender_id === $me->id ? 'You' : ($this->senderInfo($m->sender)['first']),
                'text' => $m->body !== '' ? \Illuminate\Support\Str::limit($m->body, 70) : '📎 Attachment',
            ])->values(),
            'mentionables' => $mentionables,
            'notifyLevel'  => $notifyLevel,
            'placeholder'  => $isSelf ? 'Write a note to yourself…' : 'Message ' . $title . '…',
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Count of other participants currently online in a group. */
    private function groupOnlineCount(?Conversation $conv, int $meId): int
    {
        if (! $conv || ! $conv->isGroup()) return 0;

        return $conv->participants->filter(fn ($p) => $p->admin_id !== $meId && optional($p->admin)->isOnline())->count();
    }

    /** I'm typing in this conversation — refresh my typing timestamp (poll-driven). */
    public function typing(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->input('conversation_id'));
        $conv->participants()->where('admin_id', $me->id)->update(['typing_at' => now()]);

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    /** Presence for all my teammates — drives the sidebar online dots. */
    public function presence(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $mates = $this->teammates($me)->get();

        return response()->json([
            'presence' => $mates->map(fn ($a) => [
                'id' => $a->id, 'online' => $a->isOnline(),
                'seen' => $a->isOnline() ? 'Online' : ($a->lastSeenHuman() ?? 'Offline'),
            ])->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Other participants' read watermarks (for "Seen by" + blue ticks). */
    private function watermarks(Conversation $conv, int $meId): array
    {
        return $conv->participants->where('admin_id', '!=', $meId)->map(function ($p) {
            $s = $this->senderInfo($p->admin);
            return ['id' => $p->admin_id, 'name' => $s['name'], 'first' => $s['first'],
                    'avatar' => $s['avatar'], 'mono' => $s['mono'], 'color' => $s['color'],
                    'upTo' => (int) $p->last_read_message_id];
        })->values()->all();
    }

    private function presenceOf($participants): array
    {
        return $participants->map(fn ($p) => [
            'id' => $p->admin_id,
            'online' => optional($p->admin)->isOnline() ?? false,
            'seen' => optional($p->admin)->isOnline() ? 'Online' : (optional($p->admin)->lastSeenHuman() ?? 'Offline'),
        ])->values()->all();
    }

    public function react(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'emoji'      => ['nullable', 'string', 'max:32'],   // any emoji, one per person
        ]);

        $msg = $this->participantMessage($me, $data['message_id']);
        abort_if($msg->isSystem() || $msg->deleted_at, 404);   // no reacting to a tombstone

        $emoji = $data['emoji'] ?? null;
        // A reaction must be a real emoji, never HTML/markup — the value is broadcast to every
        // participant and shown on their bubbles (defends against stored XSS in the reaction).
        if (is_string($emoji) && preg_match('~[<>&"\'`\x00-\x1f\x7f]~u', $emoji)) {
            throw ValidationException::withMessages(['emoji' => 'That reaction is not allowed.']);
        }

        // Lock the row for the read-modify-write so two simultaneous reactors can't clobber the
        // reactions JSON (one reaction silently lost). No-op on sqlite; real lock on MySQL.
        $added = false;
        $msg = DB::transaction(function () use ($data, $me, $emoji, &$added) {
            $m = TeamMessage::whereKey($data['message_id'])->lockForUpdate()->first();
            $r = $m->reactions ?? [];
            if ($emoji === null || ($r[$me->id] ?? null) === $emoji) {
                unset($r[$me->id]);
            } else {
                $r[$me->id] = $emoji;
                $added = true;
            }
            $m->reactions = $r ?: null;
            $m->save();

            return $m;
        });

        // Ping the message owner (unless they reacted to themselves) when a reaction is ADDED.
        if ($added && $msg->sender_id !== $me->id) {
            $this->queueReactionPush($msg, $me, $emoji);
        }

        return response()->json(['ok' => true, 'reactions' => $this->reactionsOf($msg, $me->id)]);
    }

    /** Web Push to a message's owner when someone reacts to it. Sent after the response. */
    private function queueReactionPush(TeamMessage $msg, Admin $reactor, string $emoji): void
    {
        if (! WebPushSender::enabled()) {
            return;
        }
        $conv = $msg->conversation;
        if (! $conv) {
            return;
        }
        $conv->loadMissing('participants');
        $part = $conv->participants->firstWhere('admin_id', $msg->sender_id);
        if (! $part) {
            return;
        }
        $level = $part->notify_level ?? 'all';
        if ((bool) ($part->muted ?? false) || $level === 'none') {
            return;   // muted or notifications off → no reaction ping
        }

        $snippet = $msg->body !== '' ? Str::limit($msg->body, 40) : 'your file';
        $payload = [
            'title' => $this->convTitle($conv, $msg->sender_id),   // DM → reactor's name; group → group name
            'body'  => $this->senderInfo($reactor)['first'] . ' reacted ' . $emoji . ' to: ' . $snippet,
            'url'   => route('admin.team-messages.index', ['c' => $conv->id, 'standalone' => 1]),
            'tag'   => 'apex-react-' . $msg->id,
            'conv'  => (string) $conv->id,
        ];
        $ownerId = $msg->sender_id;
        app()->terminating(function () use ($ownerId, $payload) {
            app(WebPushSender::class)->sendToAdmins([$ownerId], $payload);
        });
    }

    /** Toggle a conversation as a favorite (pinned to the top of my sidebar). */
    public function favorite(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->input('conversation_id'));
        $fav = $request->boolean('favorite');
        $conv->participants()->where('admin_id', $me->id)->update(['favorite' => $fav]);

        return response()->json(['ok' => true, 'favorite' => $fav]);
    }

    /** Toggle mute for a conversation (no unread badge / notifications for me). */
    public function mute(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->input('conversation_id'));
        $muted = $request->boolean('muted');
        $conv->participants()->where('admin_id', $me->id)->update(['muted' => $muted]);

        return response()->json(['ok' => true, 'muted' => $muted]);
    }

    /** Pin / unpin a message within its conversation. */
    public function pin(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $msg = $this->participantMessage($me, (int) $request->input('message_id'));
        abort_if($msg->isSystem(), 404);
        // A deleted message can't be pinned, but it CAN always be unpinned — otherwise an old
        // pin on a since-deleted message could never be cleared.
        abort_if($msg->deleted_at && $request->boolean('pinned'), 404);

        if ($request->boolean('pinned')) {
            $msg->forceFill(['pinned_at' => now(), 'pinned_by' => $me->id])->save();
        } else {
            $msg->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();
        }

        return response()->json(['ok' => true, 'pinned' => (bool) $msg->pinned_at]);
    }

    /** Search my conversations' message text (people/group names are filtered client-side). */
    public function search(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) return response()->json(['messages' => [], 'files' => []]);

        $convIds = DB::table('conversation_participants')->where('admin_id', $me->id)->pluck('conversation_id');
        $like    = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        // Message-text matches.
        $msgs = TeamMessage::whereIn('conversation_id', $convIds)
            ->where('type', 'text')->whereNull('deleted_at')->visibleTo($me->id)
            ->where('body', 'like', $like)
            ->with('sender', 'conversation.participants.admin')
            ->latest('id')->limit(20)->get();

        // File-name matches (attachment original_name), across all conversations I'm in.
        $fileMsgs = TeamMessage::whereIn('conversation_id', $convIds)
            ->whereNull('deleted_at')->visibleTo($me->id)
            ->whereHas('attachments', fn ($a) => $a->where('original_name', 'like', $like))
            ->with(['sender', 'conversation.participants.admin',
                    'attachments' => fn ($a) => $a->where('original_name', 'like', $like)])
            ->latest('id')->limit(20)->get();

        return response()->json([
            'messages' => $msgs->map(fn ($m) => [
                'conversation_id' => $m->conversation_id,
                'message_id' => $m->id,
                'title'   => $this->convTitle($m->conversation, $me->id),
                'snippet' => Str::limit($m->body, 80),
                'sender'  => $this->senderInfo($m->sender)['first'],
                'at'      => $m->created_at->timezone(self::TZ)->format('M j'),
            ])->values(),
            'files' => $fileMsgs->flatMap(fn ($m) => $m->attachments->map(fn ($a) => [
                'conversation_id' => $m->conversation_id,
                'message_id' => $m->id,
                'title'   => $this->convTitle($m->conversation, $me->id),
                'name'    => $a->original_name,
                'size'    => $a->humanSize(),
                'image'   => $a->isImage(),
                'sender'  => $this->senderInfo($m->sender)['first'],
                'at'      => $m->created_at->timezone(self::TZ)->format('M j'),
            ]))->take(25)->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The mentions an edited body still carries: everyone written as "@Their Name" in the text,
     * plus whatever the client sent that is still present. Keeps mentions alive across an edit
     * without depending on the client remembering them.
     *
     * @return array<int, string> mention keys for syncMentions()
     */
    private function mentionsInBody(Conversation $conv, Admin $me, string $body, array $clientMentions): array
    {
        $keys = [];
        foreach ($conv->participants as $p) {
            if ($p->admin_id === $me->id) continue;
            $name = trim((string) optional($p->admin)->full_name);
            if ($name !== '' && mb_stripos($body, '@' . $name) !== false) {
                $keys[] = (string) $p->admin_id;
            }
        }
        if ($conv->isGroup() && mb_stripos($body, '@everyone') !== false) {
            $keys[] = 'everyone';
        }
        // Anything the client still claims (it validates against participants in syncMentions).
        foreach ($clientMentions as $m) {
            $keys[] = (string) $m;
        }

        return array_values(array_unique($keys));
    }

    /** Record who a message @mentions (ids that are participants, plus @everyone). */
    private function syncMentions(TeamMessage $msg, Conversation $conv, Admin $me, array $mentions): void
    {
        $memberIds = $conv->participants->pluck('admin_id')->all();
        $all = false; $ids = [];

        foreach ($mentions as $mn) {
            if ($mn === 'everyone') { $all = true; continue; }
            $id = (int) $mn;
            if ($id && $id !== $me->id && in_array($id, $memberIds, true)) $ids[] = $id;
        }

        // mentions_all_at records when it FIRST said @everyone, and is left alone afterwards.
        if ($all) $msg->forceFill(['mentions_all' => true, 'mentions_all_at' => $msg->mentions_all_at ?: now()])->save();

        // sync() (not detach-then-attach) so an edit KEEPS the rows for people who were
        // already mentioned — along with their created_at — and only adds/removes the
        // difference. Detaching everything first made a typo fix look like a brand new
        // mention for everybody in the message.
        $msg->mentionedAdmins()->sync(array_unique($ids));

        // Stamp when each mention appeared. The notification poll walks forward by message id,
        // so a mention ADDED by editing an older message would never be reached; this is the
        // timestamp it also checks. Only never-stamped rows are touched.
        DB::table('message_mentions')->where('team_message_id', $msg->id)
            ->whereNull('created_at')->update(['created_at' => now()]);
    }

    private function mentionLabels(TeamMessage $m): array
    {
        $labels = $m->relationLoaded('mentionedAdmins') ? $m->mentionedAdmins->pluck('full_name')->all()
            : $m->mentionedAdmins()->pluck('full_name')->all();
        if ($m->mentions_all) $labels[] = 'everyone';

        return array_values($labels);
    }

    private function mentionsMe(TeamMessage $m, int $meId): bool
    {
        if ($m->mentions_all) return true;

        return $m->relationLoaded('mentionedAdmins')
            ? $m->mentionedAdmins->contains('id', $meId)
            : $m->mentionedAdmins()->where('admin_id', $meId)->exists();
    }

    /** Per-chat notification preference: all | mentions | none. */
    public function notify(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->input('conversation_id'));
        $level = in_array($request->input('level'), ['all', 'mentions', 'none'], true) ? $request->input('level') : 'all';
        $conv->participants()->where('admin_id', $me->id)->update(['notify_level' => $level]);

        return response()->json(['ok' => true, 'level' => $level]);
    }

    /** Global poll for desktop notifications — new messages I should be pinged about. */
    public function notifications(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $this->maybePurgeRetention();
        $after = (int) $request->query('after', 0);
        $convIds = DB::table('conversation_participants')->where('admin_id', $me->id)->pluck('conversation_id');
        // Second watermark, by time rather than id: see newMentions().
        $now = now();

        // First poll of a fresh client (new install, or right after the Refresh button wiped
        // its stored position) — it asks with prime=1 and gets back only where the history
        // currently ENDS. Returning the oldest messages here is what made days-old messages
        // arrive as "new" notifications, 30 at a time, until the client had walked through
        // the whole retention window. From then on it polls normally with ?after=<id>.
        if ($request->boolean('prime')) {
            return response()->json([
                'messages' => [],
                'lastId'   => (int) TeamMessage::whereIn('conversation_id', $convIds)->max('id'),
                'mLast'    => $now->toDateTimeString(),
                'more'     => false,
                'unread'   => (int) collect($this->unreadForBadge($me))->sum(),
                'perConv'  => (object) $this->unreadForBadge($me),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $rows = TeamMessage::whereIn('conversation_id', $convIds)
            ->where('type', 'text')->whereNull('deleted_at')->where('sender_id', '!=', $me->id)
            ->where('id', '>', $after)->visibleTo($me->id)
            ->with('sender', 'conversation.participants.admin', 'mentionedAdmins')
            ->orderBy('id')->limit(30)->get();

        $out = []; $maxId = $after;
        foreach ($rows as $m) {
            $maxId = max($maxId, $m->id);
            $part = $m->conversation->participants->firstWhere('admin_id', $me->id);
            $level = $part->notify_level ?? 'all';
            $muted = (bool) ($part->muted ?? false);
            $mention = $this->mentionsMe($m, $me->id);

            $should = $mention ? ($level !== 'none') : (! $muted && $level === 'all');
            if (! $should) continue;

            $out[] = [
                'id' => $m->id, 'conversation_id' => $m->conversation_id,
                'title' => $this->convTitle($m->conversation, $me->id),
                'sender' => $this->senderInfo($m->sender)['first'],
                'sender_id' => $m->sender_id,   // lets the client match a not-yet-created DM's peer row
                // Only a DM may be matched to a sender's "tap to message" row; an unknown group
                // gets a row of its own (icon + name) instead.
                'is_group'  => $m->conversation->isGroup(),
                'icon'      => $m->conversation->isGroup() ? ($m->conversation->icon ?: '💬') : null,
                'snippet' => $m->body !== '' ? Str::limit($m->body, 60) : 'Sent a file',
                'mention' => $mention,
            ];
        }

        // An @mention that arrived by EDITING an older message: its id is already behind the
        // watermark above, so only the timestamp pass below can find it.
        foreach ($this->newMentions($me, $convIds, $after, (string) $request->query('mAfter', '')) as $m) {
            $out[] = [
                'id' => $m->id, 'conversation_id' => $m->conversation_id,
                'title' => $this->convTitle($m->conversation, $me->id),
                'sender' => $this->senderInfo($m->sender)['first'],
                'sender_id' => $m->sender_id,
                'is_group' => $m->conversation->isGroup(),
                'icon' => $m->conversation->isGroup() ? ($m->conversation->icon ?: '💬') : null,
                'snippet' => $m->body !== '' ? Str::limit($m->body, 60) : 'Sent a file',
                'mention' => true,
                // Tells the client this one is deliberately BEHIND its id watermark, so its
                // "never re-notify an old id" rule must not swallow it.
                'editedMention' => true,
            ];
        }

        $perConv = $this->unreadForBadge($me);

        return response()->json([
            'messages' => $out,
            'lastId'   => $maxId,
            'mLast'    => $now->toDateTimeString(),
            // There is at least one more batch to walk through (a long absence): the client
            // holds its toasts and shows one summary when it has caught up.
            'more'     => $rows->count() >= 30,
            'unread'   => (int) collect($perConv)->sum(),
            'perConv'  => (object) $perConv,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Messages I was @mentioned in since `$mAfter` whose id the poll has ALREADY passed —
     * i.e. someone edited an older message to add my name. Without this the mention showed a
     * silent badge that only appeared if I happened to scroll back to it.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $convIds
     * @return \Illuminate\Support\Collection<int, TeamMessage>
     */
    private function newMentions(Admin $me, $convIds, int $after, string $mAfter)
    {
        // No watermark yet (an older client, or the very first poll after priming): nothing is
        // "new", and guessing would replay every old mention as a fresh notification.
        if ($mAfter === '' || $after <= 0) {
            return collect();
        }

        // This runs on every poll of every running app, so answer the common case ("nothing has
        // changed") with two indexed lookups instead of the join below.
        $any = DB::table('message_mentions')->where('admin_id', $me->id)->where('created_at', '>', $mAfter)->exists()
            || DB::table('team_messages')->where('mentions_all_at', '>', $mAfter)
                ->whereIn('conversation_id', $convIds)->exists();
        if (! $any) {
            return collect();
        }

        return TeamMessage::whereIn('conversation_id', $convIds)
            ->where('type', 'text')->whereNull('deleted_at')->where('sender_id', '!=', $me->id)
            ->where('id', '<=', $after)->visibleTo($me->id)
            ->where(function ($q) use ($me, $mAfter) {
                $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('message_mentions as mm')
                        ->whereColumn('mm.team_message_id', 'team_messages.id')
                        ->where('mm.admin_id', $me->id)->where('mm.created_at', '>', $mAfter))
                  ->orWhere(fn ($w) => $w->where('mentions_all', true)->where('mentions_all_at', '>', $mAfter));
            })
            // A mention pierces mute, but "no notifications at all" still means none.
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('conversation_participants as cp')
                ->whereColumn('cp.conversation_id', 'team_messages.conversation_id')
                ->where('cp.admin_id', $me->id)->where('cp.notify_level', '!=', 'none'))
            ->with('sender', 'conversation.participants.admin')
            ->orderBy('id')->limit(10)->get();
    }

    /**
     * Authoritative unread (unmuted) per conversation — drives the sidebar badges, the nav
     * badge and the desktop app's taskbar dot, with no client-side drift.
     *
     * @return array<int,int> conversation id => unread count
     */
    private function unreadForBadge(Admin $me): array
    {
        return DB::table('team_messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.admin_id', $me->id)->where('p.muted', false)->where('m.type', 'text')
            ->where('m.sender_id', '!=', $me->id)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')
            ->whereNull('m.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('message_hides as mh')
                ->whereColumn('mh.team_message_id', 'm.id')->where('mh.admin_id', $me->id))
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as cid, COUNT(*) as c')
            ->pluck('c', 'cid')->all();
    }

    /** Unread @mentions of me, per conversation (for the "@" badge). */
    private function unreadMentionsPerConversation(Admin $me): array
    {
        return DB::table('team_messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.admin_id', $me->id)->where('m.type', 'text')->where('m.sender_id', '!=', $me->id)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')
            ->whereNull('m.deleted_at')
            ->where(function ($q) use ($me) {
                $q->where('m.mentions_all', true)
                  ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('message_mentions as mm')
                        ->whereColumn('mm.team_message_id', 'm.id')->where('mm.admin_id', $me->id));
            })
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id, COUNT(*) AS c')
            ->pluck('c', 'm.conversation_id')->all();
    }

    private function convTitle(Conversation $conv, int $meId): string
    {
        if ($this->isSelfConv($conv)) return 'Notes (You)';
        if ($conv->isGroup()) return $conv->name ?: 'Group';
        $other = $conv->otherAdmin($meId);

        return $other->full_name ?? 'Direct message';
    }

    public function forward(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $data = $request->validate([
            'message_id'      => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'recipient_id'    => ['nullable', 'integer'],
        ]);

        $source = $this->participantMessage($me, $data['message_id']);
        abort_if($source->isSystem() || $source->deleted_at, 404);

        $target = $peer = null;
        if (! empty($data['conversation_id'])) {
            $target = $this->findConversation($me, (int) $data['conversation_id']);
        } else {
            $peer = $this->teammates($me)->findOrFail($data['recipient_id']);
        }

        // Forward the files too: each gets its OWN copy (new name on the private disk), so the
        // 7-day purge of the original message can never break the forwarded one.
        $copies = $this->copyAttachments($source, $me->dataOwnerId());
        if ($source->body === '' && ! $copies['rows']) {
            $this->discardFiles($copies['rows']);
            throw ValidationException::withMessages(['message_id' => $copies['missing']
                ? 'That file is no longer available, so it can’t be forwarded.'
                : 'Nothing to forward.']);
        }

        try {
            $this->serializedSend($me, function () use ($me, $source, &$target, $peer, $copies) {
                $target ??= $this->findOrCreateDm($me, $peer);
                $msg = TeamMessage::create([
                    'conversation_id' => $target->id,
                    'type'            => 'text',
                    'sender_id'       => $me->id,
                    'body'            => $source->body,
                    'forwarded'       => true,
                ]);
                if ($copies['rows']) {
                    $msg->attachments()->createMany($copies['rows']);
                }
                $this->touchConversation($target, $msg);
            });
        } catch (\Throwable $e) {
            $this->discardFiles($copies['rows']);
            throw $e;
        }

        return response()->json([
            'ok' => true, 'conversation_id' => $target->id,
            'files' => count($copies['rows']), 'missing_files' => $copies['missing'],
        ]);
    }

    /**
     * Copy a message's attachment files for a forward. Returns the new attachment rows and
     * how many originals were no longer on this server's disk (those are skipped).
     *
     * @return array{rows: array<int, array>, missing: int}
     */
    private function copyAttachments(TeamMessage $source, int $ownerId): array
    {
        $disk = Storage::disk('private');
        $rows = []; $missing = 0;
        try {
            foreach ($source->attachments as $a) {
                if (! $a->disk_path || ! $disk->exists($a->disk_path)) { $missing++; continue; }
                $ext = strtolower(pathinfo($a->disk_path, PATHINFO_EXTENSION));
                $ext = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? $ext : 'bin';
                $path = 'team-chat/' . $ownerId . '/' . Str::uuid() . '.' . $ext;
                if (! $disk->copy($a->disk_path, $path)) { $missing++; continue; }
                $rows[] = [
                    'disk_path' => $path, 'original_name' => $a->original_name, 'mime' => $a->mime,
                    'size' => $a->size, 'width' => $a->width, 'height' => $a->height,
                ];
            }
        } catch (\Throwable $e) {
            $this->discardFiles($rows);
            throw $e;
        }

        return ['rows' => $rows, 'missing' => $missing];
    }

    public function destroy(Request $request, TeamMessage $message)
    {
        $me = Auth::guard('admin')->user();
        abort_unless($this->isParticipant($me, $message->conversation_id), 403);

        // "Delete for me" — hide it for just this person; anyone can do it.
        if ($request->input('mode') === 'me') {
            $message->hiddenFor()->syncWithoutDetaching([$me->id]);

            return response()->json(['ok' => true, 'mode' => 'me', 'id' => $message->id]);
        }

        // "Delete for everyone" — only the sender, and never a system message.
        abort_unless($message->sender_id === $me->id && ! $message->isSystem(), 403);

        foreach ($message->attachments as $att) {
            Storage::disk('private')->delete($att->disk_path);
        }
        $message->attachments()->delete();

        $message->forceFill([
            'body' => '', 'reactions' => null, 'deleted_at' => now(), 'deleted_by' => $me->id,
            // A deleted message must not stay on the pinned bar (it showed as "📎 Attachment"
            // and could never be unpinned, because pinning refuses deleted messages).
            'pinned_at' => null, 'pinned_by' => null,
        ])->save();

        return response()->json(['ok' => true, 'mode' => 'everyone', 'id' => $message->id]);
    }

    /** Edit my own text message. */
    public function update(Request $request, TeamMessage $message)
    {
        $me = Auth::guard('admin')->user();
        // Must be my own live message AND I must still be a participant (e.g. not removed from the group).
        abort_unless(
            $message->sender_id === $me->id && ! $message->isSystem() && ! $message->deleted_at
                && $this->isParticipant($me, $message->conversation_id),
            403
        );

        $data = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array', 'max:50'],
            'mentions.*' => ['string', 'max:20'],
        ]);

        $body = trim($data['body']);
        // Who the message mentioned BEFORE the edit, so anyone the edit newly @-mentions can be
        // told about it. Without this, adding "@Name" by editing gave them a silent badge that
        // only appeared if they happened to scroll back to the message.
        $wereMentioned = $message->mentionedAdmins()->pluck('admins.id')->all();
        $wasAll = (bool) $message->mentions_all;

        $message->forceFill(['body' => $body, 'edited_at' => now()])->save();

        // Re-resolve mentions against the NEW text: anyone still written as "@Their Name" stays
        // mentioned (with their @ badge and mention notification), anyone edited out is dropped.
        // Reading the text — not just what the client sent — is what keeps an edit from quietly
        // stripping every mention off the message.
        $message->forceFill(['mentions_all' => false])->save();
        $message->load('conversation.participants.admin');
        $this->syncMentions($message, $message->conversation, $me,
            $this->mentionsInBody($message->conversation, $me, $body, $request->input('mentions', [])));

        $message->load('sender', 'replyTo.sender', 'attachments', 'mentionedAdmins', 'deletedByAdmin');
        $this->queueEditMentionPush($message, $me, $wereMentioned, $wasAll);

        return response()->json(['ok' => true, 'message' => $this->present($message, $me->id)]);
    }

    /**
     * Notify people an edit newly @-mentioned. Only the people who were NOT mentioned before,
     * and only if they'd accept a mention (notify_level 'none' still means none) — so fixing a
     * typo in a message that already mentions you never re-pings you.
     */
    private function queueEditMentionPush(TeamMessage $msg, Admin $me, array $wereMentioned, bool $wasAll): void
    {
        if (! WebPushSender::enabled()) {
            return;
        }

        // It already said @everyone: everyone was pinged then, so nothing here is new.
        if ($wasAll) {
            return;
        }

        $conv = $msg->conversation;
        $conv->load('participants');
        $nowAll = (bool) $msg->mentions_all;
        $nowIds = $msg->mentionedAdmins->pluck('id')->all();

        $recipients = [];
        foreach ($conv->participants as $p) {
            if ($p->admin_id === $me->id || ($p->notify_level ?? 'all') === 'none') {
                continue;
            }
            $isNew = ! in_array($p->admin_id, $wereMentioned, true)
                && ($nowAll || in_array($p->admin_id, $nowIds, true));
            if ($isNew) {
                $recipients[] = $p->admin_id;
            }
        }
        if (empty($recipients)) {
            return;
        }

        $payload = [
            'title' => $this->convTitle($conv, $recipients[0]),
            'body'  => '@ ' . $this->senderInfo($msg->sender)['first'] . ' mentioned you: ' . Str::limit($msg->body, 80),
            'url'   => route('admin.team-messages.index', ['c' => $conv->id, 'standalone' => 1]),
            'tag'   => 'apex-team-' . $conv->id,
            'conv'  => (string) $conv->id,
        ];

        app()->terminating(function () use ($recipients, $payload) {
            app(WebPushSender::class)->sendToAdmins($recipients, $payload);
        });
    }

    /** All files/images shared in a conversation (the "Shared files" gallery). */
    public function gallery(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->query('c'));

        // Newest first, a page at a time. It used to return only the newest 200 with no hint
        // that anything older existed — files past that were simply invisible in the Files and
        // Photos tabs. `before` (an attachment id) pages back through the rest.
        $perPage = 200;
        $before = (int) $request->query('before');

        $query = MessageAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conv->id)
                ->whereNull('deleted_at')->visibleTo($me->id))
            ->with('message.sender')->latest('id');
        if ($before > 0) {
            $query->where('id', '<', $before);
        }

        $atts = $query->limit($perPage + 1)->get();
        $hasMore = $atts->count() > $perPage;
        $atts = $atts->take($perPage);

        return response()->json([
            'hasMore' => $hasMore,
            'oldest'  => (int) ($atts->last()->id ?? 0),   // pass back as ?before= for the next page
            'files' => $atts->map(function (MessageAttachment $a) {
                $url = route('admin.team-messages.attachment', $a->id);
                $when = $a->created_at->timezone(self::TZ);
                return [
                    'name' => $a->original_name, 'size' => $a->humanSize(), 'image' => $a->isImage(),
                    'url' => $url, 'download' => $url . '?dl=1',
                    'by' => optional($a->message->sender)->full_name ?? 'Someone',
                    'at' => $when->format('M j, Y'),
                    'time' => $when->format('g:i A'),
                    'ts' => $when->timestamp,
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Guarded, org-scoped file access — inline by default, ?dl=1 forces download. */
    public function attachment(Request $request, MessageAttachment $attachment)
    {
        $me  = Auth::guard('admin')->user();
        $msg = $attachment->message;

        abort_unless($msg && $this->isParticipant($me, $msg->conversation_id), 403);
        abort_if((bool) $msg->deleted_at, 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($attachment->disk_path), 404);

        $headers = [
            'Cache-Control'          => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',   // don't let the browser sniff into an active type
        ];

        $ext  = strtolower(pathinfo($attachment->disk_path, PATHINFO_EXTENSION));
        $path = $disk->path($attachment->disk_path);

        // Only genuine images are served INLINE, and with an explicit image content-type derived
        // from the extension — never a server-sniffed type. Everything else is forced to download,
        // so a file renamed to an allowed extension can never be rendered as HTML/script.
        if (! $request->boolean('dl') && isset(self::IMAGE_MIME[$ext])) {
            // An attachment id always points at the same bytes, so the browser may keep it.
            // (This used to be no-store, which re-downloaded every photo on every chat switch,
            // gallery open and lightbox — the heaviest traffic the chat produced.)
            $img = response()->file($path, [
                'Content-Type'           => self::IMAGE_MIME[$ext],
                'X-Content-Type-Options' => 'nosniff',
            ]);
            // Set after building the response: a file response otherwise marks itself public,
            // and these images are private — Cloudflare (or any shared cache) must never hold
            // one and hand it to somebody else. "private" = this browser only.
            $img->headers->set('Cache-Control', 'private, max-age=31536000, immutable');

            return $img;
        }

        return response()->download($path, $attachment->original_name, $headers + ['Content-Type' => 'application/octet-stream']);
    }

    // ---------------------------------------------------------------- avatars

    /** Stream a teammate's uploaded profile photo (org-scoped, private disk). */
    public function avatar(Request $request, Admin $admin)
    {
        $me = Auth::guard('admin')->user();
        // Only people in my org (super + their VAs), or myself.
        abort_unless($admin->id === $me->id || $admin->dataOwnerId() === $me->dataOwnerId(), 403);
        abort_unless($admin->avatar && str_starts_with($admin->avatar, 'team-avatars/'), 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($admin->avatar), 404);

        $photo = response()->file($disk->path($admin->avatar), [
            'Content-Type'           => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // The URL carries the photo's own timestamp (see Admin::avatarUrl), so it changes only
        // when the photo does and the browser can keep it for a day. Set after building the
        // response, which would otherwise mark itself public — these are private photos and a
        // shared cache (Cloudflare) must never hold one.
        $photo->headers->set('Cache-Control', 'private, max-age=86400');

        return $photo;
    }

    /** Replace my own profile photo with an uploaded (already cropped) image. */
    public function avatarUpdate(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],   // 8 MB
        ]);

        $img = $request->file('photo');
        // Re-encode to a normalised square JPEG so we never store an oversized or odd
        // format, and every avatar is served as a clean image/jpeg.
        $data = $this->normaliseAvatar($img->getRealPath());
        if ($data === null) {
            throw ValidationException::withMessages(['photo' => 'That image could not be processed.']);
        }

        $path = 'team-avatars/' . $me->id . '.jpg';
        Storage::disk('private')->put($path, $data);

        $me->forceFill(['avatar' => $path])->save();

        return response()->json(['ok' => true, 'url' => $me->fresh()->avatarUrl()]);
    }

    /** Remove my photo — falls back to a monogram (sentinel "-"). */
    public function avatarRemove(Request $request)
    {
        $me = Auth::guard('admin')->user();
        if ($me->avatar && str_starts_with($me->avatar, 'team-avatars/')) {
            Storage::disk('private')->delete($me->avatar);
        }
        $me->forceFill(['avatar' => '-'])->save();

        return response()->json(['ok' => true, 'url' => $me->fresh()->avatarUrl()]);
    }

    /** Downscale + centre-crop to a square JPEG (max 512px). Returns binary or null. */
    private function normaliseAvatar(string $srcPath): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            // No GD — fall back to storing the original bytes as-is.
            $raw = @file_get_contents($srcPath);

            return $raw !== false ? $raw : null;
        }
        $info = @getimagesize($srcPath);
        if (! $info) return null;

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($srcPath); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($srcPath); break;
            case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : null; break;
            default: return null;
        }
        if (! $src) return null;

        $w = imagesx($src); $h = imagesy($src);
        $side = min($w, $h);
        $sx = (int) (($w - $side) / 2);
        $sy = (int) (($h - $side) / 2);
        $out = min(512, $side);

        $dst = imagecreatetruecolor($out, $out);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $out, $out, $side, $side);

        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);

        return $bytes ?: null;
    }

    // ---------------------------------------------------------------- groups

    public function storeGroup(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:80'],
            'icon'      => ['nullable', 'string', Rule::in(self::GROUP_ICONS)],
            'members'   => ['required', 'array', 'min:1'],
            'members.*' => ['integer'],
        ]);

        // Every chosen member must be a teammate in my org.
        $memberIds = $this->teammates($me)->whereIn('id', $data['members'])->pluck('id')->all();
        abort_if(empty($memberIds), 422);

        $conv = Conversation::create([
            'type' => 'group', 'name' => trim($data['name']), 'icon' => $data['icon'] ?? '💬',
            'data_owner_id' => $me->dataOwnerId(), 'created_by' => $me->id,
        ]);

        $conv->participants()->create(['admin_id' => $me->id, 'role' => 'admin', 'joined_at' => now()]);
        foreach ($memberIds as $id) {
            $conv->participants()->create(['admin_id' => $id, 'role' => 'member', 'joined_at' => now()]);
        }

        $this->system($conv, $me, $me->full_name . ' created the group “' . $conv->name . '”');

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'conversation_id' => $conv->id]);
        }

        return redirect()->route('admin.team-messages.index', ['c' => $conv->id]);
    }

    public function addMembers(Request $request, Conversation $conversation)
    {
        $me = Auth::guard('admin')->user();
        $this->authorizeGroupAdmin($me, $conversation);

        $data = $request->validate(['members' => ['required', 'array', 'min:1'], 'members.*' => ['integer']]);
        $present = $conversation->participants->pluck('admin_id')->all();
        $add = $this->teammates($me)->whereIn('id', $data['members'])->whereNotIn('id', $present)->get();

        foreach ($add as $t) {
            $conversation->participants()->create(['admin_id' => $t->id, 'role' => 'member', 'joined_at' => now()]);
            $this->system($conversation, $me, $me->full_name . ' added ' . $t->full_name);
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : back();
    }

    public function removeMember(Request $request, Conversation $conversation, Admin $admin)
    {
        $me = Auth::guard('admin')->user();
        $this->authorizeGroupAdmin($me, $conversation);
        abort_if($admin->id === $me->id, 422);   // use "leave" to remove yourself

        $part = $conversation->participants()->where('admin_id', $admin->id)->first();
        if ($part) {
            $part->delete();
            $this->system($conversation, $me, $me->full_name . ' removed ' . $admin->full_name);
        }

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function leaveGroup(Request $request, Conversation $conversation)
    {
        $me = Auth::guard('admin')->user();
        $this->authorizeParticipant($me, $conversation);
        abort_unless($conversation->isGroup(), 404);

        $conversation->participants()->where('admin_id', $me->id)->delete();
        $this->system($conversation, $me, $me->full_name . ' left the group');

        // Never leave a group with members but no admin — promote the earliest member.
        $conversation->load('participants');
        if ($conversation->participants->isNotEmpty() && ! $conversation->participants->contains('role', 'admin')) {
            $conversation->participants()->orderBy('id')->first()?->update(['role' => 'admin']);
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('admin.team-messages.index');
    }

    public function renameGroup(Request $request, Conversation $conversation)
    {
        $me = Auth::guard('admin')->user();
        $this->authorizeGroupAdmin($me, $conversation);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', Rule::in(self::GROUP_ICONS)],
        ]);
        $conversation->update(['name' => trim($data['name']), 'icon' => $data['icon'] ?? $conversation->icon]);
        $this->system($conversation, $me, $me->full_name . ' renamed the group to “' . $conversation->name . '”');

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    // ---------------------------------------------------------------- presentation

    private function present(TeamMessage $m, int $meId): array
    {
        $day = $this->dayLabel($m->created_at);

        if ($m->isSystem()) {
            return ['id' => $m->id, 'system' => true, 'body' => $m->body,
                    'at' => $m->created_at->timezone(self::TZ)->format('M j · g:i A'),
                    'dayKey' => $day['key'], 'day' => $day['label']];
        }

        return [
            'id'          => $m->id,
            'system'      => false,
            'mine'        => $m->sender_id === $meId,
            'body'        => $m->deleted_at ? '' : $m->body,
            'at'          => $m->created_at->timezone(self::TZ)->format('M j · g:i A'),
            'dayKey'      => $day['key'],
            'day'         => $day['label'],
            'deleted'     => (bool) $m->deleted_at,
            'forwarded'   => (bool) $m->forwarded,
            'reactions'   => $this->reactionsOf($m, $meId),
            'reply'       => $this->replySnippet($m, $meId),
            'attachments' => $m->deleted_at ? [] : $this->attachmentsOf($m),
            'sender'      => $this->senderInfo($m->sender),
            'pinned'      => (bool) $m->pinned_at,
            'deletedBy'   => $m->deleted_at ? ($m->deleted_by === $meId ? 'You' : $this->senderInfo($m->deletedByAdmin)['first']) : null,
            'mentionsMe'  => ! $m->deleted_at && $this->mentionsMe($m, $meId),
            'mentionLabels' => $m->deleted_at ? [] : $this->mentionLabels($m),
            'edited'      => ! $m->deleted_at && (bool) $m->edited_at,
        ];
    }

    /** The day-separator key + label for a message (matches the initial Blade render). */
    private function dayLabel(\Illuminate\Support\Carbon $at): array
    {
        $d = $at->copy()->timezone(self::TZ);
        $now = now(self::TZ);
        $label = $d->isSameDay($now) ? 'Today' : ($d->isSameDay($now->copy()->subDay()) ? 'Yesterday' : $d->format('F j, Y'));

        return ['key' => $d->format('Y-m-d'), 'label' => $label];
    }

    private function senderInfo(?Admin $a): array
    {
        if (! $a) return ['name' => 'Someone', 'first' => 'Someone', 'avatar' => null, 'mono' => '?', 'color' => '#64748b'];
        $parts = preg_split('/\s+/', trim($a->full_name ?: '?'));
        $mono  = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
        $palette = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#14b8a6', '#f43f5e', '#7c3aed', '#0891b2'];
        $n = 0; foreach (str_split($a->full_name ?: '?') as $ch) $n += ord($ch);

        return ['name' => $a->full_name, 'first' => $parts[0], 'avatar' => $a->avatarUrl(), 'mono' => $mono, 'color' => $palette[$n % count($palette)]];
    }

    private function attachmentsOf(TeamMessage $m): array
    {
        return $m->attachments->map(function (MessageAttachment $a) {
            $url = route('admin.team-messages.attachment', $a->id);

            return ['name' => $a->original_name, 'size' => $a->humanSize(), 'image' => $a->isImage(),
                    'url' => $url, 'download' => $url . '?dl=1', 'w' => $a->width, 'h' => $a->height];
        })->values()->all();
    }

    private function reactionsOf(TeamMessage $m, int $meId): array
    {
        $out = [];
        foreach (($m->reactions ?? []) as $uid => $emoji) {
            $out[$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'mine' => false];
            $out[$emoji]['count']++;
            if ((int) $uid === $meId) $out[$emoji]['mine'] = true;
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($out);
    }

    private function replySnippet(TeamMessage $m, int $meId): ?array
    {
        $r = $m->replyTo;
        if (! $r) return null;

        return [
            'id'     => $r->id,
            'author' => $r->sender_id === $meId ? 'You' : ($r->sender->full_name ?? 'Teammate'),
            'text'   => $r->deleted_at ? 'Deleted message' : Str::limit($r->body !== '' ? $r->body : '📎 Attachment', 90),
        ];
    }

    // ---------------------------------------------------------------- sidebar items

    private function dmItem(Admin $me, Admin $peer, ?Conversation $c, $lastMsgs, array $unread, array $mentions = []): array
    {
        $last = $c && $c->last_message_id ? $lastMsgs->get($c->last_message_id) : null;
        $part = $c ? $c->participantFor($me->id) : null;

        return [
            'kind'            => 'dm',
            'mentions'        => $c ? ($mentions[$c->id] ?? 0) : 0,
            'conversation_id' => $c?->id,
            'peer_id'         => $peer->id,
            'name'            => $peer->full_name,
            'is_group'        => false,
            'icon'            => null,
            'peer'            => $peer,
            'online'          => $peer->isOnline(),
            'favorite'        => $part ? (bool) $part->favorite : false,
            'muted'           => $part ? (bool) $part->muted : false,
            'href'            => $c ? ['c' => $c->id] : ['with' => $peer->id],
            'preview'         => $last ? $this->previewOf($me, $c, $last, false) : null,
            'unread'          => $c ? ($unread[$c->id] ?? 0) : 0,
            'ts'              => $last ? $last->created_at->timestamp : 0,
            'active_key'      => $c ? ('c' . $c->id) : ('u' . $peer->id),
        ];
    }

    private function groupItem(Admin $me, Conversation $c, $lastMsgs, array $unread, array $mentions = []): array
    {
        $last = $c->last_message_id ? $lastMsgs->get($c->last_message_id) : null;
        $part = $c->participantFor($me->id);

        return [
            'kind'            => 'group',
            'mentions'        => $mentions[$c->id] ?? 0,
            'conversation_id' => $c->id,
            'peer_id'         => null,
            'name'            => $c->name,
            'is_group'        => true,
            'icon'            => $c->icon ?: '💬',
            'peer'            => null,
            'favorite'        => $part ? (bool) $part->favorite : false,
            'muted'           => $part ? (bool) $part->muted : false,
            'members_count'   => $c->participants->count(),
            'href'            => ['c' => $c->id],
            'preview'         => $last ? $this->previewOf($me, $c, $last, true) : null,
            'unread'          => $unread[$c->id] ?? 0,
            'ts'              => $last ? $last->created_at->timestamp : ($c->created_at ? $c->created_at->timestamp : 0),
            'active_key'      => 'c' . $c->id,
        ];
    }

    private function previewOf(Admin $me, Conversation $c, TeamMessage $last, bool $group): array
    {
        if ($last->isSystem()) {
            $text = $last->body;
        } else {
            $text = $last->deleted_at ? 'This message was deleted' : ($last->body !== '' ? $last->body : '📎 Attachment');
            if ($group && ! $last->deleted_at) {
                $who = $last->sender_id === $me->id ? 'You' : ($this->senderInfo($last->sender)['first']);
                $text = $who . ': ' . $text;
            }
        }

        $mine = ! $last->isSystem() && $last->sender_id === $me->id;

        return [
            'text' => $text,
            'mine' => $mine,
            'read' => $mine ? $this->readUpTo($c, $me->id) >= $last->id : true,
            'at'   => $this->shortTime($last->created_at),
        ];
    }

    // ---------------------------------------------------------------- helpers

    private function myConversations(Admin $me)
    {
        return Conversation::where('data_owner_id', $me->dataOwnerId())
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $me->id))
            ->with('participants.admin')
            ->get();
    }

    private function findConversation(Admin $me, int $id): Conversation
    {
        $conv = Conversation::where('id', $id)
            ->where('data_owner_id', $me->dataOwnerId())
            ->with('participants.admin')
            ->first();

        // Say which it is, in words the app can show: removed from a group I could see before,
        // versus a chat that no longer exists at all. (It used to surface Laravel's raw
        // "No query results for model [App\Models\Conversation]".)
        abort_if(! $conv, 404, 'This chat is no longer available.');
        abort_unless($conv->participants->contains('admin_id', $me->id), 403,
            $conv->isGroup() ? 'You’re no longer a member of this group.' : 'This chat is no longer available to you.');

        return $conv;
    }

    private function findOrCreateDm(Admin $me, Admin $peer): Conversation
    {
        $ids = [$me->id, $peer->id];
        sort($ids);
        $key = 'dm:' . $ids[0] . '-' . $ids[1];

        $find = fn () => Conversation::where('type', 'dm')->where('data_owner_id', $me->dataOwnerId())
            ->where('dm_key', $key)->with('participants.admin')->first();

        if ($conv = $find()) {
            return $conv;
        }

        // Create under the org lock (so two first messages sent at once can't create two DM
        // threads for the same pair); if one slipped through anyway, fetch theirs (M10).
        try {
            return $this->serializedSend($me, function () use ($me, $peer, $key, $find) {
                if ($conv = $find()) {
                    return $conv;
                }
                $conv = Conversation::create([
                    'type' => 'dm', 'data_owner_id' => $me->dataOwnerId(), 'created_by' => $me->id, 'dm_key' => $key,
                ]);
                $conv->participants()->createMany([
                    ['admin_id' => $me->id, 'role' => 'member', 'joined_at' => now()],
                    ['admin_id' => $peer->id, 'role' => 'member', 'joined_at' => now()],
                ]);

                return $conv->load('participants.admin');
            });
        } catch (\Throwable $e) {
            return $find() ?: throw $e;
        }
    }

    /** The "message yourself" conversation — a private notes thread (one participant: me). */
    private function findOrCreateSelf(Admin $me): Conversation
    {
        $key  = 'self:' . $me->id;
        $find = fn () => Conversation::where('type', 'dm')->where('data_owner_id', $me->dataOwnerId())
            ->where('dm_key', $key)->with('participants.admin')->first();

        if ($conv = $find()) {
            return $conv;
        }

        try {
            return DB::transaction(function () use ($me, $key) {
                $conv = Conversation::create([
                    'type' => 'dm', 'data_owner_id' => $me->dataOwnerId(), 'created_by' => $me->id, 'dm_key' => $key,
                ]);
                $conv->participants()->create(['admin_id' => $me->id, 'role' => 'member', 'joined_at' => now()]);

                return $conv->load('participants.admin');
            });
        } catch (\Throwable $e) {
            return $find() ?: throw $e;
        }
    }

    private function isSelfConv(?Conversation $conv): bool
    {
        return $conv && str_starts_with((string) $conv->dm_key, 'self:');
    }

    /**
     * Cron-less 7-day retention: at most once an hour, triggered by normal chat traffic and run
     * AFTER the response so it never slows a request. It runs on whichever server the app is
     * talking to (the chat subdomain), so THAT server's files are cleared too — no cron needed.
     * The marker lives in this docroot's storage, so each server keeps its own hourly cadence.
     */
    private function maybePurgeRetention(): void
    {
        if (app()->runningUnitTests()) {
            return;   // the purge is covered by PurgeOldTeamChatTest; don't run it mid-suite
        }
        $marker = storage_path('framework/team-chat-purge.at');
        if (is_file($marker) && (time() - filemtime($marker)) < 3600) {
            return;   // already ran within the last hour on this server
        }
        @touch($marker);   // claim this hour (best-effort; the purge is idempotent if it double-runs)

        app()->terminating(function () {
            try { Artisan::call('team-chat:purge'); } catch (\Throwable $e) {}
        });
    }

    private function participantMessage(Admin $me, int $id): TeamMessage
    {
        return TeamMessage::where('id', $id)
            ->whereHas('conversation.participants', fn ($q) => $q->where('admin_id', $me->id))
            ->firstOrFail();
    }

    private function isParticipant(Admin $me, ?int $conversationId): bool
    {
        if (! $conversationId) return false;

        return DB::table('conversation_participants')
            ->where('conversation_id', $conversationId)->where('admin_id', $me->id)->exists();
    }

    private function authorizeParticipant(Admin $me, Conversation $conv): void
    {
        abort_unless($conv->data_owner_id === $me->dataOwnerId() && $this->isParticipant($me, $conv->id), 403);
    }

    private function authorizeGroupAdmin(Admin $me, Conversation $conv): void
    {
        abort_unless($conv->isGroup(), 404);
        $this->authorizeParticipant($me, $conv);
        $part = $conv->participants()->where('admin_id', $me->id)->first();
        abort_unless($part && $part->role === 'admin', 403);
    }

    private function system(Conversation $conv, Admin $actor, string $text): TeamMessage
    {
        return $this->serializedSend($actor, function () use ($conv, $actor, $text) {
            $msg = TeamMessage::create([
                'conversation_id' => $conv->id, 'type' => 'system', 'sender_id' => $actor->id, 'body' => $text,
            ]);
            $this->touchConversation($conv, $msg);

            return $msg;
        });
    }

    private function touchConversation(Conversation $conv, TeamMessage $msg): void
    {
        // Only ever move the pointer forward — under concurrent sends an older message's update
        // must not overwrite a newer last_message_id.
        Conversation::whereKey($conv->id)
            ->where(fn ($q) => $q->whereNull('last_message_id')->orWhere('last_message_id', '<', $msg->id))
            ->update(['last_message_id' => $msg->id, 'last_message_at' => $msg->created_at]);
        $conv->last_message_id = $msg->id;
        $conv->last_message_at = $msg->created_at;
    }

    /** Mark everything in the conversation read up to its newest message, for me. */
    private function markRead(Conversation $conv, Admin $me): void
    {
        $maxId = (int) $conv->messages()->max('id');
        if ($maxId) $this->markReadTo($conv, $me->id, $maxId);
    }

    private function markReadTo(Conversation $conv, int $adminId, int $messageId): void
    {
        $conv->participants()->where('admin_id', $adminId)
            ->where(fn ($q) => $q->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $messageId))
            ->update(['last_read_message_id' => $messageId]);
    }

    /** Highest message id read by EVERY other participant (drives blue ticks). */
    private function readUpTo(Conversation $conv, int $meId): int
    {
        $others = $conv->participants->where('admin_id', '!=', $meId);
        if ($others->isEmpty()) return 0;

        return (int) $others->min(fn ($p) => (int) $p->last_read_message_id);
    }

    private function unreadPerConversation(Admin $me): array
    {
        return DB::table('team_messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.admin_id', $me->id)
            ->where('m.type', 'text')
            ->where('m.sender_id', '!=', $me->id)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')
            ->whereNull('m.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('message_hides as mh')
                ->whereColumn('mh.team_message_id', 'm.id')->where('mh.admin_id', $me->id))
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id, COUNT(*) AS c')
            ->pluck('c', 'm.conversation_id')->all();
    }

    private function shortTime(\Illuminate\Support\Carbon $dt): string
    {
        $dt  = $dt->copy()->timezone(self::TZ);
        $now = now(self::TZ);

        if ($dt->isSameDay($now))                   return $dt->format('g:i A');
        if ($dt->isSameDay($now->copy()->subDay())) return 'Yesterday';
        if ($dt->greaterThan($now->copy()->subDays(6)->startOfDay())) return $dt->format('l');

        return $dt->format('M j');
    }

    /**
     * Write uploaded files to the private disk and return their attachment rows (not yet
     * saved). Stored before the message is inserted, so the message, its attachments and
     * its mentions can be committed together.
     */
    private function storeFiles(array $files, int $ownerId): array
    {
        $rows = [];
        try {
            foreach ($files as $file) {
                $rows[] = $this->storeFile($file, $ownerId);
            }
        } catch (\Throwable $e) {
            $this->discardFiles($rows);
            throw $e;
        }

        return $rows;
    }

    /** Remove files written by storeFiles() whose message was never saved. */
    private function discardFiles(array $rows): void
    {
        $paths = array_values(array_filter(array_column($rows, 'disk_path')));
        if ($paths) {
            try { Storage::disk('private')->delete($paths); } catch (\Throwable $e) {}
        }
    }

    private function storeFile($file, int $ownerId): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        // Any type is accepted. Keep the disk extension to a safe token so a file
        // named "x.php" can't sit on disk as an executable path; the real name is
        // preserved in original_name and used for the download filename.
        $diskExt = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? $ext : 'bin';

        $w = $h = null;
        if (in_array($ext, self::IMAGE_EXT, true)) {
            $dims = @getimagesize($file->getRealPath());
            if ($dims) { $w = $dims[0]; $h = $dims[1]; }
        }

        $path = $file->storeAs('team-chat/' . $ownerId, Str::uuid() . '.' . $diskExt, 'private');
        if (! $path) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }

        // Cap the client-supplied display name so an overlong name can't error the insert.
        $name = (string) $file->getClientOriginalName();
        if ($name === '') {
            $name = 'file.' . $diskExt;
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $name = mb_substr($name, 0, self::MAX_NAME - mb_strlen($diskExt) - 2) . '.' . $diskExt;
        }

        return [
            'disk_path' => $path, 'original_name' => $name,
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'width' => $w, 'height' => $h,
        ];
    }

    /** The other admins in my org I can message (super + VAs; not leads, not me). */
    private function teammates(Admin $me)
    {
        $ownerId = $me->dataOwnerId();

        return Admin::where(fn ($q) => $q->where('id', $ownerId)->orWhere('parent_admin_id', $ownerId))
            ->where('id', '!=', $me->id)
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'leads'));
    }
}
