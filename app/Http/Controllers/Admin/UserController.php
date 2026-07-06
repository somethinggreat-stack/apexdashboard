<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = Admin::orderByRaw("CASE WHEN role = 'super' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->get();

        $logs = ActivityLog::with('admin')->latest()->limit(300)->get();

        return view('admin.users.index', compact('users', 'logs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:admins,email',
            'password'  => 'required|string|min:6',
        ]);

        Admin::create([
            'full_name'       => $data['full_name'],
            'email'           => $data['email'],
            'password'        => $data['password'],
            'role'            => 'va',
            'parent_admin_id' => Auth::guard('admin')->user()->dataOwnerId(),
        ]);

        return back()->with('status', "{$data['full_name']} added.");
    }

    public function resetPassword(Request $request, string $id)
    {
        $user = Admin::findOrFail($id);
        $data = $request->validate(['password' => 'required|string|min:6']);

        $user->update(['password' => $data['password']]);

        return back()->with('status', "Password updated for {$user->full_name}.");
    }

    public function destroy(string $id)
    {
        $user = Admin::findOrFail($id);

        if ($user->isSuper() || $user->id === Auth::guard('admin')->id()) {
            return back()->withErrors(['user' => 'You cannot delete this account.']);
        }

        $name = $user->full_name;
        $user->delete();

        return back()->with('status', "{$name} removed.");
    }
}
