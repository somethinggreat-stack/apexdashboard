<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\BusinessLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Business-owner "New Leads" tracker. Every action is scoped to the
 * authenticated business owner's own leads — admins/VAs never see these.
 */
class LeadController extends Controller
{
    public function index()
    {
        $leads = BusinessLead::forClient(Auth::guard('client')->id())
            ->orderByDesc('created_at')
            ->get();

        return view('client.leads.index', ['leads' => $leads]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['client_id'] = Auth::guard('client')->id();
        $data['status'] = $data['status'] ?? 'new';

        BusinessLead::create($data);

        return redirect()->route('client.leads.index')->with('confirm', 'Lead added');
    }

    public function show(string $id)
    {
        $lead = BusinessLead::forClient(Auth::guard('client')->id())->findOrFail($id);

        return view('client.leads.show', ['lead' => $lead]);
    }

    public function update(Request $request, string $id)
    {
        $lead = BusinessLead::forClient(Auth::guard('client')->id())->findOrFail($id);

        // A lightweight status-only change (from the list) vs a full edit.
        if ($request->has('status') && $request->input('_status_only')) {
            $request->validate(['status' => ['required', Rule::in(array_keys(BusinessLead::STATUSES))]]);
            $lead->update(['status' => $request->input('status')]);
            return back()->with('status', 'Status updated.');
        }

        $lead->update($this->validated($request));

        return redirect()->route('client.leads.show', $lead->id)->with('confirm', 'Lead updated');
    }

    public function destroy(string $id)
    {
        $lead = BusinessLead::forClient(Auth::guard('client')->id())->findOrFail($id);
        $lead->delete();

        return redirect()->route('client.leads.index')->with('status', 'Lead deleted.');
    }

    /** All fields optional; status must be a known value when present. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'source' => 'nullable|string|max:120',
            'name'   => 'nullable|string|max:150',
            'email'  => 'nullable|email|max:255',
            'phone'  => 'nullable|string|max:40',
            'notes'  => 'nullable|string|max:5000',
            'status' => ['nullable', Rule::in(array_keys(BusinessLead::STATUSES))],
        ]);
    }
}
