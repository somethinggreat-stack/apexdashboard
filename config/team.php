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
];
