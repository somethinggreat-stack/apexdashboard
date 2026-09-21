<?php

/*
| JARVIS — a read-only API for the owner's own assistant, running on his machine.
|
| The whole surface is OFF unless JARVIS_API_TOKEN is set: routes/api-jarvis.php
| refuses to register a single route without it, so an un-configured deploy has no
| JARVIS attack surface at all rather than one guarded by an empty string.
|
| Nothing here ever writes. See docs/JARVIS_API.md.
*/

return [
    // A long random string, generated once and kept in .env on both ends.
    // Never logged: JarvisToken logs the route and params, never the credential.
    'token' => env('JARVIS_API_TOKEN'),

    // Requests per minute, per token. Generous for one assistant, useless for a scraper.
    'rate_limit' => (int) env('JARVIS_RATE_LIMIT', 60),

    'pagination' => [
        'default' => 50,
        'max'     => 200,
    ],

    // Client notes are excluded from every response on purpose. VAs paste report
    // details and client specifics into them, so they cannot be treated as
    // PII-free. Flipping this to true would put that text on the wire — don't,
    // unless notes have actually been audited.
    'expose_notes' => false,
];
