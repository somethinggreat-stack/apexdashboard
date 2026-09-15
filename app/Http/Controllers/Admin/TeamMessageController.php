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
    private const TZ = 'America/New_York';

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

        if ($active) {
            // Load only the newest page; older messages come in on demand ("load earlier").
            $messages = $active->messages()->visibleTo($me->id)
                ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
                ->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
            $hasMoreOlder = $messages->isNotEmpty()
                && $active->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();
            $pinned = $active->messages()->visibleTo($me->id)->whereNotNull('pinned_at')->with('sender')
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
            'attachments'     => ['nullable', 'array', 'max:' . self::MAX_FILES],
            'attachments.*'   => ['file', 'max:' . self::MAX_KB],
            'mentions'        => ['nullable', 'array', 'max:50'],
            'mentions.*'      => ['string', 'max:20'],
        ]);

        // Resolve (or start) the conversation.
        if (! empty($data['conversation_id'])) {
            $conv = $this->findConversation($me, (int) $data['conversation_id']);
        } elseif (! empty($data['recipient_id'])) {
            $peer = $this->teammates($me)->findOrFail($data['recipient_id']);
            $conv = $this->findOrCreateDm($me, $peer);
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

        $replyToId = null;
        if (! empty($data['reply_to_id'])) {
            $quoted = $conv->messages()->find($data['reply_to_id']);
            $replyToId = $quoted?->id;
        }

        $msg = TeamMessage::create([
            'conversation_id' => $conv->id,
            'type'            => 'text',
            'sender_id'       => $me->id,
            'reply_to_id'     => $replyToId,
            'body'            => $body,
        ]);
        $this->storeAttachments($msg, $files, $conv->data_owner_id);
        $this->syncMentions($msg, $conv, $me, $request->input('mentions', []));
        $this->touchConversation($conv, $msg);
        $this->markReadTo($conv, $me->id, $msg->id);   // I've read my own message
        $conv->participants()->where('admin_id', $me->id)->update(['typing_at' => null]);

        $msg->load('sender', 'replyTo.sender', 'attachments', 'mentionedAdmins');

        $this->queuePush($conv, $msg, $me);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $this->present($msg, $me->id), 'conversation_id' => $conv->id]);
        }

        return redirect()->route('admin.team-messages.index', ['c' => $conv->id]);
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

        $this->markRead($conv, $me);

        // Reaction/edit/delete "states": on the first poll sync the visible window (latest 80);
        // after that, return only what CHANGED since the client's last poll — so edits/reactions/
        // deletes on ANY message (not just the newest 80) propagate live.
        $statesSince = (string) $request->query('statesSince', '');
        $stateToken  = now()->toDateTimeString();   // captured before the query, round-tripped by the client
        $statesQuery = $conv->messages()->with('deletedByAdmin', 'mentionedAdmins');
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

        $messages = collect(); $hasMore = false; $pinned = collect();
        $members = collect(); $addable = collect(); $mentionables = []; $notifyLevel = 'all';

        if ($active) {
            $messages = $active->messages()->visibleTo($me->id)
                ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
                ->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
            $hasMore = $messages->isNotEmpty()
                && $active->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();
            $pinned = $active->messages()->visibleTo($me->id)->whereNotNull('pinned_at')->with('sender')
                ->orderByDesc('pinned_at')->limit(10)->get();
            $this->markRead($active, $me);
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

        return response()->json([
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
        abort_if($msg->isSystem() || $msg->deleted_at, 404);

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

        if ($all) $msg->forceFill(['mentions_all' => true])->save();
        if ($ids) $msg->mentionedAdmins()->sync(array_unique($ids));
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
                'snippet' => $m->body !== '' ? Str::limit($m->body, 60) : 'Sent a file',
                'mention' => $mention,
            ];
        }

        // Authoritative unread (unmuted), per conversation — drives the sidebar badges and
        // the nav badge with no client-side drift. Keyed by conversation id.
        $perConv = DB::table('team_messages as m')
            ->join('conversation_participants as p', 'p.conversation_id', '=', 'm.conversation_id')
            ->where('p.admin_id', $me->id)->where('p.muted', false)->where('m.type', 'text')
            ->where('m.sender_id', '!=', $me->id)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')
            ->whereNull('m.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('message_hides as mh')
                ->whereColumn('mh.team_message_id', 'm.id')->where('mh.admin_id', $me->id))
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as cid, COUNT(*) as c')
            ->pluck('c', 'cid');

        return response()->json([
            'messages' => $out,
            'lastId'   => $maxId,
            'unread'   => (int) $perConv->sum(),
            'perConv'  => (object) $perConv->all(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
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

        if (! empty($data['conversation_id'])) {
            $target = $this->findConversation($me, (int) $data['conversation_id']);
        } else {
            $peer = $this->teammates($me)->findOrFail($data['recipient_id']);
            $target = $this->findOrCreateDm($me, $peer);
        }

        $msg = TeamMessage::create([
            'conversation_id' => $target->id,
            'type'            => 'text',
            'sender_id'       => $me->id,
            'body'            => $source->body,
            'forwarded'       => true,
        ]);
        $this->touchConversation($target, $msg);

        return response()->json(['ok' => true, 'conversation_id' => $target->id]);
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

        $message->forceFill(['body' => trim($data['body']), 'edited_at' => now()])->save();

        // Re-resolve mentions against the new text.
        $message->mentionedAdmins()->detach();
        $message->forceFill(['mentions_all' => false])->save();
        $message->load('conversation.participants');
        $this->syncMentions($message, $message->conversation, $me, $request->input('mentions', []));

        $message->load('sender', 'replyTo.sender', 'attachments', 'mentionedAdmins', 'deletedByAdmin');

        return response()->json(['ok' => true, 'message' => $this->present($message, $me->id)]);
    }

    /** All files/images shared in a conversation (the "Shared files" gallery). */
    public function gallery(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->query('c'));

        $atts = MessageAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conv->id)->whereNull('deleted_at'))
            ->with('message.sender')->latest('id')->limit(200)->get();

        return response()->json([
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
            return response()->file($path, $headers + ['Content-Type' => self::IMAGE_MIME[$ext]]);
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

        return response()->file($disk->path($admin->avatar), [
            'Content-Type'           => 'image/jpeg',
            'Cache-Control'          => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        return Conversation::where('id', $id)
            ->where('data_owner_id', $me->dataOwnerId())
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $me->id))
            ->with('participants.admin')
            ->firstOrFail();
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

        // Create in a transaction; if a concurrent request beat us to it, fetch theirs instead of
        // leaving two split DM threads for the same pair (M10).
        try {
            return DB::transaction(function () use ($me, $peer, $key) {
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
        $msg = TeamMessage::create([
            'conversation_id' => $conv->id, 'type' => 'system', 'sender_id' => $actor->id, 'body' => $text,
        ]);
        $this->touchConversation($conv, $msg);

        return $msg;
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

    private function storeAttachments(TeamMessage $msg, array $files, int $ownerId): void
    {
        foreach ($files as $file) {
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

            // Cap the client-supplied display name so an overlong name can't error the insert.
            $name = (string) $file->getClientOriginalName();
            if ($name === '') {
                $name = 'file.' . $diskExt;
            } elseif (mb_strlen($name) > self::MAX_NAME) {
                $name = mb_substr($name, 0, self::MAX_NAME - mb_strlen($diskExt) - 2) . '.' . $diskExt;
            }

            $msg->attachments()->create([
                'disk_path' => $path, 'original_name' => $name,
                'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'width' => $w, 'height' => $h,
            ]);
        }
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
