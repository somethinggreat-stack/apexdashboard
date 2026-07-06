<?php

/*
| Admin/VA accounts, driven by the environment. The super admin sees
| everything; the VAs only work on the business owners. Update these in
| .env — the TeamSeeder syncs them on deploy.
*/

return [
    'super' => [
        'email'    => env('ADMIN_SUPER_EMAIL', 'admin@umair.com'),
        'password' => env('ADMIN_SUPER_PASSWORD'),
        'name'     => env('ADMIN_SUPER_NAME', 'Admin'),
    ],

    'vas' => [
        ['email' => env('VA1_EMAIL'), 'password' => env('VA1_PASSWORD'), 'name' => env('VA1_NAME', 'Zeeshan Ghazi')],
        ['email' => env('VA2_EMAIL'), 'password' => env('VA2_PASSWORD'), 'name' => env('VA2_NAME', 'Raja Khuram')],
        ['email' => env('VA3_EMAIL'), 'password' => env('VA3_PASSWORD'), 'name' => env('VA3_NAME', 'Abid Hussain')],
        ['email' => env('VA4_EMAIL'), 'password' => env('VA4_PASSWORD'), 'name' => env('VA4_NAME', 'Umair Sajid')],
    ],

    // Leads agents — access ONLY the sales leads pipeline (New Leads / In
    // Contact / Pipeline). No business owners, clients, payments, or website leads.
    'leads' => [
        ['email' => env('LEADS1_EMAIL'), 'password' => env('LEADS1_PASSWORD'), 'name' => env('LEADS1_NAME', 'Sanwal')],
    ],
];
