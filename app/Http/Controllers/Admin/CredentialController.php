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

        $credentials = $client->credentials()->orderBy('sort_order')->orderBy('id')->get();

        return view($this->adminView('admin.credentials.index'), compact('client', 'credentials'));
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

    /** Validate the shared add/edit payload. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'software_name' => 'required|string|max:120',
            'login_url'     => 'nullable|string|max:255',
            'username'      => 'nullable|string|max:255',
            'email'         => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:1000',
            'notes'         => 'nullable|string|max:2000',
        ]);
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
