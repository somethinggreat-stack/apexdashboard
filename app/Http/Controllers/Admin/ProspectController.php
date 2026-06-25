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
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $prospects = Prospect::forAdmin(Auth::guard('admin')->id())
            ->orderByDesc('updated_at')
            ->get();

        // Pre-group by stage for the pipeline board, keeping every stage present
        // (even empty ones) and in the canonical order.
        $byStatus = [];
        foreach (array_keys(Prospect::STATUSES) as $key) {
            $byStatus[$key] = $prospects->where('status', $key)->values();
        }

        return view('admin.prospects.index', compact('prospects', 'byStatus', 'view'));
    }

    /** Move a prospect to a new stage (drag-and-drop on the board). */
    public function updateStatus(Request $request, string $id)
    {
        $prospect = $this->scoped()->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Prospect::STATUSES))],
        ]);

        $prospect->update($data);

        return response()->json(['ok' => true, 'status' => $prospect->status]);
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
            'value'             => 'nullable|numeric|min:0|max:100000000',
            'status'            => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes'             => 'nullable|string|max:5000',
        ]);
    }

    private function scoped()
    {
        return Prospect::forAdmin(Auth::guard('admin')->id());
    }
}
