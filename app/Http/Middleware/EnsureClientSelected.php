<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class EnsureClientSelected
{
    public function handle(Request $request, Closure $next)
    {
        $adminId = Auth::guard('admin')->id();
        $selectedId = $request->session()->get('selected_client_id');

        $client = $selectedId
            ? Client::forAdmin($adminId)->find($selectedId)
            : null;

        if (!$client) {
            $request->session()->forget('selected_client_id');
            return redirect()->route('admin.client-selector.index');
        }

        View::share('selectedClient', $client);

        return $next($request);
    }
}
