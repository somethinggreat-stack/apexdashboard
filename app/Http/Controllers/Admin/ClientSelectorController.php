<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientSelectorController extends Controller
{
    public function index()
    {
        // Just the picker now — Needs Attention + Payments moved to the super-admin Dashboard.
        $adminId = Auth::guard('admin')->user()->dataOwnerId();

        $clients = Client::forAdmin($adminId)
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();

        return view('admin.client-selector.index', compact('clients'));
    }

    public function select(Request $request, string $id)
    {
        $client = Client::forAdmin(Auth::guard('admin')->user()->dataOwnerId())->findOrFail($id);
        $request->session()->put('selected_client_id', $client->id);

        $redirect = $request->input('redirect_to');
        if ($redirect && str_starts_with($redirect, url('/admin'))) {
            return redirect($redirect);
        }

        return redirect()->route('admin.end-users.index');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('selected_client_id');

        return redirect()->route('admin.client-selector.index');
    }
}
