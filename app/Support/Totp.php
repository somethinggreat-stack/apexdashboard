<?php

namespace App\Support;

/**
 * Time-based one-time passwords (RFC 6238 / RFC 4226): what Google Authenticator,
 * Microsoft Authenticator and Authy compute. HMAC-SHA1, 30-second steps, 6 digits.
 * Dependency-free on purpose; tests check it against the RFC's published vectors.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;

    /** A fresh 160-bit secret, base32 encoded (the format authenticator apps expect). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(preg_replace('/[\s=-]+/', '', $base32));
        $bits = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    /** The code for one 30-second step. */
    public static function codeAt(string $secret, int $step, int $digits = 6): string
    {
        $key  = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $bin  = ((ord($hash[$off]) & 0x7F) << 24)
              | ((ord($hash[$off + 1]) & 0xFF) << 16)
              | ((ord($hash[$off + 2]) & 0xFF) << 8)
              |  (ord($hash[$off + 3]) & 0xFF);

        return str_pad((string) ($bin % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function stepFor(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /**
     * Check a code. Returns the matched step (so the caller can store it and refuse that step
     * and every earlier one from then on), or null when the code is wrong or already used.
     * $window = how many steps either side of "now" count, to allow for clock drift.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code) || self::base32Decode($secret) === '') {
            return null;
        }

        $now     = self::stepFor($time);
        $matched = null;
        // Walk every step (no early exit) so timing doesn't reveal which one matched.
        for ($i = -$window; $i <= $window; $i++) {
            $step = $now + $i;
            if (hash_equals(self::codeAt($secret, $step), $code) && ($lastStep === null || $step > $lastStep)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /** The otpauth:// link a QR code encodes. */
    public static function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=' . self::PERIOD;
    }

    /** The secret in readable groups of four, for typing in by hand. */
    public static function pretty(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }
}
