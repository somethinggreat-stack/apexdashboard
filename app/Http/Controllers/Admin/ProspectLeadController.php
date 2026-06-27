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
        // appear on more than one lead — used to flag duplicates. Built as a
        // plain string array; collection keys would cast numeric strings to
        // ints and break the strict comparison in the view.
        $counts = [];
        foreach ($leads as $l) {
            $d = $l->whatsapp_digits;
            if ($d) {
                $counts[$d] = ($counts[$d] ?? 0) + 1;
            }
        }
        $dupNumbers = [];
        foreach ($counts as $digits => $count) {
            if ($count > 1) {
                $dupNumbers[] = (string) $digits;
            }
        }

        return view('admin.prospect-leads.index', compact('leads', 'dupNumbers'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['admin_id'] = Auth::guard('admin')->id();

        $digits = preg_replace('/\D/', '', (string) ($data['whatsapp'] ?? ''));
        if ($digits !== '' && $this->numberExists($digits)) {
            return back()->withInput()->withErrors([
                'whatsapp' => 'That WhatsApp number is already in Prospect Leads — duplicate not added.',
            ]);
        }

        ProspectLead::create($data);

        return redirect()->route('admin.prospect-leads.index')->with('status', 'Prospect lead added.');
    }

    public function update(Request $request, string $id)
    {
        $lead = $this->scoped()->findOrFail($id);

        $data = $this->validated($request);

        $digits = preg_replace('/\D/', '', (string) ($data['whatsapp'] ?? ''));
        if ($digits !== '' && $this->numberExists($digits, $lead->id)) {
            return back()->withInput()->withErrors([
                'whatsapp' => 'That WhatsApp number is already on another lead — duplicate not allowed.',
            ]);
        }

        $lead->update($data);

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

    /** Toggle the Hot Lead flag for a lead. */
    public function toggleHot(string $id)
    {
        $lead = $this->scoped()->findOrFail($id);
        $lead->update(['hot_lead' => ! $lead->hot_lead]);

        return back()->with('status', $lead->hot_lead
            ? "{$lead->name} marked as Hot Lead."
            : "{$lead->name} unmarked as Hot Lead.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'      => 'required|string|max:255',
            'whatsapp'  => 'nullable|string|max:40',
            'instagram' => 'nullable|string|max:255',
        ]);
    }

    /** Whether another lead already has this digits-only WhatsApp number. */
    private function numberExists(string $digits, ?int $exceptId = null): bool
    {
        return $this->scoped()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->contains(fn ($l) => $l->whatsapp_digits === $digits);
    }

    private function scoped()
    {
        return ProspectLead::forAdmin(Auth::guard('admin')->id());
    }
}
