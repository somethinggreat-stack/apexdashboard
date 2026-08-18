<?php

namespace App\Http\Controllers\Client;

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
        return view('client.auth.login');
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

        if (Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $client = Auth::guard('client')->user();

            // Non-payment wall: valid credentials, but access is revoked — do not
            // establish a session; show them what they owe instead.
            if ($client->access_revoked) {
                $outstanding = 0.0;
                try {
                    $outstanding = (float) ($client->paymentTotals()['pending'] ?? 0);
                } catch (\Throwable $e) {
                    $outstanding = 0.0;
                }
                Auth::guard('client')->logout();

                return response()->view('client.access-revoked', [
                    'client'      => $client,
                    'outstanding' => $outstanding,
                ], 403);
            }

            $request->session()->regenerate();
            return redirect()->intended(route('client.dashboard'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('client.login');
    }

    private function throttleKey(Request $request): string
    {
        return 'login-client|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
