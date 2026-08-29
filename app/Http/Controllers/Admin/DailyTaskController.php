<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\RoundSelection;
use App\Support\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Daily Task — a per-business-owner report of the clients a VA worked during a
 * SHIFT (the team's 4 PM → 10 AM PKT work-day): those a VA SELECTED a new round
 * for (the round strip changed). Driven by round_selections, never by process
 * steps — so filling missing steps ("Mark All Incomplete Complete") credits no
 * one. Bucketed by the shared WorkDay window and any of the last 15 shifts can
 * be pulled up by date.
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

        $roundLabel = fn (int $round) => EndUser::ROUND_OPTIONS[$round - 1] ?? "Round {$round}";

        // Rounds SELECTED within this shift's window (real created_at). Selecting a
        // round = starting to work it — the only thing that counts here.
        RoundSelection::where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->with(['endUser.client', 'selectedBy'])
            ->get()
            ->each(function (RoundSelection $sel) use ($ownerId, &$groups, $roundLabel) {
                $eu = $sel->endUser;
                if (!$eu || !$eu->client || $eu->client->admin_id !== $ownerId) {
                    return;
                }
                $bo = $eu->client;
                $groups[$bo->id] ??= ['name' => $bo->business_name, 'clients' => []];
                $row = &$groups[$bo->id]['clients'][$eu->id];
                $row ??= ['name' => $eu->full_name, 'vas' => [], 'tasks' => []];
                if ($va = $sel->selectedBy?->full_name) {
                    $row['vas'][$va] = true;
                }
                $row['tasks'][$roundLabel((int) $sel->round)] = true;
                unset($row);
            });

        uasort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        foreach ($groups as &$g) {
            uasort($g['clients'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        unset($g);

        $clientCount = collect($groups)->sum(fn ($g) => count($g['clients']));
        $roundCount  = collect($groups)->sum(
            fn ($g) => collect($g['clients'])->sum(fn ($c) => count($c['tasks']))
        );

        return view($this->adminView('admin.daily-task'), [
            'groups'      => $groups,
            'clientCount' => $clientCount,
            'roundCount'  => $roundCount,
            'workDate'    => $date,
            'workLabel'   => WorkDay::label($date),
            'isCurrent'   => WorkDay::isCurrent($date),
            'recentDays'  => collect(WorkDay::recent(15))->map(fn ($d) => ['date' => $d, 'label' => WorkDay::label($d)])->all(),
            'generatedAt' => Carbon::now()->timezone(WorkDay::TZ),
        ]);
    }
}
