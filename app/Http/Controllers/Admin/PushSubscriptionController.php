<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPush\WebPushCrypto;
use App\Services\WebPushSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /** One-time, super-admin-only setup page for Web Push (no terminal needed; all GET, no CSRF). */
    public function setup(Request $request)
    {
        $me = Auth::guard('admin')->user();
        abort_unless($me && $me->role === 'super', 403);

        // Generate a fresh key pair, try to save it, and ALWAYS show it for copy-paste.
        if ($request->query('generate')) {
            try {
                $keys = WebPushCrypto::generateVapidKeys();
            } catch (\Throwable $e) {
                return response($this->setupHtml('error', $e->getMessage()));
            }
            $wrote = $this->writeEnvKeys($keys['publicKey'], $keys['privateKey']);
            $this->refreshConfig();
            $lines = "VAPID_SUBJECT=" . (config('webpush.subject') ?: 'mailto:admin@apexgrowthsolution.com') . "\n"
                . "VAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}";

            return response($this->setupHtml($wrote ? 'saved' : 'manual', $lines));
        }

        // "I pasted the keys" → drop the cached config so .env is read live.
        if ($request->query('refresh')) {
            $this->refreshConfig();
        }

        return response($this->setupHtml(WebPushSender::enabled() ? 'active' : 'new', ''));
    }

    /** Strip old VAPID_* lines and append a fresh block; returns whether the write succeeded. */
    private function writeEnvKeys(string $public, string $private): bool
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }
        $env = file_get_contents($path);
        $env = preg_replace('/^VAPID_(SUBJECT|PUBLIC_KEY|PRIVATE_KEY)=.*$\n?/m', '', $env);
        $subject = config('webpush.subject') ?: 'mailto:admin@apexgrowthsolution.com';
        $env = rtrim($env, "\n") . "\n\nVAPID_SUBJECT={$subject}\nVAPID_PUBLIC_KEY={$public}\nVAPID_PRIVATE_KEY={$private}\n";

        return (bool) @file_put_contents($path, $env);
    }

    /** Clear the cached config so freshly written .env values take effect without a redeploy. */
    private function refreshConfig(): void
    {
        try { Artisan::call('config:clear'); } catch (\Throwable $e) {}
        // Also reflect the keys in THIS request so the status shows correctly right away.
        try {
            $env = @file_get_contents(base_path('.env')) ?: '';
            if (preg_match('/^VAPID_PUBLIC_KEY=(\S+)/m', $env, $m)) {
                config(['webpush.public_key' => $m[1]]);
            }
            if (preg_match('/^VAPID_PRIVATE_KEY=(\S+)/m', $env, $m)) {
                config(['webpush.private_key' => $m[1]]);
            }
        } catch (\Throwable $e) {}
    }

    private function setupHtml(string $state, string $lines): string
    {
        if ($state === 'active') {
            $body = '<p class="ok">✅ Web Push is <b>active</b>. Notifications will arrive even when the browser is closed.</p>
                <p class="muted">Ask each teammate to click <b>Allow</b> once when the notification prompt appears.</p>
                <p><a class="btn ghost" href="?generate=1">Regenerate keys</a></p>';
        } elseif ($state === 'saved') {
            $body = '<p class="ok">✅ Keys generated and saved to <code>.env</code> automatically — Web Push is now <b>active</b>.</p>
                <p class="muted">For your records (you can ignore these — they’re already saved). Keep the private key secret:</p>
                <textarea readonly onclick="this.select()">' . e($lines) . '</textarea>
                <p><a class="btn" href="?refresh=1">Done — check status</a></p>';
        } elseif ($state === 'manual') {
            $body = '<p class="warn">Keys generated, but the file couldn’t be written automatically. Copy the 3 lines below.</p>
                <p><b>Open cPanel → File Manager → edit <code>.env</code> in your site root</b> (tick “Show Hidden Files” if you don’t see it), paste these at the very bottom, and Save:</p>
                <textarea readonly onclick="this.select()">' . e($lines) . '</textarea>
                <p class="muted">Then come back and click the button below.</p>
                <p><a class="btn" href="?refresh=1">I’ve pasted them — activate</a></p>';
        } elseif ($state === 'error') {
            $body = '<p class="warn">Couldn’t generate keys on the server: <code>' . e($lines) . '</code></p>
                <p class="muted">This usually means the PHP <b>openssl</b> extension is off. Enable it in cPanel → “Select PHP Version” → Extensions, then reload.</p>';
        } else {
            $body = '<p>Web Push is not set up yet. Click below to generate your keys and turn on background/closed-browser notifications.</p>
                <p><a class="btn" href="?generate=1">Generate &amp; enable Web Push</a></p>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Web Push setup</title>
        <style>body{font:15px system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 20px;color:#1e293b}
        h1{font-size:20px}.btn{display:inline-block;text-decoration:none;border:0;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;font:600 14px system-ui;padding:11px 18px;border-radius:10px;cursor:pointer}
        .btn.ghost{background:#eef2f7;color:#475569}.ok{color:#16a34a}.warn{color:#b45309}.muted{color:#64748b;font-size:13px}
        textarea{width:100%;height:110px;font:13px monospace;border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin:8px 0}
        code{background:#f1f5f9;padding:1px 5px;border-radius:5px;word-break:break-all}</style></head>
        <body><h1>🔔 Team Chat — Web Push setup</h1>' . $body . '</body></html>';
    }

    /** Store (or refresh) this browser's push subscription for the signed-in admin. */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:2048'],
            'keys.p256dh'      => ['required', 'string', 'max:255'],
            'keys.auth'        => ['required', 'string', 'max:255'],
            'contentEncoding'  => ['nullable', 'string', 'max:32'],
        ]);

        $me = Auth::guard('admin')->user();

        PushSubscription::updateOrCreate(
            ['admin_id' => $me->id, 'endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint'         => $data['endpoint'],
                'p256dh'           => $data['keys']['p256dh'],
                'auth'             => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
                'last_used_at'     => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /** Remove this browser's subscription (on permission revoke / unsubscribe). */
    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('admin_id', Auth::guard('admin')->id())
                ->where('endpoint_hash', hash('sha256', $endpoint))
                ->delete();
        }

        return response()->json(['ok' => true]);
    }
}
