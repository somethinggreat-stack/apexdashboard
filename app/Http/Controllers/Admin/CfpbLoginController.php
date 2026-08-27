<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Support\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * CFPB Logins — a per-business-owner report of clients that had their CFPB login
 * (universal or per-round) entered/updated during a SHIFT (the shared WorkDay
 * window). Same shape as the Daily Task page: grouped by owner, with the VA who
 * did it, a copy-paste WhatsApp message, and the last-15-shifts date picker.
 */
class CfpbLoginController extends Controller
{
    public function index(Request $request)
    {
        $ownerId       = Auth::guard('admin')->user()->dataOwnerId();
        $date          = WorkDay::normalise($request->query('date'));
        [$start, $end] = WorkDay::bounds($date);

        $groups = [];

        EndUser::where('cfpb_logged_at', '>=', $start)
            ->where('cfpb_logged_at', '<', $end)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->with(['client', 'cfpbLoggedBy'])
            ->orderByDesc('cfpb_logged_at')
            ->get()
            ->each(function (EndUser $eu) use (&$groups) {
                $bo = $eu->client;
                if (!$bo) {
                    return;
                }
                $groups[$bo->id] ??= ['name' => $bo->business_name, 'clients' => []];
                $row = &$groups[$bo->id]['clients'][$eu->id];
                $row ??= ['name' => $eu->full_name, 'vas' => []];
                if ($va = $eu->cfpbLoggedBy?->full_name) {
                    $row['vas'][$va] = true;
                }
                unset($row);
            });

        uasort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        foreach ($groups as &$g) {
            uasort($g['clients'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        unset($g);

        $clientCount = collect($groups)->sum(fn ($g) => count($g['clients']));

        return view($this->adminView('admin.cfpb-logins'), [
            'groups'      => $groups,
            'clientCount' => $clientCount,
            'workDate'    => $date,
            'workLabel'   => WorkDay::label($date),
            'isCurrent'   => WorkDay::isCurrent($date),
            'recentDays'  => collect(WorkDay::recent(15))->map(fn ($d) => ['date' => $d, 'label' => WorkDay::label($d)])->all(),
            'generatedAt' => Carbon::now()->timezone(WorkDay::TZ),
        ]);
    }
}
