<?php

namespace App\Http\Controllers;

use App\Models\SmsCode;
use App\Models\SmsNumber;
use App\Services\Sms\PlivoNumbers;
use Illuminate\Http\Request;

/**
 * Where Plivo delivers an inbound SMS.
 *
 * Public by necessity — Plivo has no login here — so everything it accepts is
 * checked rather than trusted:
 *
 *   - the V3 signature must verify, or the request is dropped;
 *   - the receiving number must be one of ours, or the request is dropped;
 *   - the same message delivered twice records one row, because Plivo retries.
 *
 * It always answers 200. A webhook that returns an error gets retried, and a
 * message we have deliberately ignored is not something to retry — the only
 * thing a 500 here would achieve is Plivo sending it again.
 */
class SmsWebhookController extends Controller
{
    public function __construct(private readonly PlivoNumbers $plivo)
    {
    }

    public function plivo(Request $request)
    {
        $params = $request->post();

        $ok = $this->plivo->verifyWebhook(
            $params,
            (string) $request->header('X-Plivo-Signature-V3', ''),
            (string) $request->header('X-Plivo-Signature-V3-Nonce', '')
        );

        if (! $ok) {
            // Deliberately says nothing about why: an attacker probing the
            // endpoint learns only that it exists.
            return response()->json(['ok' => false], 200);
        }

        // Plivo uses this shape for inbound SMS; anything else is not ours.
        if (($params['Type'] ?? 'sms') !== 'sms') {
            return response()->json(['ok' => true], 200);
        }

        $to   = $this->e164((string) ($params['To'] ?? ''));
        $from = $this->e164((string) ($params['From'] ?? ''));
        $text = (string) ($params['Text'] ?? '');
        $uuid = (string) ($params['MessageUUID'] ?? '');

        $number = $to === '' ? null : SmsNumber::where('phone', $to)->first();

        // A message to a number we do not run is not ours to store.
        if (! $number || $text === '') {
            return response()->json(['ok' => true], 200);
        }

        SmsCode::firstOrCreate(
            // Plivo retries a webhook it did not get a 200 for, and can deliver
            // the same message more than once. The UUID is what makes that safe.
            ['provider_message_id' => $uuid !== '' ? $uuid : $to . ':' . md5($text . $from)],
            [
                'sms_number_id' => $number->id,
                // Whoever is holding the number right now is who the code is for.
                // Null when nobody is: the code is still recorded, just unclaimed.
                'claimed_by_admin_id' => $number->claimed_by_admin_id,
                'from_number' => $from ?: null,
                'code'        => SmsCode::extractCode($text),
                'body'        => $text,
                'received_at' => now(),
            ]
        );

        return response()->json(['ok' => true], 200);
    }

    /** Plivo sends numbers with and without the leading +, depending on the field. */
    private function e164(string $number): string
    {
        $number = trim($number);

        if ($number === '') {
            return '';
        }

        return str_starts_with($number, '+') ? $number : '+' . ltrim($number, '+');
    }
}
