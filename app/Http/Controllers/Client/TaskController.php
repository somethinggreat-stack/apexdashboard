<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\RoundSelection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tasks View — the business owner's own 30-day work log, built from the exact
 * same signal as the internal Daily Task page: a client counts on a day when a
 * VA SELECTED a new round for it (the round strip changed). Each entry is filed
 * under the real selection time (created_at) in ET, so it carries the same day +
 * timestamp it showed under on our side.
 *
 * Driven by round_selections, never process steps — so filling missing steps
 * never shows up here. Owner-facing, so VA/admin names are NEVER exposed — only
 * the client, the round, and when it was selected.
 */
class TaskController extends Controller
{
    private const WINDOW_DAYS = 30;
    private const TZ = 'America/New_York';

    public function index()
    {
        $clientId = Auth::guard('client')->id();
        $cutoff   = Carbon::now()->subDays(self::WINDOW_DAYS);

        // 'Y-m-d' (ET) => ['date' => Carbon, 'entries' => [ euId-round => entry ]]
        $days = [];

        $roundWord = function (int $round) {
            $label = EndUser::ROUND_OPTIONS[$round - 1] ?? "Round {$round}";
            return Str::before($label, ' Round') ?: "Round {$round}";
        };

        RoundSelection::whereHas('endUser', fn ($q) => $q->where('client_id', $clientId))
            ->where('created_at', '>=', $cutoff)
            ->with('endUser')
            ->orderBy('created_at')
            ->get()
            ->each(function (RoundSelection $sel) use (&$days, $roundWord) {
                $eu = $sel->endUser;
                if (!$eu) {
                    return;
                }
                $at     = $sel->created_at->copy()->timezone(self::TZ);
                $dayKey = $at->format('Y-m-d');
                $days[$dayKey] ??= ['date' => $at->copy()->startOfDay(), 'entries' => []];

                // One entry per (client, round) per day — a single round-start event.
                $ek    = $eu->id . '-' . $sel->round;
                $entry = &$days[$dayKey]['entries'][$ek];
                $entry ??= [
                    'eu'    => $eu->id,
                    'name'  => $eu->full_name,
                    'round' => $roundWord((int) $sel->round),
                    'at'    => $at,
                ];
                if ($at->lt($entry['at'])) {
                    $entry['at'] = $at;               // earliest selection time that day
                }
                unset($entry);
            });

        // Newest day first; within a day, newest entry first.
        krsort($days);
        foreach ($days as &$d) {
            uasort($d['entries'], fn ($a, $b) => $b['at'] <=> $a['at']);
        }
        unset($d);

        // Headline tiles for the window.
        $clientIds     = [];
        $roundsStarted = 0;
        foreach ($days as $d) {
            foreach ($d['entries'] as $e) {
                $roundsStarted++;
                $clientIds[$e['eu']] = true;
            }
        }

        return view('client.tasks.index', [
            'days'          => $days,
            'windowDays'    => self::WINDOW_DAYS,
            'clientsWorked' => count($clientIds),
            'roundsStarted' => $roundsStarted,
            'generatedAt'   => Carbon::now(),
        ]);
    }
}
