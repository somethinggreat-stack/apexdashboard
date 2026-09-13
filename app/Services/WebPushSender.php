<?php

namespace App\Services;

use App\Models\PushSubscription as Sub;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender
{
    /** Are VAPID keys configured? If not, Web Push is simply a no-op. */
    public static function enabled(): bool
    {
        return (bool) config('webpush.public_key') && (bool) config('webpush.private_key');
    }

    /**
     * Send one payload to every push subscription owned by the given admins.
     * Dead subscriptions (410/404) are pruned. Failures never throw.
     *
     * @param  int[]  $adminIds
     * @param  array  $payload   ['title' => .., 'body' => .., 'url' => .., 'tag' => ..]
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

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => config('webpush.subject'),
                    'publicKey'  => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ]);

            $body = json_encode($payload);
            $byEndpoint = [];

            foreach ($subs as $sub) {
                $byEndpoint[$sub->endpoint] = $sub;
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint'        => $sub->endpoint,
                        'publicKey'       => $sub->p256dh,
                        'authToken'       => $sub->auth,
                        'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                    ]),
                    $body,
                    ['TTL' => (int) config('webpush.ttl', 1800)]
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if ($report->isSuccess()) {
                    continue;
                }
                // 404 gone / 410 expired → the subscription is dead; delete it.
                if ($report->isSubscriptionExpired() && isset($byEndpoint[$endpoint])) {
                    $byEndpoint[$endpoint]->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush send failed: ' . $e->getMessage());
        }
    }
}
