<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The owner's view of Team Chat — super admin only, read-only, and invisible to everyone else.
 *
 * Inside the chat itself the super admin is just another participant: they cannot see a group
 * they were not added to, and they have no picture of who is talking to whom. This page gives
 * them that picture WITHOUT reading anyone's messages.
 *
 * Two rules hold this page together, and both are covered by tests:
 *
 *  1. **No message content, ever.** Only counts, times and names. No bodies, no previews, no
 *     file names. Private notes-to-self threads are not listed at all.
 *  2. **Nothing a VA could notice.** Every query here is a read. Opening this page must never
 *     mark anything read, add the owner to a conversation, write a system message or change a
 *     watermark — otherwise the team would see the owner's oversight from their own screens.
 */
class TeamChatOverviewController extends Controller
{
    /** Kept in step with the chat itself — every time shown here is Pakistan time. */
    private const TZ = TeamMessageController::TZ;

    public function index()
    {
        $me = Auth::guard('admin')->user();
        $ownerId = $me->dataOwnerId();

        $people = $this->people($ownerId);
        $convs  = Conversation::where('data_owner_id', $ownerId)
            ->with('participants')
            ->get();

        $convIds = $convs->pluck('id')->all();
        $msgStats  = $this->messageStats($convIds);
        $fileStats = $this->fileStats($convIds);

        $groups = [];
        $dms    = [];
        foreach ($convs as $c) {
            // A notes-to-self thread is a private notebook, not communication between people.
            if ($this->isSelfThread($c)) {
                continue;
            }

            $memberIds = $c->participants->pluck('admin_id')->all();
            $m = $msgStats[$c->id] ?? null;
            $f = $fileStats[$c->id] ?? null;

            $rowRows = [
                'id'       => $c->id,
                'messages' => (int) ($m->c ?? 0),
                'files'    => (int) ($f->c ?? 0),
                'bytes'    => (int) ($f->bytes ?? 0),
                'last'     => $this->when($m->last ?? null),
                'lastTs'   => $m->last ?? null,
                'mine'     => in_array($me->id, $memberIds, true),
            ];

            if ($c->isGroup()) {
                $groups[] = $rowRows + [
                    'name'    => $c->name ?: 'Group',
                    'icon'    => $c->icon ?: '💬',
                    'members' => $people->whereIn('id', $memberIds)->pluck('full_name')->values()->all(),
                    'creator' => optional($people->firstWhere('id', $c->created_by))->full_name,
                ];
            } else {
                $dms[] = $rowRows + [
                    'people' => $people->whereIn('id', $memberIds)->pluck('full_name')->values()->all(),
                ];
            }
        }

        // Busiest first; never-used conversations sink to the bottom.
        $byLast = fn ($a, $b) => strcmp((string) ($b['lastTs'] ?? ''), (string) ($a['lastTs'] ?? ''));
        usort($groups, $byLast);
        usort($dms, $byLast);

        return view('admin.team-messages.overview', [
            'me'         => $me,
            'groups'     => $groups,
            'dms'        => $dms,
            'people'     => $this->peopleRows($people, $convIds, $convs),
            'announcements' => $this->announcements($me, $people->keyBy('id')),
            'totals'     => [
                'groups'   => count($groups),
                'dms'      => count(array_filter($dms, fn ($d) => $d['messages'] > 0)),
                'messages' => collect($msgStats)->sum('c'),
                'files'    => collect($fileStats)->sum('c'),
                'bytes'    => collect($fileStats)->sum('bytes'),
            ],
            'retentionDays' => 7,
        ]);
    }

    /**
     * Every file that has gone out through the chat (step 4). Names, sizes, who sent them and
     * where — the compliance view, because "which client files left this company, and to whom"
     * is a question the owner has to be able to answer.
     *
     * It deliberately stops at the listing: opening a file still goes through the ordinary
     * attachment route, which requires being in the conversation. Seeing that a file exists is
     * not the same as reading a private chat, and the owner can always join a group to open it.
     */
    public function files()
    {
        $me = Auth::guard('admin')->user();
        $ownerId = $me->dataOwnerId();

        $convs = Conversation::where('data_owner_id', $ownerId)->with('participants')->get()->keyBy('id');
        $people = $this->people($ownerId)->keyBy('id');

        $rows = DB::table('message_attachments as a')
            ->join('team_messages as m', 'm.id', '=', 'a.team_message_id')
            ->whereIn('m.conversation_id', $convs->keys()->all())
            ->whereNull('m.deleted_at')
            ->orderByDesc('a.id')->limit(500)
            ->get(['a.id', 'a.original_name', 'a.size', 'a.created_at', 'm.conversation_id', 'm.sender_id']);

        $files = $rows->map(function ($r) use ($convs, $people, $me) {
            $c = $convs[$r->conversation_id] ?? null;

            return [
                'name'   => $r->original_name,
                'size'   => (int) $r->size,
                'by'     => optional($people[$r->sender_id] ?? null)->full_name ?? 'Someone',
                'where'  => $c ? $this->label($c, $people) : 'Unknown',
                'when'   => $this->when($r->created_at),
                'mine'   => $c && $c->participants->contains('admin_id', $me->id),
            ];
        })->all();

        return view('admin.team-messages.overview-files', [
            'me' => $me, 'files' => $files, 'retentionDays' => 7,
        ]);
    }

    /**
     * Save a conversation to a file before the 7-day purge takes it (step 4).
     *
     * Only conversations the owner is actually in. Exporting a chat they are not part of would
     * be reading it through the back door, and the owner's own rule is that they do not read
     * what the team says to each other. To export a group they are not in, they join it first —
     * which the group is told about.
     */
    public function export(Request $request, Conversation $conversation)
    {
        $me = Auth::guard('admin')->user();
        abort_unless($conversation->data_owner_id === $me->dataOwnerId(), 403);
        abort_unless($conversation->participants()->where('admin_id', $me->id)->exists(), 403);

        $from = $request->query('from');
        $to   = $request->query('to');

        $messages = $conversation->messages()->with('sender', 'attachments')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('id')->get();

        $name = 'chat-' . ($conversation->isGroup()
            ? \Illuminate\Support\Str::slug($conversation->name ?: 'group')
            : 'direct-' . $conversation->id) . '-' . now(self::TZ)->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($messages) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel opens the names and emoji correctly
            fputcsv($out, ['Time (Pakistan)', 'From', 'Message', 'Files']);
            foreach ($messages as $m) {
                fputcsv($out, [
                    $m->created_at->timezone(self::TZ)->format('Y-m-d g:i A'),
                    $m->deleted_at ? '—' : (optional($m->sender)->full_name ?? 'Someone'),
                    $m->deleted_at ? '[deleted]' : ($m->isSystem() ? '[' . $m->body . ']' : $m->body),
                    $m->deleted_at ? '' : $m->attachments->pluck('original_name')->implode(', '),
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A human label for a conversation, without leaking anything that was said in it. */
    private function label(Conversation $c, $people): string
    {
        if ($c->isGroup()) {
            return $c->name ?: 'Group';
        }

        $names = $c->participants->map(fn ($p) => optional($people[$p->admin_id] ?? null)->full_name)
            ->filter()->values();

        return $names->count() > 1 ? $names->implode(' ↔ ') : ($names->first() . ' (notes)');
    }

    /**
     * The owner's announcements and who has actually read each one. A person has read it when
     * their side of that direct chat has been read past the copy that was sent to them.
     */
    private function announcements(Admin $me, $people): array
    {
        $copies = \App\Models\TeamMessage::whereNotNull('announcement_id')
            ->where('sender_id', $me->id)
            ->with('conversation.participants')
            ->orderByDesc('id')->limit(200)->get()
            ->groupBy('announcement_id');

        return $copies->take(10)->map(function ($group) use ($me, $people) {
            $first = $group->first();
            $read = [];
            $unread = [];
            foreach ($group as $copy) {
                $conv = $copy->conversation;
                if (! $conv) {
                    continue;
                }
                foreach ($conv->participants as $p) {
                    if ($p->admin_id === $me->id) {
                        continue;
                    }
                    $name = optional($people[$p->admin_id] ?? null)->full_name ?? 'Someone';
                    if ((int) $p->last_read_message_id >= $copy->id) {
                        $read[] = $name;
                    } else {
                        $unread[] = $name;
                    }
                }
            }
            sort($read);
            sort($unread);

            return [
                'body'   => \Illuminate\Support\Str::limit($first->body, 300),
                'when'   => $this->when($first->created_at),
                'read'   => $read,
                'unread' => $unread,
            ];
        })->values()->all();
    }

    /** Everyone in this org who can use the chat (same rule the chat's own sidebar uses). */
    private function people(int $ownerId)
    {
        return Admin::where(fn ($q) => $q->where('id', $ownerId)->orWhere('parent_admin_id', $ownerId))
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'leads'))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role', 'last_seen_at']);
    }

    /** Per-conversation message count + latest message time. Counts only, never bodies. */
    private function messageStats(array $convIds)
    {
        if (! $convIds) {
            return collect();
        }

        return DB::table('team_messages')
            ->whereIn('conversation_id', $convIds)
            ->where('type', 'text')->whereNull('deleted_at')
            ->groupBy('conversation_id')
            ->selectRaw('conversation_id, COUNT(*) AS c, MAX(created_at) AS last')
            ->get()->keyBy('conversation_id');
    }

    /** Per-conversation file count + total size. File NAMES are deliberately not selected. */
    private function fileStats(array $convIds)
    {
        if (! $convIds) {
            return collect();
        }

        return DB::table('message_attachments as a')
            ->join('team_messages as m', 'm.id', '=', 'a.team_message_id')
            ->whereIn('m.conversation_id', $convIds)->whereNull('m.deleted_at')
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id AS conversation_id, COUNT(*) AS c, COALESCE(SUM(a.size), 0) AS bytes')
            ->get()->keyBy('conversation_id');
    }

    /** Per-person activity: how much they sent, where they are, when they were last around. */
    private function peopleRows($people, array $convIds, $convs): array
    {
        $sent = $convIds
            ? DB::table('team_messages')->whereIn('conversation_id', $convIds)
                ->where('type', 'text')->whereNull('deleted_at')
                ->groupBy('sender_id')->selectRaw('sender_id, COUNT(*) AS c, MAX(created_at) AS last')
                ->get()->keyBy('sender_id')
            : collect();

        $groupCount = [];
        foreach ($convs as $c) {
            if (! $c->isGroup()) {
                continue;
            }
            foreach ($c->participants as $p) {
                $groupCount[$p->admin_id] = ($groupCount[$p->admin_id] ?? 0) + 1;
            }
        }

        return $people->map(function (Admin $a) use ($sent, $groupCount) {
            $s = $sent[$a->id] ?? null;

            return [
                'id'       => $a->id,
                'name'     => $a->full_name,
                'role'     => $a->role === 'super' ? 'Owner' : 'VA',
                'messages' => (int) ($s->c ?? 0),
                'groups'   => $groupCount[$a->id] ?? 0,
                'lastMsg'  => $this->when($s->last ?? null),
                'online'   => $a->isOnline(),
                'lastSeen' => $a->last_seen_at ? $a->last_seen_at->diffForHumans() : 'never',
            ];
        })->sortByDesc('messages')->values()->all();
    }

    private function isSelfThread(Conversation $c): bool
    {
        return str_starts_with((string) $c->dm_key, 'self:') || $c->participants->count() < 2;
    }

    /** A timestamp as the team reads it: Pakistan time. */
    private function when($raw): ?string
    {
        if (! $raw) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($raw)->timezone(self::TZ)->format('M j · g:i A');
    }
}
