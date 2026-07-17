<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'business_name'       => 'required|string|max:255',
            'email'               => 'required|email|unique:clients,email',
            'password'            => 'required|string|min:6',
            'phone'               => 'nullable|string|max:30',
            'monthly_fee'         => 'nullable|numeric|min:0',
            'compensation_model'  => 'required|in:per_round,hourly',
            'per_round_fee'       => 'nullable|numeric|min:0',
            'hourly_rate'         => 'nullable|numeric|min:0',
            'weekly_hours_target' => 'nullable|integer|min:0|max:168',
            'pay_cycle'           => 'nullable|in:biweekly,monthly',
            'pay_cycle_anchor'    => 'nullable|date',
        ]);

        $data['admin_id']            = Auth::guard('admin')->id();
        $data['monthly_fee']         = $data['monthly_fee'] ?? 149.00;
        $data['referred_by_chantal'] = $request->boolean('referred_by_chantal');

        // Clear irrelevant fields for the chosen model so we don't store mixed config.
        if ($data['compensation_model'] === 'per_round') {
            $data['hourly_rate']         = null;
            $data['weekly_hours_target'] = null;
            $data['pay_cycle']           = null;
            $data['pay_cycle_anchor']    = null;
        } else {
            $data['per_round_fee']    = null;
            $data['pay_cycle']        = $data['pay_cycle'] ?? 'biweekly';
            $data['pay_cycle_anchor'] = $data['pay_cycle_anchor'] ?? now()->startOfWeek()->toDateString();
        }

        Client::create($data);

        return redirect()->route('admin.clients.index')->with('status', 'Business owner created.');
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
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['referred_by_chantal'] = $request->boolean('referred_by_chantal');

        $client->update($data);

        return redirect()->route('admin.clients.index')->with('status', 'Business owner updated.');
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
