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
    public function index(Request $request)
    {
        $channel = $this->channel($request);
        $adminId = Auth::guard('admin')->user()->dataOwnerId();

        $leads = ProspectLead::forAdmin($adminId)
            ->where('channel', $channel)
            ->orderByDesc('updated_at')
            ->get();

        // Duplicate matching:
        //  - instagram channel: by normalized Instagram link, among IG leads
        //  - whatsapp/phone:    by digits, ACROSS both whatsapp + phone leads
        if ($channel === 'instagram') {
            $pool  = $leads;
            $keyOf = fn ($l) => $l->instagram_key;
        } else {
            $pool  = ProspectLead::forAdmin($adminId)->whereIn('channel', ['whatsapp', 'phone'])->get();
            $keyOf = fn ($l) => $l->whatsapp_digits;
        }

        $counts = [];
        foreach ($pool as $l) {
            $k = $keyOf($l);
            if ($k) {
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        }
        $dupKeys = [];
        foreach ($counts as $k => $c) {
            if ($c > 1) {
                $dupKeys[] = (string) $k;
            }
        }

        // Keys already on file (for the live add-form duplicate check).
        $existingKeys = array_values(array_unique(array_keys($counts)));
        $existingKeys = array_map('strval', $existingKeys);

        return view('admin.prospect-leads.index', compact('leads', 'channel', 'dupKeys', 'existingKeys'));
    }

    public function store(Request $request)
    {
        $channel = $this->channel($request);
        $data = $this->validated($request, $channel);
        $data['admin_id'] = Auth::guard('admin')->user()->dataOwnerId();
        $data['channel']  = $channel;

        if ($msg = $this->duplicateMessage($channel, $data)) {
            return back()->withInput()->withErrors(['dupe' => $msg]);
        }

        ProspectLead::create($data);

        return redirect()->route('admin.prospect-leads.index', ['channel' => $channel])
            ->with('status', ProspectLead::CHANNELS[$channel] . ' lead added.');
    }

    public function update(Request $request, string $id)
    {
        $lead = $this->scoped()->findOrFail($id);
        $channel = $lead->channel ?: 'whatsapp';
        $data = $this->validated($request, $channel);

        if ($msg = $this->duplicateMessage($channel, $data, $lead->id)) {
            return back()->withInput()->withErrors(['dupe' => $msg]);
        }

        $lead->update($data);

        return redirect()->route('admin.prospect-leads.index', ['channel' => $channel])
            ->with('status', 'Lead updated.');
    }

    public function destroy(string $id)
    {
        $lead = $this->scoped()->findOrFail($id);
        $channel = $lead->channel ?: 'whatsapp';
        $lead->delete();

        return redirect()->route('admin.prospect-leads.index', ['channel' => $channel])
            ->with('status', 'Lead removed.');
    }

    /** Promote a lead into the matching in-contact pipeline (same channel). */
    public function move(Request $request, string $id)
    {
        $lead = $this->scoped()->findOrFail($id);
        $channel = $lead->channel ?: 'whatsapp';

        $data = $request->validate([
            'outreach_whatsapp' => 'nullable|string|max:40',
            'status'            => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes'             => 'nullable|string|max:5000',
        ]);

        // For number channels, keep the Instagram link in the notes too.
        $extra = [];
        if ($channel !== 'instagram' && $lead->instagram) {
            $extra[] = 'Instagram: ' . $lead->instagram;
        }
        $notes = trim(($data['notes'] ?? '') . ($extra ? "\n\n" . implode("\n", $extra) : ''));

        Prospect::create([
            'admin_id'          => Auth::guard('admin')->user()->dataOwnerId(),
            'channel'           => $channel,
            'name'              => $lead->name,
            'whatsapp'          => $channel === 'instagram' ? null : $lead->whatsapp,
            'outreach_whatsapp' => $channel === 'instagram' ? null : ($data['outreach_whatsapp'] ?? null),
            'instagram'         => $lead->instagram,
            'status'            => $data['status'],
            'notes'             => $notes !== '' ? $notes : null,
        ]);

        $name = $lead->name;
        $lead->delete();

        return redirect()->route('admin.prospects.index', ['channel' => $channel])
            ->with('status', "{$name} moved to " . Prospect::CHANNELS[$channel] . ' in Contact.');
    }

    public function toggleHot(string $id)
    {
        $lead = $this->scoped()->findOrFail($id);
        $lead->update(['hot_lead' => ! $lead->hot_lead]);

        return back()->with('status', $lead->hot_lead
            ? "{$lead->name} marked as Hot Lead."
            : "{$lead->name} unmarked as Hot Lead.");
    }

    /* ---------------- helpers ---------------- */

    private function channel(Request $request): string
    {
        $c = (string) $request->input('channel', $request->query('channel', 'whatsapp'));
        return array_key_exists($c, ProspectLead::CHANNELS) ? $c : 'whatsapp';
    }

    private function validated(Request $request, string $channel): array
    {
        if ($channel === 'instagram') {
            return $request->validate([
                'name'      => 'required|string|max:255',
                'instagram' => 'required|string|max:255',
            ]);
        }

        return $request->validate([
            'name'      => 'required|string|max:255',
            'whatsapp'  => 'nullable|string|max:40',
            'instagram' => 'nullable|string|max:255',
        ]);
    }

    /** Returns a duplicate error message, or null if this lead is unique. */
    private function duplicateMessage(string $channel, array $data, ?int $exceptId = null): ?string
    {
        $adminId = Auth::guard('admin')->user()->dataOwnerId();

        if ($channel === 'instagram') {
            $key = $this->igKey($data['instagram'] ?? null);
            if (!$key) {
                return null;
            }
            $exists = ProspectLead::forAdmin($adminId)->where('channel', 'instagram')
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->get()->contains(fn ($l) => $l->instagram_key === $key);

            return $exists ? 'That Instagram link is already in Instagram Leads — duplicate not added.' : null;
        }

        $digits = preg_replace('/\D/', '', (string) ($data['whatsapp'] ?? ''));
        if ($digits === '') {
            return null;
        }
        $exists = ProspectLead::forAdmin($adminId)->whereIn('channel', ['whatsapp', 'phone'])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()->contains(fn ($l) => $l->whatsapp_digits === $digits);

        return $exists ? 'That number is already in WhatsApp or Phone Leads — duplicate not added.' : null;
    }

    private function igKey(?string $ig): ?string
    {
        $ig = strtolower(trim((string) $ig));
        $ig = preg_replace('/\?.*$/', '', $ig);
        $ig = rtrim($ig, '/');
        return $ig !== '' ? $ig : null;
    }

    private function scoped()
    {
        return ProspectLead::forAdmin(Auth::guard('admin')->user()->dataOwnerId());
    }
}
