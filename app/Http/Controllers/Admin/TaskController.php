<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tasks View (internal) — the selected business owner's 30-day work log, built
 * from the same signal as the Daily Task page: a client counts on a day when a
 * Round-N Week-1 process step was logged (a round was started). Each task is
 * filed under the step's real logged time (created_at) in ET.
 *
 * This is the internal twin of the owner-facing Tasks View, and unlike that one
 * it DOES surface the VA who logged each step (super-admin/VA side only).
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

        ProcessStep::forClient($clientId)
            ->where('created_at', '>=', $cutoff)
            ->where('week', 1)
            ->with(['endUser', 'createdBy'])
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

                $ek    = $eu->id . '-' . $step->round;
                $entry = &$days[$dayKey]['entries'][$ek];
                $entry ??= [
                    'eu'    => $eu->id,
                    'name'  => $eu->full_name,
                    'round' => $roundWord((int) $step->round),
                    'tasks' => [],
                    'vas'   => [],
                    'at'    => $at,
                ];
                if ($label = $step->step_type_label) {
                    $entry['tasks'][$label] = true;
                }
                if ($va = $step->createdBy?->full_name) {
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
        $stepsLogged   = 0;
        foreach ($days as $d) {
            foreach ($d['entries'] as $e) {
                $roundsStarted++;
                $stepsLogged += count($e['tasks']);
                $clientIds[$e['eu']] = true;
            }
        }

        return view($this->adminView('admin.tasks.index'), [
            'days'          => $days,
            'windowDays'    => self::WINDOW_DAYS,
            'clientsWorked' => count($clientIds),
            'roundsStarted' => $roundsStarted,
            'stepsLogged'   => $stepsLogged,
            'generatedAt'   => Carbon::now(),
        ]);
    }
}
