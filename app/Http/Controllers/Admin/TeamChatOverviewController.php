<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Conversation;
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
