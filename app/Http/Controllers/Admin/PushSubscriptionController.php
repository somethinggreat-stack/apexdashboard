<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
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
