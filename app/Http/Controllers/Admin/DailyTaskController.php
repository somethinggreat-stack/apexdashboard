<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Daily Task — a per-business-owner report of the clients a VA actually worked
 * in the last 12 hours: those that had a Round-N Week-1 process step logged
 * (i.e. a round was started). Nothing else counts — moving a client to the
 * Clients/Done list is not "work" and never lands here. Grouped by owner so the
 * super admin can copy/paste the WhatsApp-style daily update and catch VAs who
 * logged nothing.
 */
class DailyTaskController extends Controller
{
    private const WINDOW_HOURS = 12;

    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $cutoff  = Carbon::now()->subHours(self::WINDOW_HOURS);

        // owner id => ['name' => ..., 'clients' => [euId => ['name'=>, 'vas'=>[], 'tasks'=>[]]]]
        $groups = [];

        // Ordinal-prefixed step label, e.g. "4th · CFPB (All 3B) & Innovis".
        $taskLabel = function (ProcessStep $step) {
            $roundLabel = \App\Models\EndUser::ROUND_OPTIONS[$step->round - 1] ?? "Round {$step->round}";
            return \Illuminate\Support\Str::before($roundLabel, ' Round') . ' · ' . $step->step_type_label;
        };

        // Week-1 process steps logged in the window (real created_at, not the
        // entered date). Week 1 of any round = that round was started — the only
        // thing that counts as a daily task. Follow-up / closeout weeks don't.
        ProcessStep::where('created_at', '>=', $cutoff)
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
                $row['tasks'][$taskLabel($step)] = true;   // dedup identical step descriptions
                unset($row);
            });

        // Sort owners by name, clients by name.
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
            'windowHours' => self::WINDOW_HOURS,
            'clientCount' => $clientCount,
            'stepCount'   => $stepCount,
            'generatedAt' => Carbon::now(),
        ]);
    }
}
