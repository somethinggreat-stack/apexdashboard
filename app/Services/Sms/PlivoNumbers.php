<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Plivo\Util\v3SignatureValidation;
use RuntimeException;

/**
 * The only thing that talks to Plivo.
 *
 * It does exactly two jobs: list the numbers on the account, and say whether an
 * inbound webhook really came from Plivo. It cannot send a message, buy a
 * number or spend money — the credentials could, which is why there is no
 * general-purpose "call anything" method here.
 */
class PlivoNumbers
{
    public function isConfigured(): bool
    {
        return (bool) (config('sms.plivo.auth_id') && config('sms.plivo.auth_token'));
    }

    /**
     * Every number on the account, minus anything explicitly excluded.
     *
     * @return array<int, array{phone: string, label: ?string}>
     */
    public function numbers(): array
    {
        $rows     = $this->get('/Number/')['objects'] ?? [];
        $excluded = (array) config('sms.excluded', []);
        $out      = [];

        foreach ($rows as $row) {
            $raw = (string) ($row['number'] ?? '');

            if ($raw === '') {
                continue;
            }

            // Plivo reports numbers without the leading +; the rest of the app
            // works in E.164, and so does the webhook payload.
            $phone = str_starts_with($raw, '+') ? $raw : '+' . $raw;

            if (in_array($phone, $excluded, true)) {
                continue;
            }

            $out[] = [
                'phone' => $phone,
                'label' => $row['alias'] ?: null,
            ];
        }

        return $out;
    }

    /**
     * Did this webhook really come from Plivo?
     *
     * Delegated to Plivo's own validator rather than reimplemented. The V3
     * scheme signs the URL plus every POST parameter sorted and concatenated,
     * then the nonce — fiddly enough that a hand-rolled version that looks
     * right and is subtly wrong would either reject real traffic or, worse,
     * accept forged traffic.
     *
     * The URL must match what is configured in Plivo to the character, which is
     * why it comes from config rather than from the request: behind a proxy,
     * the request's own idea of its URL is not reliable.
     */
    public function verifyWebhook(array $params, string $signature, string $nonce): bool
    {
        if (! config('sms.verify_signature', true)) {
            return true;
        }

        if ($signature === '' || $nonce === '' || ! $this->isConfigured()) {
            return false;
        }

        try {
            return v3SignatureValidation::validateV3Signature(
                'POST',
                (string) config('sms.webhook_url'),
                $nonce,
                (string) config('sms.plivo.auth_token'),
                $signature,
                $params
            );
        } catch (\Throwable $e) {
            Log::warning('Plivo signature validation failed to run: ' . $e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------- internals

    private function get(string $path): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The number pool is not configured on this server yet.');
        }

        $url = rtrim((string) config('sms.plivo.base_url'), '/')
            . '/Account/' . config('sms.plivo.auth_id') . $path;

        try {
            $response = Http::withBasicAuth(
                    (string) config('sms.plivo.auth_id'),
                    (string) config('sms.plivo.auth_token')
                )
                ->timeout((int) config('sms.timeout', 20))
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Plivo. Try again in a moment.');
        }

        if (! $response->successful()) {
            // The path can be logged; the credentials never are.
            Log::warning('Plivo GET ' . $path . ' failed', ['status' => $response->status()]);

            throw new RuntimeException('Plivo refused the request (HTTP ' . $response->status() . ').');
        }

        return (array) $response->json();
    }
}
