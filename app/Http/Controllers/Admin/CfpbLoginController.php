<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * CFPB Logins — a per-business-owner report of clients that had their CFPB login
 * (universal or per-round) entered/updated in the last 24 hours. Same shape as
 * the Daily Task page: grouped by owner, with the VA who did it and a
 * copy-paste WhatsApp message.
 */
class CfpbLoginController extends Controller
{
    private const WINDOW_HOURS = 24;

    public function index()
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $cutoff  = Carbon::now()->subHours(self::WINDOW_HOURS);

        $groups = [];

        EndUser::where('cfpb_logged_at', '>=', $cutoff)
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
            'windowHours' => self::WINDOW_HOURS,
            'clientCount' => $clientCount,
            'generatedAt' => Carbon::now(),
        ]);
    }
}
