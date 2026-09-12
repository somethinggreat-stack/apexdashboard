<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public intake base URL
    |--------------------------------------------------------------------------
    |
    | The client-facing "Secure Intake Link" is built from this base when set,
    | instead of the app's own domain. Point it at a free reverse-proxy host
    | (e.g. a Cloudflare Worker: https://securecreditintake.<acct>.workers.dev)
    | so clients never see the app's real domain. Leave empty to use APP_URL.
    |
    */
    'public_base' => env('INTAKE_PUBLIC_BASE', ''),
];
