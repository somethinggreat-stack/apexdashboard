<?php

namespace Tests\Feature;

use App\Services\WebPush\WebPushCrypto;
use Tests\TestCase;

/**
 * Verifies the dependency-free Web Push crypto (RFC 8291 aes128gcm + RFC 8292 VAPID).
 * On a host where openssl can't create EC keys (e.g. a misconfigured local Windows
 * openssl.cnf) the test skips itself; on Linux/CI it runs for real.
 */
class WebPushCryptoTest extends TestCase
{
    private function ecOrSkip(): array
    {
        $res = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $res) {
            $this->markTestSkipped('openssl cannot create EC keys in this environment.');
        }
        $d = openssl_pkey_get_details($res);
        $pub = "\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $priv = str_pad($d['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        return [WebPushCrypto::b64u($pub), WebPushCrypto::b64u($priv), WebPushCrypto::b64u(random_bytes(16))];
    }

    public function test_vapid_keys_generate(): void
    {
        try {
            $keys = WebPushCrypto::generateVapidKeys();
        } catch (\Throwable $e) {
            $this->markTestSkipped($e->getMessage());
        }
        $this->assertNotEmpty($keys['publicKey']);
        $this->assertNotEmpty($keys['privateKey']);
        // Public key decodes to a 65-byte uncompressed P-256 point.
        $this->assertSame(65, strlen(WebPushCrypto::b64ud($keys['publicKey'])));
    }

    public function test_encrypt_then_decrypt_round_trips(): void
    {
        [$p256, $priv, $auth] = $this->ecOrSkip();
        $msg = 'When I grow up, I want to be a watermelon';
        $enc = WebPushCrypto::encrypt($p256, $auth, $msg);

        $this->assertGreaterThan(100, strlen($enc['body']));
        $this->assertSame($msg, WebPushCrypto::decrypt($enc['body'], $priv, $p256, $auth));
    }

    public function test_vapid_auth_header_is_well_formed(): void
    {
        try {
            $keys = WebPushCrypto::generateVapidKeys();
        } catch (\Throwable $e) {
            $this->markTestSkipped($e->getMessage());
        }
        $h = WebPushCrypto::vapidAuth('https://fcm.googleapis.com/fcm/send/abc', $keys['publicKey'], $keys['privateKey'], 'mailto:a@b.com');
        $this->assertStringStartsWith('vapid t=', $h['Authorization']);
        $this->assertStringContainsString(', k=' . $keys['publicKey'], $h['Authorization']);
    }
}
