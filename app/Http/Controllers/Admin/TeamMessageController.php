<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\TeamMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Team Chat — internal direct messages between the org's admins (super + VAs).
 * Everyone messages within their own org only (dataOwnerId), never across orgs,
 * and never business owners.
 */
class TeamMessageController extends Controller
{
    private const TZ = 'America/New_York';

    /** Emoji a message can be reacted with (WhatsApp-style). */
    private const EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    public function index(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $members = $this->teammates($me)->orderBy('full_name')->get();

        // Unread count per sender (their messages to me, not yet read).
        $unread = TeamMessage::where('recipient_id', $me->id)->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) AS c')->groupBy('sender_id')->pluck('c', 'sender_id');

        // Last message in each thread — the WhatsApp-style contact preview.
        $previews = [];
        foreach ($members as $m) {
            $last = TeamMessage::between($me->id, $m->id)->latest('id')->first();
            if ($last) {
                $previews[$m->id] = $this->preview($last, $me->id);
            }
        }

        // Order contacts like a messenger: most recent conversation first,
        // teammates with no messages yet fall to the bottom (kept alphabetical).
        $members = $members->sortBy(fn ($m) => isset($previews[$m->id])
            ? -$previews[$m->id]['ts'] : PHP_INT_MAX)->values();

        $withId   = (int) $request->query('with');
        $active   = $withId ? $members->firstWhere('id', $withId) : null;
        $messages = collect();

        if ($active) {
            $messages = TeamMessage::between($me->id, $active->id)
                ->with('replyTo.sender')->orderBy('id')->get();
            $this->markRead($me->id, $active->id);
        }

        return view($this->adminView('admin.team-messages.index'), [
            'me' => $me, 'members' => $members, 'unread' => $unread, 'previews' => $previews,
            'active' => $active, 'messages' => $messages, 'tz' => self::TZ, 'emoji' => self::EMOJI,
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $data = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'body'         => ['required', 'string', 'max:5000'],
            'reply_to_id'  => ['nullable', 'integer'],
        ]);

        // The recipient must be a teammate in my org (never outside it, never me).
        $recipient = $this->teammates($me)->findOrFail($data['recipient_id']);

        // A reply must point at a message inside this very thread.
        $replyToId = null;
        if (! empty($data['reply_to_id'])) {
            $quoted = TeamMessage::between($me->id, $recipient->id)->find($data['reply_to_id']);
            $replyToId = $quoted?->id;
        }

        $msg = TeamMessage::create([
            'sender_id'    => $me->id,
            'recipient_id' => $recipient->id,
            'reply_to_id'  => $replyToId,
            'body'         => trim($data['body']),
        ]);
        $msg->load('replyTo.sender');

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $this->present($msg, $me->id)]);
        }

        return redirect()->route('admin.team-messages.index', ['with' => $recipient->id]);
    }

    /** Add / change / remove my emoji reaction on a message I can see. */
    public function react(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'emoji'      => ['nullable', 'string', Rule::in(self::EMOJI)],
        ]);

        $msg = $this->participantMessage($me, $data['message_id']);

        $r = $msg->reactions ?? [];
        $emoji = $data['emoji'] ?? null;
        if ($emoji === null || ($r[$me->id] ?? null) === $emoji) {
            unset($r[$me->id]);           // tapping the same emoji again clears it
        } else {
            $r[$me->id] = $emoji;         // one reaction per person, like WhatsApp
        }
        $msg->reactions = $r ?: null;
        $msg->save();

        return response()->json(['ok' => true, 'reactions' => $this->reactionsOf($msg, $me->id)]);
    }

    /** Forward a message's text to another teammate. */
    public function forward(Request $request)
    {
        $me = Auth::guard('admin')->user();
        $data = $request->validate([
            'message_id'   => ['required', 'integer'],
            'recipient_id' => ['required', 'integer'],
        ]);

        $source = $this->participantMessage($me, $data['message_id']);
        abort_if((bool) $source->deleted_at, 404);
        $to = $this->teammates($me)->findOrFail($data['recipient_id']);

        TeamMessage::create([
            'sender_id'    => $me->id,
            'recipient_id' => $to->id,
            'body'         => $source->body,
            'forwarded'    => true,
        ]);

        return response()->json(['ok' => true, 'with' => $to->id, 'name' => $to->full_name]);
    }

    /** Delete for everyone — keep the row as a tombstone (only the sender may). */
    public function destroy(Request $request, TeamMessage $message)
    {
        $me = Auth::guard('admin')->user();
        abort_unless($message->sender_id === $me->id, 403);

        $message->body = '';
        $message->reactions = null;
        $message->deleted_at = now();
        $message->save();

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    /** Poll: new messages after a given id, plus read watermark and live state of recent messages. */
    public function thread(Request $request)
    {
        $me   = Auth::guard('admin')->user();
        $with = $this->teammates($me)->findOrFail((int) $request->query('with'));
        $after = (int) $request->query('after', 0);

        $msgs = TeamMessage::between($me->id, $with->id)
            ->with('replyTo.sender')->where('id', '>', $after)->orderBy('id')->get();

        $this->markRead($me->id, $with->id);

        $readUpTo = (int) (TeamMessage::where('sender_id', $me->id)->where('recipient_id', $with->id)
            ->whereNotNull('read_at')->max('id') ?? 0);

        // Live state (reactions / deleted) for recent messages already on screen.
        $states = TeamMessage::between($me->id, $with->id)->latest('id')->limit(80)->get()
            ->map(fn ($m) => [
                'id'        => $m->id,
                'deleted'   => (bool) $m->deleted_at,
                'reactions' => $this->reactionsOf($m, $me->id),
            ])->values();

        return response()->json([
            'messages' => $msgs->map(fn ($m) => $this->present($m, $me->id))->values(),
            'readUpTo' => $readUpTo,
            'states'   => $states,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function present(TeamMessage $m, int $meId): array
    {
        return [
            'id'        => $m->id,
            'mine'      => $m->sender_id === $meId,
            'body'      => $m->deleted_at ? '' : $m->body,
            'at'        => $m->created_at->timezone(self::TZ)->format('M j · g:i A'),
            'deleted'   => (bool) $m->deleted_at,
            'forwarded' => (bool) $m->forwarded,
            'reactions' => $this->reactionsOf($m, $meId),
            'reply'     => $this->replySnippet($m, $meId),
        ];
    }

    /** Aggregate reactions to [{emoji, count, mine}], most-used first. */
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

    /** The quoted-reply snippet, if this message is a reply. */
    private function replySnippet(TeamMessage $m, int $meId): ?array
    {
        $r = $m->replyTo;
        if (! $r) return null;

        return [
            'author' => $r->sender_id === $meId ? 'You' : ($r->sender->full_name ?? 'Teammate'),
            'text'   => $r->deleted_at ? 'Deleted message' : Str::limit($r->body, 90),
        ];
    }

    /** Fetch a message I'm a participant in (sender or recipient), else 404. */
    private function participantMessage(Admin $me, int $id): TeamMessage
    {
        return TeamMessage::where('id', $id)
            ->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))
            ->firstOrFail();
    }

    /** Contact-list preview: last line, when, mine?, and (if mine) whether they've read it. */
    private function preview(TeamMessage $m, int $meId): array
    {
        $mine = $m->sender_id === $meId;

        return [
            'body' => $m->deleted_at ? 'This message was deleted' : $m->body,
            'mine' => $mine,
            'read' => $mine ? ! is_null($m->read_at) : true,
            'ts'   => $m->created_at->timestamp,
            'at'   => $this->shortTime($m->created_at),
        ];
    }

    /** Messenger-style short timestamp: time today, "Yesterday", weekday this week, else date. */
    private function shortTime(\Illuminate\Support\Carbon $dt): string
    {
        $dt  = $dt->copy()->timezone(self::TZ);
        $now = now(self::TZ);

        if ($dt->isSameDay($now))                    return $dt->format('g:i A');
        if ($dt->isSameDay($now->copy()->subDay()))  return 'Yesterday';
        if ($dt->greaterThan($now->copy()->subDays(6)->startOfDay())) return $dt->format('l');

        return $dt->format('M j');
    }

    private function markRead(int $meId, int $fromId): void
    {
        TeamMessage::where('sender_id', $fromId)->where('recipient_id', $meId)
            ->whereNull('read_at')->update(['read_at' => now()]);
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
