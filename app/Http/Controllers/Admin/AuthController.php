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
            return redirect()->intended(route('admin.client-selector.index'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
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
