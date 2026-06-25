<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProspectLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProspectLeadController extends Controller
{
    public function index()
    {
        $leads = ProspectLead::forAdmin(Auth::guard('admin')->id())
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.prospect-leads.index', compact('leads'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['admin_id'] = Auth::guard('admin')->id();

        ProspectLead::create($data);

        return redirect()->route('admin.prospect-leads.index')->with('status', 'Prospect lead added.');
    }

    public function update(Request $request, string $id)
    {
        $lead = $this->scoped()->findOrFail($id);

        $lead->update($this->validated($request));

        return redirect()->route('admin.prospect-leads.index')->with('status', 'Prospect lead updated.');
    }

    public function destroy(string $id)
    {
        $this->scoped()->findOrFail($id)->delete();

        return redirect()->route('admin.prospect-leads.index')->with('status', 'Prospect lead removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'      => 'required|string|max:255',
            'whatsapp'  => 'nullable|string|max:40',
            'instagram' => 'nullable|string|max:255',
            'website'   => 'nullable|string|max:255',
        ]);
    }

    private function scoped()
    {
        return ProspectLead::forAdmin(Auth::guard('admin')->id());
    }
}
