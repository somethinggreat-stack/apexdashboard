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

    /**
     * Only these step types (the initial dispute wave) count as "task done" on
     * the daily report. Follow-up calls, aggressive follow-up, pull report and
     * record deletions do NOT put a client on the list.
     */
    private const COUNTED_STEP_TYPES = [
        'ex_tu_eq_letters_generated',
        'phone_call_disputes',
        'ftc_and_freezes',
        'cfpb_3b_and_innovis',
        'experian_upload',
    ];

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

        // 1) Initial-wave process steps logged in the window (real created_at, not
        //    the entered date). Only the five counted step types qualify.
        ProcessStep::where('created_at', '>=', $cutoff)
            ->whereIn('step_type', self::COUNTED_STEP_TYPES)
            ->with(['endUser.client', 'createdBy'])
            ->get()
            ->each(function (ProcessStep $step) use ($ownerId, $addClient) {
                $eu = $step->endUser;
                if (!$eu || !$eu->client || $eu->client->admin_id !== $ownerId) {
                    return;
                }
                $roundLabel = \App\Models\EndUser::ROUND_OPTIONS[$step->round - 1] ?? "Round {$step->round}";
                $task = \Illuminate\Support\Str::before($roundLabel, ' Round') . ' · ' . $step->step_type_label;
                $addClient($eu, $step->createdBy?->full_name, false, $task);
            });

        // 2) Clients newly added to the Clients (Done) list in the window.
        EndUser::where('listed_at', '>=', $cutoff)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->with('client')
            ->get()
            ->each(fn (EndUser $eu) => $addClient($eu, null, true, null));

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
