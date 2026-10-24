<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\AdminSignIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return response()->view('admin.auth.login')
            // Never let a phone or browser show a stale copy (its old token would 419).
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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

        if ($user = $this->checkPassword($credentials)) {
            RateLimiter::clear($key);

            // Enrolled accounts are NOT signed in yet: the authenticator code comes first.
            if (Admin::twoFactorEnabled() && $user->hasTwoFactor()) {
                return AdminSignIn::holdForCode($request, $user, $request->boolean('remember'), false);
            }

            return AdminSignIn::complete($request, $user, $request->boolean('remember'), false);
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    /** Dedicated Team Chat sign-in (separate from the dashboard login). */
    public function showChatLogin()
    {
        return response()->view('admin.auth.chat-login')
            // Never let a phone or browser show a stale copy (its old token would 419).
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** Authenticate for Team Chat and drop the user straight into the standalone chat. */
    public function chatLogin(Request $request)
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

        if ($user = $this->checkPassword($credentials)) {
            // Team Chat is for the super admin and VAs only — leads have no chat.
            if ($user->isLeads()) {
                return back()->withErrors(['email' => 'Team Chat is not available for your account.'])->onlyInput('email');
            }

            RateLimiter::clear($key);

            if (Admin::twoFactorEnabled() && $user->hasTwoFactor()) {
                return AdminSignIn::holdForCode($request, $user, $request->boolean('remember'), true);
            }

            return AdminSignIn::complete($request, $user, $request->boolean('remember'), true);
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    /**
     * Check the email + password WITHOUT signing anyone in, so an account that owes an
     * authenticator code is never logged in half-way. Returns the account, or null.
     */
    private function checkPassword(array $credentials): ?Admin
    {
        $provider = Auth::guard('admin')->getProvider();
        $user     = $provider->retrieveByCredentials($credentials);

        return ($user instanceof Admin && $provider->validateCredentials($user, $credentials)) ? $user : null;
    }

    public function logout(Request $request)
    {
        // Handed to the login page so it can clear THIS person's chat data from this browser
        // (saved chats, drafts, notification list) — including a tray sign-out that never
        // touches the chat page. Scoped on purpose: another account's data and unrelated
        // dashboard storage must survive.
        $chatLogout = [
            'uid'   => (string) Auth::guard('admin')->id(),
            'stamp' => \App\Http\Middleware\TeamChatSession::stamp($request),
        ];
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

        // The desktop app / chat subdomain sign back in on the chat login, not the dashboard.
        $to = (str_contains((string) $request->userAgent(), 'ApexDesktop')
            || $request->getHost() === config('app.chat_host'))
            ? route('admin.chat-login')
            : route('admin.login');

        // The login page also cleans up direct/tray logout on HTTP. Avoid origin-wide
        // Clear-Site-Data: unrelated dashboard data and other accounts must survive.
        return redirect()->to($to)->with('chat_logout', $chatLogout);
    }

    private function throttleKey(Request $request): string
    {
        return 'login-admin|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
