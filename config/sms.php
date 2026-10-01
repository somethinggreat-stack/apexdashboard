<?php

/*
|--------------------------------------------------------------------------
| SMS number pool — one-time codes
|--------------------------------------------------------------------------
|
| VAs claim a pooled phone number, use it to sign a client up somewhere, and
| read the code here. A number held by one VA cannot be used by another until
| the code lands or the claim times out.
|
| Plivo pushes every inbound SMS to our webhook the moment it arrives, carrying
| the receiving number, the sender and the text. That replaced a polling
| integration against GoHighLevel, which could not be made to work: the only
| endpoint that would return a code thread was the conversation SEARCH — both
| GET /conversations/{id}/messages and GET /conversations/{id} answered
| 400 CONVERSATIONS_CONVERSATION_NOT_FOUND for exactly those threads.
|
| A push also removes the need to delete anything: the message only ever
| arrives here, so there is no second inbox to tidy up and no customer thread
| to delete by mistake.
|
*/

return [
    'provider' => 'plivo',

    'plivo' => [
        // Dashboard → Account → Keys & Credentials.
        'auth_id'    => env('PLIVO_AUTH_ID'),
        'auth_token' => env('PLIVO_AUTH_TOKEN'),
        'base_url'   => 'https://api.plivo.com/v1',
    ],

    /*
    | The public URL Plivo posts to, exactly as entered in their dashboard.
    |
    | It is part of what the signature is computed over, so it must match to the
    | character — scheme, host and path. A trailing slash here and not there is
    | enough to make every webhook look forged.
    */
    'webhook_url' => env('SMS_WEBHOOK_URL', 'https://apexgrowthsolution.com/sms/plivo/inbound'),

    /*
    | Refuse a webhook whose signature does not check out. Only ever turn this
    | off to diagnose a mismatch, and only for as long as that takes: with it
    | off, anyone who knows the URL can post a code into the dashboard.
    */
    'verify_signature' => (bool) env('SMS_VERIFY_SIGNATURE', true),

    /*
    | A claim is released when the code is copied, when the VA releases it, or
    | when this many minutes pass — otherwise a closed laptop retires a number
    | from the pool for good.
    */
    'claim_minutes' => (int) env('SMS_CLAIM_MINUTES', 10),

    // How often the page asks our OWN database whether a code has landed.
    // Nothing leaves the server, so this can be brisk.
    'poll_seconds' => (int) env('SMS_POLL_SECONDS', 3),

    // Numbers that must never be claimable, whatever the provider reports.
    'excluded' => [],

    'timeout' => (int) env('SMS_TIMEOUT', 20),
];
