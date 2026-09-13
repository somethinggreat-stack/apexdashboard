<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Minishlink\WebPush\VAPID;

class PushSubscriptionController extends Controller
{
    /** One-time, super-admin-only setup page for Web Push (no terminal needed). */
    public function setup(Request $request)
    {
        $me = Auth::guard('admin')->user();
        abort_unless($me && $me->role === 'super', 403);

        $configured = WebPushSender::enabled();

        // POST = generate keys, try to save to .env automatically.
        if ($request->isMethod('post')) {
            $keys = VAPID::createVapidKeys();
            $path = base_path('.env');
            $wrote = false;

            if (is_file($path) && is_writable($path)) {
                $env = file_get_contents($path);
                $env = preg_replace('/^VAPID_(SUBJECT|PUBLIC_KEY|PRIVATE_KEY)=.*$\n?/m', '', $env);
                $subject = config('webpush.subject') ?: 'mailto:admin@apexgrowthsolution.com';
                $env = rtrim($env, "\n") . "\n\nVAPID_SUBJECT={$subject}\n"
                    . "VAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}\n";
                file_put_contents($path, $env);
                $wrote = true;
                try { Artisan::call('config:cache'); } catch (\Throwable $e) {}
            }

            $lines = "VAPID_SUBJECT=mailto:admin@apexgrowthsolution.com\n"
                . "VAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}";

            return response($this->setupHtml($wrote ? 'saved' : 'manual', $lines));
        }

        return response($this->setupHtml($configured ? 'active' : 'new', ''));
    }

    private function setupHtml(string $state, string $lines): string
    {
        $token = csrf_token();
        $body = '';
        if ($state === 'active') {
            $body = '<p class="ok">✅ Web Push is <b>active</b>. Notifications will arrive even when the browser is closed.</p>
                <form method="post"><input type="hidden" name="_token" value="' . $token . '">
                <button class="btn ghost">Regenerate keys</button></form>';
        } elseif ($state === 'saved') {
            $body = '<p class="ok">✅ Keys generated and saved automatically. Web Push is now <b>active</b>.</p>
                <p class="muted">You can close this page. Ask each teammate to click <b>Allow</b> once when the notification prompt appears.</p>';
        } elseif ($state === 'manual') {
            $body = '<p class="warn">Keys were generated but couldn’t be saved automatically (file not writable).</p>
                <p>Open <b>cPanel → File Manager</b>, edit the <code>.env</code> file in your site root, paste these 3 lines at the bottom, save — then reload this page:</p>
                <textarea readonly onclick="this.select()">' . e($lines) . '</textarea>
                <p class="muted">Keep the private key secret.</p>';
        } else {
            $body = '<p>Web Push is not set up yet. Click below to generate your keys and turn on background/closed-browser notifications.</p>
                <form method="post"><input type="hidden" name="_token" value="' . $token . '">
                <button class="btn">Generate &amp; enable Web Push</button></form>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Web Push setup</title>
        <style>body{font:15px system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 20px;color:#1e293b}
        h1{font-size:20px}.btn{border:0;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;font:600 14px system-ui;padding:11px 18px;border-radius:10px;cursor:pointer}
        .btn.ghost{background:#eef2f7;color:#475569}.ok{color:#16a34a}.warn{color:#b45309}.muted{color:#64748b;font-size:13px}
        textarea{width:100%;height:120px;font:13px monospace;border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin:8px 0}
        code{background:#f1f5f9;padding:1px 5px;border-radius:5px}</style></head>
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
