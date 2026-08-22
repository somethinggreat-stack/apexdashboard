<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Profile — every admin (super, VA, leads agent) manages their OWN account here:
 * name, email and password. Scoped strictly to the authenticated user; role and
 * organization are never editable from this screen.
 */
class ProfileController extends Controller
{
    public function edit()
    {
        return view($this->adminView('admin.profile'), [
            'admin' => Auth::guard('admin')->user(),
        ]);
    }

    public function update(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $data = $request->validate([
            'full_name'        => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            // Password is optional — blank keeps the current one. When changing it,
            // the current password must be given and the new one confirmed.
            'current_password' => 'nullable|string',
            'password'         => 'nullable|string|min:10|confirmed',
        ]);

        if (!empty($data['password'])) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $admin->password)) {
                return back()
                    ->withErrors(['current_password' => 'Your current password is incorrect.'])
                    ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
            }
            $admin->password = $data['password']; // 'hashed' cast re-hashes on save
        }

        $admin->full_name = $data['full_name'];
        $admin->email     = $data['email'];
        $admin->save();

        return back()->with('status', 'Your profile has been updated.');
    }
}
