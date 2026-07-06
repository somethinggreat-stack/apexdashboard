<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExtraProjectController extends Controller
{
    private const TYPES = ['funnel', 'support', 'ads'];

    private const STATUSES = [
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'completed'   => 'Completed',
        'paused'      => 'Paused',
    ];

    public function index(string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $ownerId  = Auth::guard('admin')->user()->dataOwnerId();
        $projects = ExtraProject::forAdmin($ownerId)->type($type)->latest()->get();

        return view('admin.extra.index', [
            'type'     => $type,
            'projects' => $projects,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $data = $this->validated($request);

        $project = new ExtraProject();
        $project->fill($data);
        $project->type                = $type;
        $project->admin_id            = Auth::guard('admin')->user()->dataOwnerId();
        $project->created_by_admin_id = Auth::guard('admin')->id();
        $project->save();

        return back()->with('status', 'Saved.');
    }

    public function update(Request $request, string $id)
    {
        $project = $this->scoped()->findOrFail($id);
        $project->update($this->validated($request));

        return back()->with('status', 'Updated.');
    }

    public function destroy(string $id)
    {
        $project = $this->scoped()->findOrFail($id);
        $name    = $project->client_name;
        $project->delete();

        return back()->with('status', "{$name} removed.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_name' => 'required|string|max:255',
            'link'        => 'nullable|string|max:1000',
            'whatsapp'    => 'nullable|string|max:40',
            'amount'      => 'nullable|numeric|min:0|max:99999999',
            'paid'        => 'nullable|numeric|min:0|max:99999999',
            'status'      => 'required|in:' . implode(',', array_keys(self::STATUSES)),
            'notes'       => 'nullable|string|max:2000',
        ]);
    }

    private function scoped()
    {
        return ExtraProject::forAdmin(Auth::guard('admin')->user()->dataOwnerId());
    }
}
