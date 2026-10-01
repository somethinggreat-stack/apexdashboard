<?php

/*
|--------------------------------------------------------------------------
| cPanel — mailbox creation for the CFPB workflow
|--------------------------------------------------------------------------
|
| VAs create a throwaway mailbox per client, use it to sign the client up to
| CFPB, and read the OTP in webmail. The dashboard creates and deletes those
| mailboxes so nobody needs a cPanel login of their own.
|
| The API token lives ONLY in .env. It is never logged and never reaches the
| browser: App\Services\Cpanel\CpanelMail is the single place that holds it,
| and it exposes three operations — create, delete, list. There is deliberately
| no generic "call any cPanel function" method, because a token inherits every
| permission the cPanel user has.
|
*/

return [
    'host' => env('CPANEL_HOST'),
    'port' => (int) env('CPANEL_PORT', 2083),
    'user' => env('CPANEL_USER'),

    // Created in cPanel → API Tokens. Shown once; keep it in .env on the server.
    'token' => env('CPANEL_API_TOKEN'),

    // The domain new mailboxes are created under.
    'mail_domain' => env('CPANEL_MAIL_DOMAIN', 'apexgrowthsolution.com'),

    // Megabytes per mailbox. cPanel treats 0 as unlimited — we never send 0.
    'quota_mb' => (int) env('CPANEL_MAILBOX_QUOTA_MB', 100),

    /*
    | Addresses this app must never create or delete, whatever is asked of it.
    | These are real business inboxes; a mistyped form or a stray click must not
    | be able to reach them. Matched on the local part, case-insensitively.
    */
    'protected' => [
        'hello', 'info', 'admin', 'billing', 'support', 'sales', 'contact',
        'noreply', 'no-reply', 'postmaster', 'abuse', 'webmaster', 'office',
        'accounts', 'accounting', 'care', 'help', 'team', 'mail', 'email',
    ],

    // Where "Open Webmail" points. cPanel serves webmail on 2096 over TLS.
    'webmail_url' => env('CPANEL_WEBMAIL_URL'),

    // Seconds before a cPanel call is abandoned. The UI must never hang on it.
    'timeout' => (int) env('CPANEL_TIMEOUT', 20),
];
