<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProspectController extends Controller
{
    public function index()
    {
        // Active pipeline — everyone except the lost bucket.
        $prospects = Prospect::forAdmin(Auth::guard('admin')->id())
            ->where('status', '!=', 'lost')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.prospects.index', compact('prospects'));
    }

    /** Lost prospects — moved out of the active pipeline (e.g. went silent). */
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

        return redirect()->route('admin.prospects.index')->with('status', "{$prospect->name} moved to Lost Prospects.");
    }

    /** Bring a lost prospect back into the active pipeline. */
    public function reactivate(string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);
        $prospect->update(['status' => 'contacted']);

        return redirect()->route('admin.prospects.lost')->with('status', "{$prospect->name} moved back to Prospects in Contact.");
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['admin_id'] = Auth::guard('admin')->id();

        Prospect::create($data);

        return redirect()->route('admin.prospects.index')->with('status', 'Prospect added.');
    }

    public function update(Request $request, string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);

        $prospect->update($this->validated($request));

        return redirect()->route('admin.prospects.index')->with('status', 'Prospect updated.');
    }

    public function destroy(string $id)
    {
        $this->scoped()->findOrFail($id)->delete();

        return redirect()->route('admin.prospects.index')->with('status', 'Prospect removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'whatsapp'          => 'nullable|string|max:40',
            'outreach_whatsapp' => 'nullable|string|max:40',
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
