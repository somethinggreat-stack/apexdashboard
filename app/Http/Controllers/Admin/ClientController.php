<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::forAdmin(Auth::guard('admin')->id())
            ->withCount('endUsers')
            ->orderBy('business_name')
            ->get();

        return view('admin.clients.index', ['clients' => $clients, 'referrers' => $this->referrers()]);
    }

    public function create()
    {
        return view('admin.clients.create', ['referrers' => $this->referrers()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name'       => 'required|string|max:255',
            // A binned owner still holds its email; ignore soft-deleted rows so
            // the address frees up for re-use while the old one sits in the bin.
            'email'               => ['required', 'email', Rule::unique('clients', 'email')->whereNull('deleted_at')],
            'password'            => 'required|string|min:6',
            'phone'               => 'nullable|string|max:30',
            'monthly_fee'         => 'nullable|numeric|min:0',
            'compensation_model'  => 'required|in:per_round,hourly',
            'per_round_fee'       => 'nullable|numeric|min:0',
            'hourly_rate'         => 'nullable|numeric|min:0',
            'weekly_hours_target' => 'nullable|integer|min:0|max:168',
            'pay_cycle'           => 'nullable|in:biweekly,monthly',
            'pay_cycle_anchor'    => 'nullable|date',
            'round_cycle_days'    => 'required|in:20,30',
        ]);

        $data['admin_id']               = Auth::guard('admin')->id();
        // No dashboard subscription fee — owners are billed per round only.
        $data['monthly_fee']            = $data['monthly_fee'] ?? 0;
        $data['is_commission_referrer'] = $request->boolean('is_commission_referrer');
        $data['referrer_id']            = $this->validReferrerId($request->input('referrer_id'));

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

        return redirect()->route('admin.clients.index')->with('confirm', 'Business owner added');
    }

    public function edit(string $id)
    {
        $client = $this->scoped()->findOrFail($id);
        return view('admin.clients.edit', ['client' => $client, 'referrers' => $this->referrers()]);
    }

    public function update(Request $request, string $id)
    {
        $client = $this->scoped()->findOrFail($id);

        $data = $request->validate([
            'business_name'         => 'required|string|max:255',
            'email'                 => 'required|email|unique:clients,email,' . $client->id,
            'password'              => 'nullable|string|min:6',
            'phone'                 => 'nullable|string|max:30',
            'status'                => 'required|in:active,inactive',
            'round_cycle_days'      => 'required|in:20,30',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_commission_referrer'] = $request->boolean('is_commission_referrer');
        $data['referrer_id']            = $this->validReferrerId($request->input('referrer_id'), $client->id);

        $client->update($data);

        return redirect()->route('admin.clients.index')->with('confirm', 'Business owner updated');
    }

    public function destroy(string $id)
    {
        // Soft delete → Recycle Bin. The model's deleting hook sends this
        // owner's clients to the bin with it; nothing is erased for 10 days.
        $client = $this->scoped()->findOrFail($id);
        $client->forceFill(['deleted_by_admin_id' => Auth::guard('admin')->id()])->save();
        $client->delete();

        return redirect()->route('admin.clients.index')
            ->with('status', 'Business owner moved to the Recycle Bin. You can restore it there for 10 days.');
    }

    private function scoped()
    {
        return Client::forAdmin(Auth::guard('admin')->id());
    }

    /** Business owners in this org flagged as commission referrers. */
    private function referrers()
    {
        return Client::forAdmin(Auth::guard('admin')->id())
            ->referrers()
            ->orderBy('business_name')
            ->get();
    }

    /**
     * Validate a submitted referrer id: it must be a referrer in this org, and
     * (on edit) not the business owner itself. Returns the id or null.
     */
    private function validReferrerId($value, ?int $excludeId = null): ?int
    {
        if (empty($value)) {
            return null;
        }

        $query = Client::forAdmin(Auth::guard('admin')->id())->referrers()->whereKey($value);
        if ($excludeId) {
            $query->whereKeyNot($excludeId);
        }

        return $query->exists() ? (int) $value : null;
    }
}

