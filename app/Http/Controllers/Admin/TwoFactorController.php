<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\AdminSignIn;
use App\Support\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Authenticator-app sign-in for staff: the code page shown after a correct password, plus
 * setup, recovery codes and turn-off for signed-in staff.
 */
class TwoFactorController extends Controller
{
    private const NO_CACHE = 'no-store, no-cache, must-revalidate, max-age=0';

    // ---------------------------------------------------------------------
    //  The code page (after a correct password, before anyone is signed in)
    // ---------------------------------------------------------------------

    public function challenge(Request $request)
    {
        $pending = AdminSignIn::pending($request);
        if (! $pending) {
            return $this->backToLogin($request, 'Your sign-in timed out. Please sign in again.');
        }

        return response()->view('admin.auth.two-factor-challenge', ['chat' => (bool) $pending['chat']])
            ->header('Cache-Control', self::NO_CACHE);
    }

    public function verify(Request $request)
    {
        $pending = AdminSignIn::pending($request);
        if (! $pending) {
            return $this->backToLogin($request, 'Your sign-in timed out. Please sign in again.');
        }

        $user = Admin::find($pending['id']);
        if (! $user || ! $user->hasTwoFactor() || ! Admin::twoFactorEnabled()) {
            $request->session()->forget(AdminSignIn::PENDING);

            return $this->backToLogin($request, 'Please sign in again.');
        }

        $request->validate([
            'code'          => 'nullable|string|max:32',
            'recovery_code' => 'nullable|string|max:32',
        ]);

        // Five tries per account and fifteen per address, per five minutes.
        $userKey = '2fa-user|' . $user->id;
        $ipKey   = '2fa-ip|' . $request->ip();
        foreach ([[$userKey, 5], [$ipKey, 15]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = ceil(RateLimiter::availableIn($key) / 60);

                return back()->withErrors(['code' => "Too many wrong codes. Try again in about {$wait} minute(s)."]);
            }
        }

        $viaRecovery = filled($request->input('recovery_code'));
        $ok = $viaRecovery
            ? $user->consumeRecoveryCode((string) $request->input('recovery_code'))
            : $this->acceptCode($user, (string) $request->input('code'));

        if (! $ok) {
            RateLimiter::hit($userKey, 300);
            RateLimiter::hit($ipKey, 300);
            AdminSignIn::log($request, $user, 'two_factor_failed', $viaRecovery ? 'Wrong recovery code' : 'Wrong authenticator code');

            return back()->withErrors([$viaRecovery ? 'recovery_code' : 'code' => $viaRecovery
                ? 'That recovery code is not valid, or it has already been used.'
                : 'That code is not right. Open your authenticator app and enter the current 6-digit code.']);
        }

        RateLimiter::clear($userKey);
        if ($viaRecovery) {
            AdminSignIn::log($request, $user, 'two_factor_recovery', 'Signed in with a recovery code (' . $user->recoveryCodesLeft() . ' left)');
        }

        return AdminSignIn::complete($request, $user, (bool) $pending['remember'], (bool) $pending['chat'], true);
    }

    // ---------------------------------------------------------------------
    //  Setup, recovery codes, turn off (signed-in staff)
    // ---------------------------------------------------------------------

    public function setup(Request $request)
    {
        $user = Auth::guard('admin')->user();
        abort_unless(Admin::twoFactorEnabled(), 404);

        if ($user->hasTwoFactor()) {
            return redirect()->route('admin.two-factor.manage');
        }

        // The secret is kept (encrypted) but stays unconfirmed until a code proves the app has it.
        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => null])->save();
        }

        return response()->view('admin.two-factor.setup', [
            'admin'  => $user,
            'qr'     => $this->qrSvg(Totp::uri(config('team.two_factor.issuer'), $user->email, $user->two_factor_secret)),
            'secret' => Totp::pretty($user->two_factor_secret),
        ])->header('Cache-Control', self::NO_CACHE);
    }

    public function confirm(Request $request)
    {
        $user = Auth::guard('admin')->user();
        abort_unless(Admin::twoFactorEnabled(), 404);

        if ($user->hasTwoFactor() || ! $user->two_factor_secret) {
            return redirect()->route('admin.two-factor.manage');
        }

        $request->validate(['code' => 'required|string|max:32']);

        $key = '2fa-setup|' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withErrors(['code' => 'Too many tries. Wait a few minutes and try again.']);
        }

        $step = Totp::verify($user->two_factor_secret, (string) $request->input('code'));
        if ($step === null) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => 'That code is not right. Make sure you scanned the code above, then enter the current 6 digits.']);
        }
        RateLimiter::clear($key);

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $user->issueRecoveryCodes();

        $request->session()->put(AdminSignIn::PASSED, now()->timestamp);
        $request->session()->flash('two_factor_codes', $codes);
        AdminSignIn::log($request, $user, 'two_factor_enabled', 'Turned on authenticator-app sign-in');

        return redirect()->route('admin.two-factor.recovery');
    }

    /** The recovery codes, shown once right after they are made. */
    public function recovery(Request $request)
    {
        $codes = $request->session()->get('two_factor_codes');
        if (! $codes) {
            return redirect()->route('admin.two-factor.manage');
        }

        return response()->view('admin.two-factor.recovery', ['admin' => Auth::guard('admin')->user(), 'codes' => $codes])
            ->header('Cache-Control', self::NO_CACHE);
    }

    public function manage()
    {
        $user = Auth::guard('admin')->user();

        return response()->view('admin.two-factor.manage', [
            'admin'    => $user,
            'enabled'  => Admin::twoFactorEnabled(),
            'required' => $user->requiresTwoFactor(),
        ])->header('Cache-Control', self::NO_CACHE);
    }

    public function regenerateCodes(Request $request)
    {
        $user = Auth::guard('admin')->user();
        if (! $this->confirmedAction($request, $user)) {
            return back();
        }

        $request->session()->flash('two_factor_codes', $user->issueRecoveryCodes());
        AdminSignIn::log($request, $user, 'two_factor_codes', 'Made new recovery codes');

        return redirect()->route('admin.two-factor.recovery');
    }

    public function disable(Request $request)
    {
        $user = Auth::guard('admin')->user();
        abort_if($user->requiresTwoFactor(), 403, 'Two-factor sign-in is required for your role and cannot be turned off.');

        if (! $this->confirmedAction($request, $user)) {
            return back();
        }

        $user->clearTwoFactor();
        $request->session()->forget(AdminSignIn::PASSED);
        AdminSignIn::log($request, $user, 'two_factor_disabled', 'Turned off authenticator-app sign-in');

        return redirect()->route('admin.two-factor.manage')->with('status', 'Two-factor sign-in is turned off.');
    }

    // ---------------------------------------------------------------------

    /** Sensitive changes need the password AND a current code, so a hijacked session can't weaken the account. */
    private function confirmedAction(Request $request, Admin $user): bool
    {
        $request->validate(['password' => 'required|string', 'code' => 'required|string|max:32']);

        $key = '2fa-manage|' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            back()->withErrors(['code' => 'Too many tries. Wait a few minutes and try again.'])->throwResponse();
        }

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($key, 300);
            back()->withErrors(['password' => 'That password is not right.'])->throwResponse();
        }

        if (! $this->acceptCode($user, (string) $request->input('code'))) {
            RateLimiter::hit($key, 300);
            back()->withErrors(['code' => 'That code is not right. Enter the current 6-digit code.'])->throwResponse();
        }

        RateLimiter::clear($key);

        return true;
    }

    /** Check a code and burn its time step in one atomic update, so the same code can't be used twice. */
    private function acceptCode(Admin $user, string $code): bool
    {
        $step = Totp::verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
        if ($step === null) {
            return false;
        }

        $claimed = Admin::whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
            ->update(['two_factor_last_step' => $step]);

        if ($claimed) {
            $user->two_factor_last_step = $step;
        }

        return (bool) $claimed;
    }

    private function qrSvg(string $uri): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(224, 1), new SvgImageBackEnd())))->writeString($uri);

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    private function backToLogin(Request $request, string $message)
    {
        $chat = str_contains((string) $request->userAgent(), 'ApexDesktop') || $request->getHost() === config('app.chat_host');

        return redirect()->route($chat ? 'admin.chat-login' : 'admin.login')->withErrors(['email' => $message]);
    }
}
