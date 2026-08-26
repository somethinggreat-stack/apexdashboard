<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tasks View — the business owner's own 30-day work log, built from the exact
 * same signal as the internal Daily Task page: a client counts on a day when a
 * Round-N Week-1 process step was logged (i.e. a round was started). Each task
 * is filed under the step's real logged time (created_at) in ET, so it carries
 * the same day + timestamp it showed under on our side.
 *
 * Owner-facing, so VA/admin names are NEVER exposed here — only the client, the
 * round, the step performed, and when it was logged.
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
        // entry = ['eu' => id, 'name' => , 'round' => '2nd', 'tasks' => [label=>true], 'at' => Carbon]
        $days = [];

        $roundWord = function (int $round) {
            $label = EndUser::ROUND_OPTIONS[$round - 1] ?? "Round {$round}";
            return Str::before($label, ' Round') ?: "Round {$round}";
        };

        // Week-1 steps logged in the window (real created_at). Week 1 of any round
        // = that round was started — the only thing that counts as a task, exactly
        // like the internal Daily Task page.
        ProcessStep::forClient($clientId)
            ->where('created_at', '>=', $cutoff)
            ->where('week', 1)
            ->with('endUser')
            ->orderBy('created_at')
            ->get()
            ->each(function (ProcessStep $step) use (&$days, $roundWord) {
                $eu = $step->endUser;
                if (!$eu) {
                    return;
                }
                $at     = $step->created_at->copy()->timezone(self::TZ);
                $dayKey = $at->format('Y-m-d');
                $days[$dayKey] ??= ['date' => $at->copy()->startOfDay(), 'entries' => []];

                // One entry per (client, round) per day — a single round-start event.
                $ek    = $eu->id . '-' . $step->round;
                $entry = &$days[$dayKey]['entries'][$ek];
                $entry ??= [
                    'eu'    => $eu->id,
                    'name'  => $eu->full_name,
                    'round' => $roundWord((int) $step->round),
                    'tasks' => [],
                    'at'    => $at,
                ];
                if ($label = $step->step_type_label) {
                    $entry['tasks'][$label] = true;   // dedup identical step labels
                }
                if ($at->lt($entry['at'])) {
                    $entry['at'] = $at;               // earliest logged time that day
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
        $stepsLogged   = 0;
        foreach ($days as $d) {
            foreach ($d['entries'] as $e) {
                $roundsStarted++;
                $stepsLogged += count($e['tasks']);
                $clientIds[$e['eu']] = true;
            }
        }

        return view('client.tasks.index', [
            'days'          => $days,
            'windowDays'    => self::WINDOW_DAYS,
            'clientsWorked' => count($clientIds),
            'roundsStarted' => $roundsStarted,
            'stepsLogged'   => $stepsLogged,
            'generatedAt'   => Carbon::now(),
        ]);
    }
}
