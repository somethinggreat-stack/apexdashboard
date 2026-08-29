<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\RoundSelection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tasks View (internal) — the selected business owner's 30-day work log, built
 * from the same signal as the Daily Task page: a client counts on the day a VA
 * SELECTED a new round for it (the round strip changed). Each entry is filed
 * under the real selection time (created_at) in ET.
 *
 * Driven by round_selections, never process steps — so filling missing steps
 * ("Mark All Incomplete Complete") never shows anyone as having worked a client.
 * Unlike the owner-facing Tasks View, this one DOES surface the VA who selected
 * each round (super-admin/VA side only).
 */
class TaskController extends Controller
{
    private const WINDOW_DAYS = 30;
    private const TZ = 'America/New_York';

    public function index()
    {
        $clientId = session('selected_client_id');
        $cutoff   = Carbon::now()->subDays(self::WINDOW_DAYS);

        // 'Y-m-d' (ET) => ['date' => Carbon, 'entries' => [ euId-round => entry ]]
        $days = [];

        $roundWord = function (int $round) {
            $label = EndUser::ROUND_OPTIONS[$round - 1] ?? "Round {$round}";
            return Str::before($label, ' Round') ?: "Round {$round}";
        };

        RoundSelection::whereHas('endUser', fn ($q) => $q->where('client_id', $clientId))
            ->where('created_at', '>=', $cutoff)
            ->with(['endUser', 'selectedBy'])
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

                $ek    = $eu->id . '-' . $sel->round;
                $entry = &$days[$dayKey]['entries'][$ek];
                $entry ??= [
                    'eu'    => $eu->id,
                    'name'  => $eu->full_name,
                    'round' => $roundWord((int) $sel->round),
                    'vas'   => [],
                    'at'    => $at,
                ];
                if ($va = $sel->selectedBy?->full_name) {
                    $entry['vas'][$va] = true;
                }
                if ($at->lt($entry['at'])) {
                    $entry['at'] = $at;
                }
                unset($entry);
            });

        krsort($days);
        foreach ($days as &$d) {
            uasort($d['entries'], fn ($a, $b) => $b['at'] <=> $a['at']);
        }
        unset($d);

        $clientIds     = [];
        $roundsStarted = 0;
        foreach ($days as $d) {
            foreach ($d['entries'] as $e) {
                $roundsStarted++;
                $clientIds[$e['eu']] = true;
            }
        }

        return view($this->adminView('admin.tasks.index'), [
            'days'          => $days,
            'windowDays'    => self::WINDOW_DAYS,
            'clientsWorked' => count($clientIds),
            'roundsStarted' => $roundsStarted,
            'generatedAt'   => Carbon::now(),
        ]);
    }
}
