<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessOwnerCredential;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * The business owner Credentials vault — their CRM / software logins (GoHighLevel,
 * dispute software, email, …), stored per owner and shown just above Tasks View.
 * Reached only through the admin.credentials middleware (super admin, or a VA the
 * super admin has granted). Always scoped to the currently selected owner.
 */
class CredentialController extends Controller
{
    public function index()
    {
        $client = $this->selectedClient();

        $all = $client->credentials()->orderBy('sort_order')->orderBy('id')->get();

        $credentials = $all->where('type', '!=', 'link')->values();  // CRM / software logins
        $links       = $all->where('type', 'link')->values();        // plain resource links

        return view($this->adminView('admin.credentials.index'), compact('client', 'credentials', 'links'));
    }

    public function store(Request $request)
    {
        $client = $this->selectedClient();

        $client->credentials()->create($this->validated($request));

        return back()->with('status', 'Credential saved.');
    }

    public function update(Request $request, int $id)
    {
        $credential = $this->find($id);

        $credential->update($this->validated($request));

        return back()->with('status', 'Credential updated.');
    }

    public function destroy(int $id)
    {
        $credential = $this->find($id);
        $name = $credential->software_name;
        $credential->delete();

        return back()->with('status', "{$name} removed.");
    }

    /**
     * Validate the add/edit payload. A 'link' only carries a name, a link and
     * notes — it never asks for login details; a 'credential' carries the lot.
     */
    private function validated(Request $request): array
    {
        $type = $request->input('type') === 'link' ? 'link' : 'credential';

        if ($type === 'link') {
            $data = $request->validate([
                'software_name' => 'required|string|max:120',
                'login_url'     => 'required|string|max:255',
                'notes'         => 'nullable|string|max:2000',
            ]);
        } else {
            $data = $request->validate([
                'software_name' => 'required|string|max:120',
                'login_url'     => 'nullable|string|max:255',
                'username'      => 'nullable|string|max:255',
                'email'         => 'nullable|string|max:255',
                'password'      => 'nullable|string|max:1000',
                'notes'         => 'nullable|string|max:2000',
            ]);
        }

        $data['type'] = $type;

        return $data;
    }

    /** The owner in session — the vault is always scoped to them. */
    private function selectedClient(): Client
    {
        // client.selected middleware guarantees a valid, in-scope selection.
        return view()->shared('selectedClient');
    }

    /** A credential that belongs to the selected owner, or 404. */
    private function find(int $id): BusinessOwnerCredential
    {
        return $this->selectedClient()->credentials()->findOrFail($id);
    }
}
