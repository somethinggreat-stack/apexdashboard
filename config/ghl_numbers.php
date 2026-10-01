<?php

/*
|--------------------------------------------------------------------------
| GHL number pool — one-time codes
|--------------------------------------------------------------------------
|
| VAs claim one of the sub-account's phone numbers, use it to sign a client up
| somewhere, and read the SMS code here instead of opening GoHighLevel. A number
| held by one VA cannot be used by another until the code lands or the claim
| times out.
|
| Everything the integration needs was measured against the live sub-account,
| not guessed — see App\Services\Ghl\GhlNumbers for what each call actually
| answers with.
|
*/

return [
    'token'       => env('GHL_RMS_TOKEN'),
    'location_id' => env('GHL_RMS_LOCATION_ID'),

    'base_url'    => 'https://services.leadconnectorhq.com',
    'api_version' => '2021-07-28',

    /*
    | THE POOL. Two independent gates, and a number must pass BOTH.
    |
    | +1 205-839-2700 ("Bj's number") is the sub-account's Default Number and
    | carries live customer conversations — payment promises, dispute questions,
    | real people. It must never be claimable, never polled, and never deleted
    | from. Excluding it in config rather than in a view means no screen, route
    | or future helper can reach it by accident.
    */
    'excluded' => [
        '+12058392700',   // Bj's number — live customer line, Default Number
    ],

    // ...and the name must look like a pool number. Belt and braces: a new
    // customer-facing number added later is out until someone puts it in.
    'name_pattern' => '/^Alvina/i',

    /*
    | A claim is released when the code is copied, when the VA releases it, or
    | when this many minutes pass — otherwise a closed laptop retires a number
    | from the pool for good.
    */
    'claim_minutes' => (int) env('GHL_CLAIM_MINUTES', 10),

    // How far back a claim looks for its code. A message older than the claim
    // belongs to whatever happened before it, never to this VA.
    'poll_seconds' => (int) env('GHL_POLL_SECONDS', 5),

    /*
    | Delete the conversation from GHL once the VA has copied the code.
    |
    | Irreversible, and the threads live beside real customer ones, so
    | GhlNumbers::deleteConversationSafely refuses unless EVERY message in it is
    | inbound and addressed to a pool number. Set false to keep the threads.
    */
    'delete_after_copy' => (bool) env('GHL_DELETE_AFTER_COPY', true),

    'timeout' => (int) env('GHL_TIMEOUT', 20),
];
