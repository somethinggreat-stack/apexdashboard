<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::forAdmin(Auth::guard('admin')->id())
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('admin.clients.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:30',
            'monthly_fee' => 'nullable|numeric|min:0',
        ]);

        $data['admin_id'] = Auth::guard('admin')->id();
        $data['monthly_fee'] = $data['monthly_fee'] ?? 149.00;

        Client::create($data);

        return redirect()->route('admin.clients.index')->with('status', 'Business owner created.');
    }

    public function show(string $id)
    {
        $client = $this->scoped()->with('endUsers')->findOrFail($id);
        return view('admin.clients.show', compact('client'));
    }

    public function edit(string $id)
    {
        $client = $this->scoped()->findOrFail($id);
        return view('admin.clients.edit', compact('client'));
    }

    public function update(Request $request, string $id)
    {
        $client = $this->scoped()->findOrFail($id);

        $data = $request->validate([
            'business_name'         => 'required|string|max:255',
            'email'                 => 'required|email|unique:clients,email,' . $client->id,
            'password'              => 'nullable|string|min:6',
            'phone'                 => 'nullable|string|max:30',
            'monthly_fee'           => 'required|numeric|min:0',
            'status'                => 'required|in:active,inactive',
            'intake_display_name'   => 'nullable|string|max:255',
            'intake_logo'           => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Handle logo upload (public disk so the intake form can render it)
        if ($request->hasFile('intake_logo')) {
            // Remove previous logo first
            if ($client->intake_logo_path && Storage::disk('public')->exists($client->intake_logo_path)) {
                Storage::disk('public')->delete($client->intake_logo_path);
            }
            $file = $request->file('intake_logo');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = $client->id . '_' . time() . '.' . $ext;
            $data['intake_logo_path'] = $file->storeAs('intake-logos', $filename, 'public');
        }
        unset($data['intake_logo']);

        $client->update($data);

        return redirect()->route('admin.clients.show', $client)->with('status', 'Business owner updated.');
    }

    public function regenerateIntakeToken(string $client)
    {
        $bo = $this->scoped()->findOrFail($client);
        $bo->update(['intake_token' => Client::generateIntakeToken()]);

        return redirect()->route('admin.clients.show', $bo)->with('status', 'Intake link regenerated — the old link no longer works.');
    }

    public function removeIntakeLogo(string $client)
    {
        $bo = $this->scoped()->findOrFail($client);
        if ($bo->intake_logo_path && Storage::disk('public')->exists($bo->intake_logo_path)) {
            Storage::disk('public')->delete($bo->intake_logo_path);
        }
        $bo->update(['intake_logo_path' => null]);

        return redirect()->route('admin.clients.show', $bo)->with('status', 'Intake logo removed.');
    }

    public function destroy(string $id)
    {
        $this->scoped()->findOrFail($id)->delete();
        return redirect()->route('admin.clients.index')->with('status', 'Business owner deleted.');
    }

    private function scoped()
    {
        return Client::forAdmin(Auth::guard('admin')->id());
    }
}
