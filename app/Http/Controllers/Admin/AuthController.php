<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (Auth::guard('admin')->validate($credentials)) {
            RateLimiter::clear($key);
            $admin = \App\Models\Admin::where('email', $credentials['email'])->first();

            // Super admin logs straight in — no gag.
            if ($admin && $admin->isSuper()) {
                Auth::guard('admin')->login($admin, $request->boolean('remember'));
                $request->session()->regenerate();
                $this->logLogin($request);

                return redirect()->intended(route('admin.dashboard'));
            }

            // Everyone else on the team (VAs / leads) gets the confirm gate.
            $request->session()->put('pending_admin_id', $admin->id);
            $request->session()->put('pending_remember', $request->boolean('remember'));

            return redirect()->route('admin.login.confirm');
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    /** Step 2 for VAs: the "Fuck, {name}? 😤" gate. */
    public function showConfirm(Request $request)
    {
        $id = $request->session()->get('pending_admin_id');
        $admin = $id ? \App\Models\Admin::find($id) : null;

        if (!$admin) {
            $request->session()->forget(['pending_admin_id', 'pending_remember']);
            return redirect()->route('admin.login');
        }

        return view('admin.auth.confirm', ['name' => $admin->full_name ?: 'you']);
    }

    public function confirm(Request $request)
    {
        $id = $request->session()->get('pending_admin_id');
        if (!$id) {
            return redirect()->route('admin.login');
        }

        // "No" → not approved for login.
        if ($request->input('answer') !== 'yes') {
            $request->session()->forget(['pending_admin_id', 'pending_remember']);
            return redirect()->route('admin.login')->with('status', "No worries — come back when you're ready. 🐔");
        }

        $remember = (bool) $request->session()->get('pending_remember', false);
        Auth::guard('admin')->loginUsingId($id, $remember);
        $request->session()->forget(['pending_admin_id', 'pending_remember']);
        $request->session()->regenerate();
        $this->logLogin($request);

        $user = Auth::guard('admin')->user();
        if ($user->isLeads()) {
            return redirect()->route('admin.prospect-leads.index', ['channel' => 'whatsapp']);
        }

        return redirect()->intended(route('admin.client-selector.index'));
    }

    private function logLogin(Request $request): void
    {
        \App\Models\ActivityLog::create([
            'admin_id'    => Auth::guard('admin')->id(),
            'action'      => 'login',
            'description' => 'Logged in',
            'method'      => 'POST',
            'path'        => '/admin/login',
            'ip'          => $request->ip(),
        ]);
    }

    public function logout(Request $request)
    {
        if ($id = Auth::guard('admin')->id()) {
            \App\Models\ActivityLog::create([
                'admin_id'    => $id,
                'action'      => 'logout',
                'description' => 'Logged out',
                'method'      => 'POST',
                'path'        => '/admin/logout',
                'ip'          => $request->ip(),
            ]);
        }
        Auth::guard('admin')->logout();
        $request->session()->forget('selected_client_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    private function throttleKey(Request $request): string
    {
        return 'login-admin|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
