<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\TeamMessage;
use Illuminate\Http\Request;
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

    private const EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    private const GROUP_ICONS = ['💬', '🚀', '🔥', '⭐', '📁', '🎯', '💼', '📣', '🛠️', '🏆'];

    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx'];
    private const MAX_KB = 25600;
    private const MAX_FILES = 10;
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

        // Build the unified sidebar: every teammate as a DM + every group I'm in.
        $items = [];
        foreach ($teammates as $t) {
            $c = $dmByPeer[$t->id] ?? null;
            $items[] = $this->dmItem($me, $t, $c, $lastMsgs, $unread);
        }
        foreach ($convos as $c) {
            if ($c->isGroup()) $items[] = $this->groupItem($me, $c, $lastMsgs, $unread);
        }

        // Most recent conversation first; never-used DMs fall to the bottom by name.
        usort($items, function ($a, $b) {
            $at = $a['ts']; $bt = $b['ts'];
            if ($at && $bt) return $bt <=> $at;
            if ($at) return -1;
            if ($bt) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        // Resolve the active thread: ?c=<conversation> or ?with=<teammate>.
        $active = null; $peer = null; $messages = collect(); $members = collect(); $addable = collect();

        if ($request->filled('c')) {
            $active = $convos->firstWhere('id', (int) $request->query('c'));
        } elseif ($request->filled('with')) {
            $peer = $teammates->firstWhere('id', (int) $request->query('with'));
            if ($peer) $active = $dmByPeer[$peer->id] ?? null;   // may be null → virtual DM
        }

        if ($active) {
            $messages = $active->messages()
                ->with('sender', 'replyTo.sender', 'attachments')->orderBy('id')->get();
            $this->markRead($active, $me);

            if ($active->isDm()) {
                $peer = $active->otherAdmin($me->id);
            } else {
                $members = $active->participants->load('admin');
                $inIds   = $active->participants->pluck('admin_id')->all();
                $addable = $teammates->whereNotIn('id', $inIds)->values();
            }
        }

        return view($this->adminView('admin.team-messages.index'), [
            'me' => $me, 'items' => $items, 'active' => $active, 'peer' => $peer,
            'messages' => $messages, 'members' => $members, 'addable' => $addable,
            'teammates' => $teammates, 'emoji' => self::EMOJI, 'groupIcons' => self::GROUP_ICONS,
            'readUpTo' => $active ? $this->readUpTo($active, $me->id) : 0,
            'watermarks' => $active ? $this->watermarks($active, $me->id) : [],
            'peerOnline' => $peer ? $peer->isOnline() : false,
            'peerSeen' => $peer ? ($peer->isOnline() ? 'Online' : ($peer->lastSeenHuman() ?? 'Offline')) : null,
            'onlineCount' => $active && $active->isGroup()
                ? $active->participants->filter(fn ($p) => $p->admin_id !== $me->id && optional($p->admin)->isOnline())->count()
                : 0,
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
        foreach ($files as $file) {
            if (! in_array(strtolower($file->getClientOriginalExtension()), self::ALLOWED_EXT, true)) {
                throw ValidationException::withMessages(['attachments' => 'That file type is not allowed.']);
            }
        }

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
        $this->touchConversation($conv, $msg);
        $this->markReadTo($conv, $me->id, $msg->id);   // I've read my own message
        $conv->participants()->where('admin_id', $me->id)->update(['typing_at' => null]);

        $msg->load('sender', 'replyTo.sender', 'attachments');

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $this->present($msg, $me->id), 'conversation_id' => $conv->id]);
        }

        return redirect()->route('admin.team-messages.index', ['c' => $conv->id]);
    }

    /** Poll: new messages, read watermarks, reaction/deletion state, typing + presence. */
    public function thread(Request $request)
    {
        $me   = Auth::guard('admin')->user();
        $conv = $this->findConversation($me, (int) $request->query('c'));
        $after = (int) $request->query('after', 0);

        $msgs = $conv->messages()->with('sender', 'replyTo.sender', 'attachments')
            ->where('id', '>', $after)->orderBy('id')->get();

        $this->markRead($conv, $me);

        $states = $conv->messages()->latest('id')->limit(80)->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'deleted' => (bool) $m->deleted_at, 'reactions' => $this->reactionsOf($m, $me->id),
            ])->values();

        $others = $conv->participants->where('admin_id', '!=', $me->id);

        $typing = $others
            ->filter(fn ($p) => $p->typing_at && $p->typing_at->gt(now()->subSeconds(6)))
            ->map(fn ($p) => $this->senderInfo($p->admin)['first'])->values();

        return response()->json([
            'messages'   => $msgs->map(fn ($m) => $this->present($m, $me->id))->values(),
            'readUpTo'   => $this->readUpTo($conv, $me->id),
            'states'     => $states,
            'typing'     => $typing,
            'watermarks' => $this->watermarks($conv, $me->id),
            'presence'   => $this->presenceOf($others),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
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
            'emoji'      => ['nullable', 'string', Rule::in(self::EMOJI)],
        ]);

        $msg = $this->participantMessage($me, $data['message_id']);
        abort_if($msg->isSystem(), 404);

        $r = $msg->reactions ?? [];
        $emoji = $data['emoji'] ?? null;
        if ($emoji === null || ($r[$me->id] ?? null) === $emoji) {
            unset($r[$me->id]);
        } else {
            $r[$me->id] = $emoji;
        }
        $msg->reactions = $r ?: null;
        $msg->save();

        return response()->json(['ok' => true, 'reactions' => $this->reactionsOf($msg, $me->id)]);
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
        abort_unless($message->sender_id === $me->id && ! $message->isSystem(), 403);

        foreach ($message->attachments as $att) {
            Storage::disk('private')->delete($att->disk_path);
        }
        $message->attachments()->delete();

        $message->body = '';
        $message->reactions = null;
        $message->deleted_at = now();
        $message->save();

        return response()->json(['ok' => true, 'id' => $message->id]);
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

        $headers = ['Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('dl')
            ? $disk->download($attachment->disk_path, $attachment->original_name, $headers)
            : $disk->response($attachment->disk_path, $attachment->original_name, $headers);
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
        if ($m->isSystem()) {
            return ['id' => $m->id, 'system' => true, 'body' => $m->body,
                    'at' => $m->created_at->timezone(self::TZ)->format('M j · g:i A')];
        }

        return [
            'id'          => $m->id,
            'system'      => false,
            'mine'        => $m->sender_id === $meId,
            'body'        => $m->deleted_at ? '' : $m->body,
            'at'          => $m->created_at->timezone(self::TZ)->format('M j · g:i A'),
            'deleted'     => (bool) $m->deleted_at,
            'forwarded'   => (bool) $m->forwarded,
            'reactions'   => $this->reactionsOf($m, $meId),
            'reply'       => $this->replySnippet($m, $meId),
            'attachments' => $m->deleted_at ? [] : $this->attachmentsOf($m),
            'sender'      => $this->senderInfo($m->sender),
        ];
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
            'author' => $r->sender_id === $meId ? 'You' : ($r->sender->full_name ?? 'Teammate'),
            'text'   => $r->deleted_at ? 'Deleted message' : Str::limit($r->body !== '' ? $r->body : '📎 Attachment', 90),
        ];
    }

    // ---------------------------------------------------------------- sidebar items

    private function dmItem(Admin $me, Admin $peer, ?Conversation $c, $lastMsgs, array $unread): array
    {
        $last = $c && $c->last_message_id ? $lastMsgs->get($c->last_message_id) : null;

        return [
            'kind'            => 'dm',
            'conversation_id' => $c?->id,
            'peer_id'         => $peer->id,
            'name'            => $peer->full_name,
            'is_group'        => false,
            'icon'            => null,
            'peer'            => $peer,
            'online'          => $peer->isOnline(),
            'href'            => $c ? ['c' => $c->id] : ['with' => $peer->id],
            'preview'         => $last ? $this->previewOf($me, $c, $last, false) : null,
            'unread'          => $c ? ($unread[$c->id] ?? 0) : 0,
            'ts'              => $last ? $last->created_at->timestamp : 0,
            'active_key'      => $c ? ('c' . $c->id) : ('u' . $peer->id),
        ];
    }

    private function groupItem(Admin $me, Conversation $c, $lastMsgs, array $unread): array
    {
        $last = $c->last_message_id ? $lastMsgs->get($c->last_message_id) : null;

        return [
            'kind'            => 'group',
            'conversation_id' => $c->id,
            'peer_id'         => null,
            'name'            => $c->name,
            'is_group'        => true,
            'icon'            => $c->icon ?: '💬',
            'peer'            => null,
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
        $conv = Conversation::where('type', 'dm')->where('data_owner_id', $me->dataOwnerId())
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $me->id))
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $peer->id))
            ->first();

        if (! $conv) {
            $conv = Conversation::create(['type' => 'dm', 'data_owner_id' => $me->dataOwnerId(), 'created_by' => $me->id]);
            $conv->participants()->createMany([
                ['admin_id' => $me->id, 'role' => 'member', 'joined_at' => now()],
                ['admin_id' => $peer->id, 'role' => 'member', 'joined_at' => now()],
            ]);
            $conv->load('participants.admin');
        }

        return $conv;
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
        $conv->update(['last_message_id' => $msg->id, 'last_message_at' => $msg->created_at]);
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
            if (! in_array($ext, self::ALLOWED_EXT, true)) continue;

            $w = $h = null;
            if (in_array($ext, self::IMAGE_EXT, true)) {
                $dims = @getimagesize($file->getRealPath());
                if ($dims) { $w = $dims[0]; $h = $dims[1]; }
            }

            $path = $file->storeAs('team-chat/' . $ownerId, Str::uuid() . '.' . $ext, 'private');

            $msg->attachments()->create([
                'disk_path' => $path, 'original_name' => $file->getClientOriginalName(),
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
