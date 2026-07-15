<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        // Only this org's accounts (the super admin + the users they created).
        $users = $this->orgScope($ownerId)
            ->orderByRaw("CASE WHEN role = 'super' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->get();

        // Activity log — pick a time window and/or search (find older events like
        // "who deleted this client three days ago").
        $ranges = [
            '30m' => ['label' => 'Last 30 minutes', 'since' => now()->subMinutes(30)],
            '24h' => ['label' => 'Last 24 hours',   'since' => now()->subDay()],
            '7d'  => ['label' => 'Last 7 days',     'since' => now()->subDays(7)],
            '30d' => ['label' => 'Last 30 days',    'since' => now()->subDays(30)],
            'all' => ['label' => 'All time',        'since' => null],
        ];
        $range  = array_key_exists($request->query('range'), $ranges) ? $request->query('range') : '30m';
        $search = trim((string) $request->query('q', ''));

        $logs = ActivityLog::with('admin')
            ->whereIn('admin_id', $users->pluck('id'))
            ->when($ranges[$range]['since'], fn ($q, $since) => $q->where('created_at', '>=', $since))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('description', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('path', 'like', $like)
                        ->orWhere('ip', 'like', $like)
                        ->orWhereHas('admin', fn ($a) => $a->where('full_name', 'like', $like)->orWhere('email', 'like', $like));
                });
            })
            ->latest()
            ->limit(500)
            ->get();

        return view('admin.users.index', compact('users', 'logs', 'ranges', 'range', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:admins,email',
            'password'  => 'required|string|min:10',
        ]);

        // role + parent_admin_id are NOT mass-assignable — set explicitly.
        $admin = new Admin();
        $admin->full_name       = $data['full_name'];
        $admin->email           = $data['email'];
        $admin->password        = $data['password'];
        $admin->role            = 'va';
        $admin->parent_admin_id = Auth::guard('admin')->user()->dataOwnerId();
        $admin->save();

        return back()->with('status', "{$data['full_name']} added.");
    }

    public function resetPassword(Request $request, string $id)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $user    = $this->orgScope($ownerId)->findOrFail($id);

        $data = $request->validate(['password' => 'required|string|min:10']);
        $user->update(['password' => $data['password']]);

        return back()->with('status', "Password updated for {$user->full_name}.");
    }

    public function destroy(string $id)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();
        $user    = $this->orgScope($ownerId)->findOrFail($id);

        if ($user->isSuper() || $user->id === Auth::guard('admin')->id()) {
            return back()->withErrors(['user' => 'You cannot delete this account.']);
        }

        $name = $user->full_name;
        $user->delete();

        return back()->with('status', "{$name} removed.");
    }

    /** Accounts belonging to this admin org: the owner plus the users they created. */
    private function orgScope(int $ownerId)
    {
        return Admin::where(fn ($q) => $q->where('id', $ownerId)->orWhere('parent_admin_id', $ownerId));
    }
}
