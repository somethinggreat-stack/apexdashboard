<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Daily Task — a per-business-owner report of everything worked on in the last
 * 12 hours: clients that had process steps logged, and clients newly added to
 * the Clients (Done) list. Grouped by owner so the super admin can copy/paste
 * the WhatsApp-style daily update and catch VAs who logged nothing.
 */
class DailyTaskController extends Controller
{
    private const WINDOW_HOURS = 12;

    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $cutoff  = Carbon::now()->subHours(self::WINDOW_HOURS);

        // owner id => ['name' => ..., 'clients' => [euId => ['name'=>, 'vas'=>[], 'listed'=>bool]]]
        $groups = [];

        $addClient = function (EndUser $eu, ?string $va, bool $listed, ?string $task) use (&$groups) {
            $bo = $eu->client;
            if (!$bo) {
                return;
            }
            $groups[$bo->id] ??= ['name' => $bo->business_name, 'clients' => []];
            $row = &$groups[$bo->id]['clients'][$eu->id];
            $row ??= ['name' => $eu->full_name, 'vas' => [], 'listed' => false, 'tasks' => []];
            if ($va) {
                $row['vas'][$va] = true;
            }
            if ($listed) {
                $row['listed'] = true;
            }
            if ($task) {
                $row['tasks'][$task] = true;   // dedup identical step descriptions
            }
            unset($row);
        };

        // Ordinal-prefixed step label, e.g. "4th · CFPB (All 3B) & Innovis".
        $taskLabel = function (ProcessStep $step) {
            $roundLabel = \App\Models\EndUser::ROUND_OPTIONS[$step->round - 1] ?? "Round {$step->round}";
            return \Illuminate\Support\Str::before($roundLabel, ' Round') . ' · ' . $step->step_type_label;
        };

        // 1) Week-1 process steps logged in the window (real created_at, not the
        //    entered date). Week 1 of any round = that round was started — the only
        //    thing that counts as a daily task. Follow-up / closeout weeks don't.
        ProcessStep::where('created_at', '>=', $cutoff)
            ->where('week', 1)
            ->with(['endUser.client', 'createdBy'])
            ->get()
            ->each(function (ProcessStep $step) use ($ownerId, $addClient, $taskLabel) {
                $eu = $step->endUser;
                if (!$eu || !$eu->client || $eu->client->admin_id !== $ownerId) {
                    return;
                }
                $addClient($eu, $step->createdBy?->full_name, false, $taskLabel($step));
            });

        // 2) Clients newly added to the Clients (Done) list in the window. Attach
        //    the VA(s) and Week-1 steps of their latest round so these rows carry
        //    the same "who did it + what was done" detail as the worked rows.
        EndUser::where('listed_at', '>=', $cutoff)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->with(['client', 'processSteps.createdBy'])
            ->get()
            ->each(function (EndUser $eu) use ($addClient, $taskLabel) {
                $addClient($eu, null, true, null);

                $latestRound = $eu->processSteps->max('round');
                if ($latestRound === null) {
                    return;
                }
                $eu->processSteps
                    ->where('round', $latestRound)
                    ->where('week', 1)
                    ->each(fn (ProcessStep $step) => $addClient($eu, $step->createdBy?->full_name, true, $taskLabel($step)));
            });

        // Sort owners by name, clients by name.
        uasort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        foreach ($groups as &$g) {
            uasort($g['clients'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        unset($g);

        $clientCount = collect($groups)->sum(fn ($g) => count($g['clients']));

        return view($this->adminView('admin.daily-task'), [
            'groups'      => $groups,
            'windowHours' => self::WINDOW_HOURS,
            'clientCount' => $clientCount,
            'generatedAt' => Carbon::now(),
        ]);
    }
}
