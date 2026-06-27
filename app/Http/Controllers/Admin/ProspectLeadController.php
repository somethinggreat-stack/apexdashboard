<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Models\ProspectLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProspectLeadController extends Controller
{
    public function index()
    {
        $leads = ProspectLead::forAdmin(Auth::guard('admin')->id())
            ->orderByDesc('updated_at')
            ->get();

        // WhatsApp numbers (digits only, so formatting doesn't matter) that
        // appear on more than one lead — used to flag duplicates.
        $dupNumbers = $leads
            ->map->whatsapp_digits
            ->filter()
            ->countBy()
            ->filter(fn ($count) => $count > 1)
            ->keys()
            ->all();

        return view('admin.prospect-leads.index', compact('leads', 'dupNumbers'));
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

    /**
     * Promote a lead into the active "Prospects in Contact" pipeline. Carries
     * the name + client WhatsApp over, captures the outreach number, stage and
     * notes, folds any Instagram/website into the discussion, then removes the
     * lead (it's a move, not a copy).
     */
    public function move(Request $request, string $id)
    {
        $lead = $this->scoped()->findOrFail($id);

        $data = $request->validate([
            'outreach_whatsapp' => 'nullable|string|max:40',
            'status'            => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes'             => 'nullable|string|max:5000',
        ]);

        // Keep the social links by appending them to the discussion notes.
        $extra = [];
        if ($lead->instagram) $extra[] = 'Instagram: ' . $lead->instagram;
        if ($lead->website)   $extra[] = 'Website: ' . $lead->website;
        $notes = trim(($data['notes'] ?? '') . ($extra ? "\n\n" . implode("\n", $extra) : ''));

        Prospect::create([
            'admin_id'          => Auth::guard('admin')->id(),
            'name'              => $lead->name,
            'whatsapp'          => $lead->whatsapp,
            'outreach_whatsapp' => $data['outreach_whatsapp'] ?? null,
            'status'            => $data['status'],
            'notes'             => $notes !== '' ? $notes : null,
        ]);

        $name = $lead->name;
        $lead->delete();

        return redirect()->route('admin.prospects.index')->with('status', "{$name} moved to Prospects in Contact.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'      => 'required|string|max:255',
            'whatsapp'  => 'nullable|string|max:40',
            'instagram' => 'nullable|string|max:255',
        ]);
    }

    private function scoped()
    {
        return ProspectLead::forAdmin(Auth::guard('admin')->id());
    }
}
