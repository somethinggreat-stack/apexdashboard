<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /*
     * Benny's GoHighLevel sub-account. Leave any of these empty to keep the
     * intake sync switched off — the command exits quietly when unconfigured.
     */
    'ghl' => [
        'api_key'                  => env('GHL_API_KEY'),
        'location_id'              => env('GHL_LOCATION_ID'),
        'credit_repair_survey_id'  => env('GHL_CREDIT_REPAIR_SURVEY_ID'),
        'client_id'                => env('GHL_SYNC_CLIENT_ID'),
    ],

];
