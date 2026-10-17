<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * One "419 Page Expired" (CSRF token mismatch). Recorded by the exception
 * handler; shown to super admins on the Session Errors page.
 */
class SessionError extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'session_expired' => 'Session expired / page open too long',
        'cookie_missing'  => 'Browser did not send the session cookie',
        'not_https'       => 'Opened over http:// (not https)',
    ];

    protected $fillable = [
        'area', 'method', 'path', 'host', 'secure', 'had_cookie', 'reason', 'device',
        'user_agent', 'ip', 'referer', 'was_ajax',
    ];

    protected $casts = [
        'secure'     => 'boolean',
        'had_cookie' => 'boolean',
        'was_ajax'   => 'boolean',
        'created_at' => 'datetime',
    ];

    /** Record a 419 for this request. Never throws — logging must not break the redirect. */
    public static function record(Request $request, string $area): void
    {
        try {
            $secure    = $request->isSecure();
            $hadCookie = $request->cookies->has(config('session.cookie'));
            $reason    = ! $secure ? 'not_https' : ($hadCookie ? 'session_expired' : 'cookie_missing');

            static::create([
                'area'       => $area,
                'method'     => $request->method(),
                'path'       => mb_substr('/' . ltrim($request->path(), '/'), 0, 255),
                'host'       => mb_substr($request->getHost(), 0, 120),
                'secure'     => $secure,
                'had_cookie' => $hadCookie,
                'reason'     => $reason,
                'device'     => static::describeDevice((string) $request->userAgent()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'ip'         => $request->ip(),
                'referer'    => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
                'was_ajax'   => $request->expectsJson(),
            ]);

            // Keep the table small: occasionally drop anything older than 60 days.
            if (random_int(1, 50) === 1) {
                static::where('created_at', '<', now()->subDays(60))->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not record session error', ['message' => $e->getMessage()]);
        }
    }

    /** A short human label: "iPhone · WhatsApp", "Windows · Chrome", "Apex desktop app"… */
    public static function describeDevice(string $ua): string
    {
        if ($ua === '') {
            return 'Unknown';
        }
        if (str_contains($ua, 'ApexDesktop')) {
            return 'Apex desktop app';
        }
        $os = match (true) {
            str_contains($ua, 'iPhone')    => 'iPhone',
            str_contains($ua, 'iPad')      => 'iPad',
            str_contains($ua, 'Android')   => 'Android',
            str_contains($ua, 'Windows')   => 'Windows',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Linux')     => 'Linux',
            default                        => 'Other',
        };
        $browser = match (true) {
            str_contains($ua, 'WhatsApp')                           => 'WhatsApp',
            str_contains($ua, 'FBAN') || str_contains($ua, 'FBAV')   => 'Facebook',
            str_contains($ua, 'Instagram')                          => 'Instagram',
            str_contains($ua, 'Edg/')                               => 'Edge',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'FxiOS') || str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari')                             => 'Safari',
            default                                                 => 'Browser',
        };

        return "{$os} · {$browser}";
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
