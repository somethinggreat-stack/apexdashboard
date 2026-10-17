<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SessionError;
use Illuminate\Http\Request;

/**
 * Super Admin → Session Errors: every "419 Page Expired" the system caught,
 * who hit it (area / device) and why, so we can prove the fix works and spot
 * any device or setting still causing them.
 */
class SessionErrorController extends Controller
{
    public function index(Request $request)
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30, 60], true) ? (int) $request->query('days') : 7;
        $area = in_array($request->query('area'), ['admin', 'portal', 'chat'], true) ? $request->query('area') : null;
        $since = now()->subDays($days);

        $base = SessionError::where('created_at', '>=', $since)
            ->when($area, fn ($q) => $q->where('area', $area));

        $stats = [
            'last_24h' => SessionError::where('created_at', '>=', now()->subDay())->count(),
            'last_7d'  => SessionError::where('created_at', '>=', now()->subDays(7))->count(),
            'last_30d' => SessionError::where('created_at', '>=', now()->subDays(30))->count(),
        ];
        $byArea   = (clone $base)->selectRaw('area, COUNT(*) as n')->groupBy('area')->pluck('n', 'area');
        $byReason = (clone $base)->selectRaw('reason, COUNT(*) as n')->groupBy('reason')->pluck('n', 'reason');
        $byDevice = (clone $base)->selectRaw('device, COUNT(*) as n')->groupBy('device')->orderByDesc('n')->limit(8)->pluck('n', 'device');
        $byPath   = (clone $base)->selectRaw('path, COUNT(*) as n')->groupBy('path')->orderByDesc('n')->limit(8)->pluck('n', 'path');
        // Not "$errors" — that name is Laravel's shared validation bag in every view.
        $rows     = (clone $base)->orderByDesc('created_at')->limit(200)->get();

        return view('admin.pro.session-errors.index',
            compact('days', 'area', 'stats', 'byArea', 'byReason', 'byDevice', 'byPath', 'rows'));
    }
}
