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
        $adminId = Auth::guard('admin')->user()?->dataOwnerId();

        if (!$adminId) {
            $request->session()->forget('selected_client_id');
            return redirect()->route('admin.client-selector.index');
        }

        // For any route that targets a specific client (an end-user route carries
        // its id), the owner IS that client's owner — resolve it straight from the
        // record, with NO dependency on the session. This is what makes profile
        // saves reliable: multipart posts can arrive without the session cookie
        // (this host strips it), and we still know exactly whose client it is.
        $client = $this->ownerFromEndUserRoute($request, $adminId);

        // Otherwise fall back to the session-selected owner (list pages, etc.).
        if (!$client) {
            $selectedId = $request->session()->get('selected_client_id');
            if ($selectedId) {
                $client = Client::forAdmin($adminId)->find($selectedId);
            }
        }

        if (!$client) {
            $request->session()->forget('selected_client_id');
            return redirect()->route('admin.client-selector.index');
        }

        // Keep the session in step so the sidebar, badge counts and the rest of
        // the app follow whichever owner we resolved.
        $request->session()->put('selected_client_id', $client->id);
        View::share('selectedClient', $client);

        return $next($request);
    }

    /**
     * Resolve the owner from the end-user in the URL (end-users/{id} and friends),
     * only if that client belongs to a business owner this admin/VA may access.
     * Returns null for routes without a specific end-user so they fall back to the
     * session-selected owner.
     */
    private function ownerFromEndUserRoute(Request $request, int $adminId): ?Client
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
