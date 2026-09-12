<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\TeamMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Team Chat — internal direct messages between the org's admins (super + VAs).
 * Everyone messages within their own org only (dataOwnerId), never across orgs,
 * and never business owners.
 */
class TeamMessageController extends Controller
{
    private const TZ = 'America/New_York';

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
            $messages = TeamMessage::between($me->id, $active->id)->orderBy('id')->get();
            $this->markRead($me->id, $active->id);
        }

        return view($this->adminView('admin.team-messages.index'), [
            'me' => $me, 'members' => $members, 'unread' => $unread, 'previews' => $previews,
            'active' => $active, 'messages' => $messages, 'tz' => self::TZ,
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('admin')->user();

        $data = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'body'         => ['required', 'string', 'max:5000'],
        ]);

        // The recipient must be a teammate in my org (never outside it, never me).
        $recipient = $this->teammates($me)->findOrFail($data['recipient_id']);

        $msg = TeamMessage::create([
            'sender_id'    => $me->id,
            'recipient_id' => $recipient->id,
            'body'         => trim($data['body']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $this->present($msg, $me->id),
            ]);
        }

        return redirect()->route('admin.team-messages.index', ['with' => $recipient->id]);
    }

    /** Poll: new messages in a thread after a given id (also marks them read). */
    public function thread(Request $request)
    {
        $me   = Auth::guard('admin')->user();
        $with = $this->teammates($me)->findOrFail((int) $request->query('with'));
        $after = (int) $request->query('after', 0);

        $msgs = TeamMessage::between($me->id, $with->id)
            ->where('id', '>', $after)->orderBy('id')->get();

        $this->markRead($me->id, $with->id);

        // Highest id of MY messages the other person has now read — drives the live blue ticks.
        $readUpTo = (int) (TeamMessage::where('sender_id', $me->id)->where('recipient_id', $with->id)
            ->whereNotNull('read_at')->max('id') ?? 0);

        return response()->json([
            'messages' => $msgs->map(fn ($m) => $this->present($m, $me->id))->values(),
            'readUpTo' => $readUpTo,
        ]);
    }

    private function present(TeamMessage $m, int $meId): array
    {
        return [
            'id'   => $m->id,
            'mine' => $m->sender_id === $meId,
            'body' => $m->body,
            'at'   => $m->created_at->timezone(self::TZ)->format('M j · g:i A'),
        ];
    }

    /** Contact-list preview: last line, when, mine?, and (if mine) whether they've read it. */
    private function preview(TeamMessage $m, int $meId): array
    {
        $mine = $m->sender_id === $meId;

        return [
            'body' => $m->body,
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
