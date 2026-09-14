<?php

namespace App\Services;

use App\Models\PushSubscription as Sub;
use App\Services\WebPush\WebPushCrypto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends Web Push notifications with a self-contained (dependency-free) VAPID + aes128gcm
 * implementation — no composer package to fail on shared hosting. See WebPushCrypto.
 */
class WebPushSender
{
    /** Are VAPID keys configured? If not, Web Push is simply a no-op. */
    public static function enabled(): bool
    {
        // Web Push is disabled: Team Chat is now a native desktop app with OS notifications,
        // so browser push (the "Google Chrome" toasts) is redundant and unwanted.
        if (! config('webpush.enabled', false)) {
            return false;
        }

        return (bool) config('webpush.public_key') && (bool) config('webpush.private_key');
    }

    /**
     * Send one payload to every push subscription owned by the given admins.
     * Dead subscriptions (404/410) are pruned. Failures never throw.
     *
     * @param  int[]  $adminIds
     * @param  array  $payload   ['title'=>.., 'body'=>.., 'url'=>.., 'tag'=>.., 'conv'=>..]
     */
    public function sendToAdmins(array $adminIds, array $payload): void
    {
        if (! self::enabled() || empty($adminIds)) {
            return;
        }

        $subs = Sub::whereIn('admin_id', array_values(array_unique($adminIds)))->get();
        if ($subs->isEmpty()) {
            return;
        }

        $public  = (string) config('webpush.public_key');
        $private  = (string) config('webpush.private_key');
        $subject  = (string) (config('webpush.subject') ?: 'mailto:admin@apexgrowthsolution.com');
        $ttl      = (int) config('webpush.ttl', 1800);
        $body     = json_encode($payload);

        foreach ($subs as $sub) {
            try {
                $enc = WebPushCrypto::encrypt($sub->p256dh, $sub->auth, $body);
                $vapid = WebPushCrypto::vapidAuth($sub->endpoint, $public, $private, $subject);

                $res = Http::withHeaders([
                    'Authorization'    => $vapid['Authorization'],
                    'Content-Type'     => 'application/octet-stream',
                    'Content-Encoding' => 'aes128gcm',
                    'TTL'              => (string) $ttl,
                    'Urgency'          => 'high',
                ])->withOptions(['timeout' => 8, 'connect_timeout' => 5])
                    ->withBody($enc['body'], 'application/octet-stream')
                    ->post($sub->endpoint);

                // 404 gone / 410 expired → the subscription is dead; delete it.
                if (in_array($res->status(), [404, 410], true)) {
                    $sub->delete();
                } elseif ($res->successful()) {
                    $sub->forceFill(['last_used_at' => now()])->saveQuietly();
                }
            } catch (\Throwable $e) {
                Log::warning('WebPush send failed: ' . $e->getMessage());
            }
        }
    }
}
