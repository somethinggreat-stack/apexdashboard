<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessStep;
use App\Support\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Daily Task — a per-business-owner report of the clients a VA worked during a
 * SHIFT (the team's 4 PM → 10 AM PKT work-day): those that had a Round-N Week-1
 * process step logged (a round was started). Bucketed by the shared WorkDay
 * window so it always matches the EOD report, and any of the last 15 shifts can
 * be pulled up by date — nothing rolls off a 12-hour peephole any more.
 */
class DailyTaskController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        // Which shift are we looking at? Default = the current (in-progress) one.
        $date            = WorkDay::normalise($request->query('date'));
        [$start, $end]   = WorkDay::bounds($date);

        // owner id => ['name' => ..., 'clients' => [euId => ['name'=>, 'vas'=>[], 'tasks'=>[]]]]
        $groups = [];

        $taskLabel = function (ProcessStep $step) {
            $roundLabel = \App\Models\EndUser::ROUND_OPTIONS[$step->round - 1] ?? "Round {$step->round}";
            return \Illuminate\Support\Str::before($roundLabel, ' Round') . ' · ' . $step->step_type_label;
        };

        // Week-1 steps logged within this shift's window (real created_at). Week 1
        // of any round = that round was started — the only thing that counts.
        ProcessStep::where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where('week', 1)
            ->with(['endUser.client', 'createdBy'])
            ->get()
            ->each(function (ProcessStep $step) use ($ownerId, &$groups, $taskLabel) {
                $eu = $step->endUser;
                if (!$eu || !$eu->client || $eu->client->admin_id !== $ownerId) {
                    return;
                }
                $bo = $eu->client;
                $groups[$bo->id] ??= ['name' => $bo->business_name, 'clients' => []];
                $row = &$groups[$bo->id]['clients'][$eu->id];
                $row ??= ['name' => $eu->full_name, 'vas' => [], 'tasks' => []];
                if ($va = $step->createdBy?->full_name) {
                    $row['vas'][$va] = true;
                }
                $row['tasks'][$taskLabel($step)] = true;
                unset($row);
            });

        uasort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        foreach ($groups as &$g) {
            uasort($g['clients'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        unset($g);

        $clientCount = collect($groups)->sum(fn ($g) => count($g['clients']));
        $stepCount   = collect($groups)->sum(
            fn ($g) => collect($g['clients'])->sum(fn ($c) => count($c['tasks']))
        );

        return view($this->adminView('admin.daily-task'), [
            'groups'      => $groups,
            'clientCount' => $clientCount,
            'stepCount'   => $stepCount,
            'workDate'    => $date,
            'workLabel'   => WorkDay::label($date),
            'isCurrent'   => WorkDay::isCurrent($date),
            'recentDays'  => collect(WorkDay::recent(15))->map(fn ($d) => ['date' => $d, 'label' => WorkDay::label($d)])->all(),
            'generatedAt' => Carbon::now()->timezone(WorkDay::TZ),
        ]);
    }
}
