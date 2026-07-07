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

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            \App\Models\ActivityLog::create([
                'admin_id'    => Auth::guard('admin')->id(),
                'action'      => 'login',
                'description' => 'Logged in',
                'method'      => 'POST',
                'path'        => '/admin/login',
                'ip'          => $request->ip(),
            ]);
            // Leads agents can't reach the business-owner workflow — land them
            // on their leads pipeline instead (and don't honour a stored
            // "intended" URL they'd only get a 403 on).
            $user = Auth::guard('admin')->user();
            if ($user->isLeads()) {
                return redirect()->route('admin.prospect-leads.index', ['channel' => 'whatsapp']);
            }
            if ($user->isSuper()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('admin.client-selector.index'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
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
