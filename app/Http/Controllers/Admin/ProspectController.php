<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProspectController extends Controller
{
    public function index(Request $request)
    {
        $channel = $this->channel($request);

        // Active pipeline for this channel — everyone except the lost bucket.
        $prospects = Prospect::forAdmin(Auth::guard('admin')->id())
            ->where('channel', $channel)
            ->where('status', '!=', 'lost')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.prospects.index', compact('prospects', 'channel'));
    }

    /** Lost prospects across all channels. */
    public function lost()
    {
        $prospects = Prospect::forAdmin(Auth::guard('admin')->id())
            ->where('status', 'lost')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.prospects.lost', compact('prospects'));
    }

    /** One-click move into the Lost bucket. */
    public function markLost(string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);
        $prospect->update(['status' => 'lost']);

        return redirect()->route('admin.prospects.index', ['channel' => $prospect->channel])
            ->with('status', "{$prospect->name} moved to Lost Prospects.");
    }

    /** Bring a lost prospect back into the active pipeline. */
    public function reactivate(string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);
        $prospect->update(['status' => 'contacted']);

        return redirect()->route('admin.prospects.lost')
            ->with('status', "{$prospect->name} moved back to " . Prospect::CHANNELS[$prospect->channel ?? 'whatsapp'] . ' in Contact.');
    }

    public function store(Request $request)
    {
        $channel = $this->channel($request);
        $data = $this->validated($request);
        $data['admin_id'] = Auth::guard('admin')->id();
        $data['channel']  = $channel;

        Prospect::create($data);

        return redirect()->route('admin.prospects.index', ['channel' => $channel])
            ->with('status', 'Prospect added.');
    }

    public function update(Request $request, string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);

        $prospect->update($this->validated($request));

        return redirect()->route('admin.prospects.index', ['channel' => $prospect->channel])
            ->with('status', 'Prospect updated.');
    }

    public function destroy(string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);
        $channel = $prospect->channel ?: 'whatsapp';
        $prospect->delete();

        return redirect()->route('admin.prospects.index', ['channel' => $channel])
            ->with('status', 'Prospect removed.');
    }

    private function channel(Request $request): string
    {
        $c = (string) $request->input('channel', $request->query('channel', 'whatsapp'));
        return array_key_exists($c, Prospect::CHANNELS) ? $c : 'whatsapp';
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'whatsapp'          => 'nullable|string|max:40',
            'outreach_whatsapp' => 'nullable|string|max:40',
            'instagram'         => 'nullable|string|max:255',
            'referred_by'       => 'nullable|string|max:255',
            'status'            => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes'             => 'nullable|string|max:5000',
        ]);
    }

    private function scoped()
    {
        return Prospect::forAdmin(Auth::guard('admin')->id());
    }
}
