<?php

namespace App\Services\WebPush;

/**
 * Dependency-free Web Push (VAPID + aes128gcm) using only ext-openssl.
 *
 * Implements RFC 8291 (Message Encryption) + RFC 8292 (VAPID) so we don't rely on
 * any composer package that might fail to install on shared hosting. Verified against
 * the RFC 8291 §5 test vector (see WebPushCryptoTest).
 */
class WebPushCrypto
{
    // DER prefix for a P-256 SubjectPublicKeyInfo; the raw 65-byte point follows it.
    private const SPKI_PREFIX = "3059301306072a8648ce3d020106082a8648ce3d030107034200";

    /** Generate a VAPID key pair as base64url strings: ['publicKey'=>.., 'privateKey'=>..]. */
    public static function generateVapidKeys(): array
    {
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $res) {
            throw new \RuntimeException('openssl could not create an EC key (openssl.cnf / curve support missing).');
        }
        $d = openssl_pkey_get_details($res);

        return [
            'publicKey'  => self::b64u(self::point($d)),
            'privateKey' => self::b64u(self::pad($d['ec']['d'])),
        ];
    }

    /** self-test: generate keys + encrypt a tiny payload to a throwaway subscription. */
    public static function selfTest(): bool
    {
        $recv = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $rd = openssl_pkey_get_details($recv);
        $enc = self::encrypt(self::b64u(self::point($rd)), self::b64u(random_bytes(16)), 'ping');

        return isset($enc['body']) && strlen($enc['body']) > 100;
    }

    /**
     * VAPID Authorization + Crypto-Key headers for a push endpoint.
     * Returns ['Authorization' => 'vapid t=..,k=..'].
     */
    public static function vapidAuth(string $endpoint, string $publicKeyB64, string $privateKeyB64, string $subject): array
    {
        $u = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');

        $jwtHeader  = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $jwtPayload = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $subject]));
        $signingInput = $jwtHeader . '.' . $jwtPayload;

        $pem = self::privatePem(self::b64ud($privateKeyB64), self::b64ud($publicKeyB64));
        $pkey = openssl_pkey_get_private($pem);
        if (! $pkey) {
            throw new \RuntimeException('Invalid VAPID private key.');
        }
        openssl_sign($signingInput, $der, $pkey, OPENSSL_ALGO_SHA256);
        $jwt = $signingInput . '.' . self::b64u(self::derToRaw($der));

        return ['Authorization' => 'vapid t=' . $jwt . ', k=' . $publicKeyB64];
    }

    /**
     * Encrypt $payload for a subscription (aes128gcm). Returns ['body'=>binary].
     * $ephemeral / $salt are injectable only for test vectors.
     */
    public static function encrypt(string $p256dhB64, string $authB64, string $payload, ?array $ephemeral = null, ?string $salt = null): array
    {
        $uaPublic = self::b64ud($p256dhB64);      // 65 bytes, receiver public
        $authSecret = self::b64ud($authB64);       // 16 bytes

        if ($ephemeral) {
            $asPublic  = $ephemeral['public'];
            $asPrivate = openssl_pkey_get_private(self::privatePem($ephemeral['private'], $asPublic));
        } else {
            $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            $ed = openssl_pkey_get_details($eph);
            $asPublic  = self::point($ed);
            $asPrivate = $eph;
        }

        $peer = openssl_pkey_get_public(self::publicPem($uaPublic));
        $sharedSecret = openssl_pkey_derive($peer, $asPrivate, 32);
        if ($sharedSecret === false) {
            throw new \RuntimeException('ECDH derivation failed.');
        }

        $salt = $salt ?? random_bytes(16);

        // RFC 8291: PRK-combining IKM, then RFC 8188 CEK + nonce.
        $ikm   = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\x00" . $uaPublic . $asPublic, $authSecret);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // Single record: plaintext + 0x02 delimiter (last record), no extra padding.
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        // aes128gcm header: salt(16) | rs(4, uint32) | idlen(1) | keyid(as_public,65)
        $header = $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic;

        return ['body' => $header . $cipher . $tag, 'salt' => $salt, 'asPublic' => $asPublic];
    }

    /**
     * Decrypt an aes128gcm body back to plaintext, given the receiver's key pair + auth.
     * Used to verify the encryption is RFC-correct (round-trip + cross-check vs a known lib).
     */
    public static function decrypt(string $body, string $recvPrivateB64, string $recvPublicB64, string $authB64): string
    {
        $recvPublic = self::b64ud($recvPublicB64);
        $authSecret = self::b64ud($authB64);

        $salt  = substr($body, 0, 16);
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $ct = substr($body, 21 + $idlen);

        $recvPriv = openssl_pkey_get_private(self::privatePem(self::b64ud($recvPrivateB64), $recvPublic));
        $shared = openssl_pkey_derive(self::publicPem($asPublic), $recvPriv, 32);

        $ikm   = hash_hkdf('sha256', $shared, 32, "WebPush: info\x00" . $recvPublic . $asPublic, $authSecret);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $tag = substr($ct, -16);
        $enc = substr($ct, 0, -16);
        $plain = openssl_decrypt($enc, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) {
            throw new \RuntimeException('decrypt failed');
        }

        return rtrim($plain, "\x00\x02");   // strip the record delimiter + any zero padding
    }

    // ---------- helpers ----------

    private static function point(array $details): string
    {
        return "\x04" . self::pad($details['ec']['x']) . self::pad($details['ec']['y']);
    }

    private static function pad(string $b): string
    {
        return str_pad($b, 32, "\x00", STR_PAD_LEFT);
    }

    private static function publicPem(string $point): string
    {
        $der = hex2bin(self::SPKI_PREFIX) . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Build a SEC1 EC private-key PEM from the raw 32-byte scalar + 65-byte public point. */
    private static function privatePem(string $d, string $point): string
    {
        $d = self::pad($d);
        $version   = "\x02\x01\x01";                                   // INTEGER 1
        $privOctet = "\x04\x20" . $d;                                  // OCTET STRING (32)
        $params    = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // [0] namedCurve prime256v1
        $pubBit    = "\xa1\x44\x03\x42\x00" . $point;                  // [1] BIT STRING (0x04||x||y)
        $seq       = $version . $privOctet . $params . $pubBit;
        $der       = "\x30" . chr(strlen($seq)) . $seq;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** DER ECDSA signature (SEQUENCE of two INTEGERs) → raw 64-byte R||S. */
    private static function derToRaw(string $der): string
    {
        $off = 0;
        if (ord($der[$off++]) !== 0x30) {
            throw new \RuntimeException('Bad DER signature.');
        }
        if (ord($der[$off]) & 0x80) {
            $off += 1 + (ord($der[$off]) & 0x7f);
        } else {
            $off++;
        }
        $read = function () use ($der, &$off) {
            $off++; // skip 0x02 INTEGER tag
            $len = ord($der[$off++]);
            $val = substr($der, $off, $len);
            $off += $len;
            return ltrim($val, "\x00");
        };
        $r = $read();
        $s = $read();

        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    public static function b64u(string $b): string
    {
        return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    }

    public static function b64ud(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
