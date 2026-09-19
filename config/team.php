<?php

/*
| The super admin account, driven by the environment. TeamSeeder guarantees
| this one account exists on every deploy so login can never break.
|
| VAs and leads agents are NOT configured here — they are added and removed
| from the Users & Activity screen (pick the role on add). The database is the
| single source of truth for them; nothing here re-creates a deleted account.
*/

return [
    'super' => [
        'email'    => env('ADMIN_SUPER_EMAIL', 'admin@umair.com'),
        'password' => env('ADMIN_SUPER_PASSWORD'),
        'name'     => env('ADMIN_SUPER_NAME', 'Admin'),
    ],

    /*
    | Team Chat upload ceiling for ONE message (all its files + form data), in MiB.
    | The chat host sits behind Cloudflare, which rejects request bodies over
    | 100 MiB (measured 2026-09-17: 104,857,600 bytes accepted, 106,000,000
    | rejected with Cloudflare's own 413 page). The chat also never exceeds PHP's
    | post_max_size / upload_max_filesize — the effective limits are the smallest
    | of all of these (see TeamMessageController::uploadLimits()).
    */
    'chat' => [
        // 100 = the measured Cloudflare ceiling. uploadLimits() subtracts 256 KB of headroom for
        // the multipart framing and form fields, so the request itself always stays under it.
        'max_request_mb' => (int) env('TEAM_CHAT_MAX_REQUEST_MB', 100),

        /*
        | How long a Team Chat sign-in stays valid. The desktop app lives in the tray all
        | shift, so the dashboard's 2-hour SESSION_LIFETIME would expire under a VA and make
        | their next message fail with "Reconnecting…". Chat requests extend their own session
        | to this instead (never shorter than the app-wide setting).
        */
        'session_days' => (int) env('TEAM_CHAT_SESSION_DAYS', 30),
    ],
];
