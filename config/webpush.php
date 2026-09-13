<?php

return [
    /*
     | VAPID keys identify this application server to the browser push services.
     | Generate a pair once with:  php artisan webpush:vapid
     | then paste the three values into your server .env (they are NOT committed).
     */
    'subject'     => env('VAPID_SUBJECT', 'mailto:admin@apexgrowthsolution.com'),
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    // web-push per-message TTL (seconds the push service keeps trying to deliver).
    'ttl' => (int) env('VAPID_TTL', 1800),
];
