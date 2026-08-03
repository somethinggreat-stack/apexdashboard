<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\EndUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class EnsureClientSelected
{
    public function handle(Request $request, Closure $next)
    {
        $adminId    = Auth::guard('admin')->user()?->dataOwnerId();
        $selectedId = $request->session()->get('selected_client_id');

        $client = ($selectedId && $adminId)
            ? Client::forAdmin($adminId)->find($selectedId)
            : null;

        // Self-heal: if the selected owner is missing or stale but the request
        // targets a specific client (an end-user route carries its id), recover
        // the owner from that client — as long as this admin/VA is allowed to see
        // it. This stops a lost/mismatched session from silently bouncing the user
        // to the picker and discarding what they just typed (e.g. a profile save).
        if (!$client && $adminId) {
            $recovered = $this->recoverOwnerFromRoute($request, $adminId);
            if ($recovered) {
                $client = $recovered;
                $request->session()->put('selected_client_id', $client->id);
            }
        }

        if (!$client) {
            $request->session()->forget('selected_client_id');
            return redirect()->route('admin.client-selector.index');
        }

        View::share('selectedClient', $client);

        return $next($request);
    }

    /**
     * On an end-user route (end-users/{id} and friends) resolve the owner from
     * the end-user in the URL, but only if it belongs to a client this admin/VA
     * is authorized for. Returns null for any non-end-user route so those still
     * fall through to the picker.
     */
    private function recoverOwnerFromRoute(Request $request, int $adminId): ?Client
    {
        if (!$request->routeIs('admin.end-users.*')) {
            return null;
        }

        $euId = $request->route('id') ?? $request->route('endUser');
        if (!$euId || !is_numeric($euId)) {
            return null;
        }

        $endUser = EndUser::whereKey($euId)->first();
        if (!$endUser) {
            return null;
        }

        return Client::forAdmin($adminId)->find($endUser->client_id);
    }
}
