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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'aliexpress' => [
        'app_key' => env('ALIEXPRESS_APP_KEY'),
        'app_secret' => env('ALIEXPRESS_APP_SECRET'),
        'tracking_id' => env('ALIEXPRESS_TRACKING_ID'),

        // Endpoint oficial de la Open Platform (region singapore).
        'base_url' => env('ALIEXPRESS_BASE_URL', 'https://api-sg.aliexpress.com/sync'),

        // md5 = md5(secret + params + secret); hmac = HMAC-MD5 de los params.
        'sign_method' => env('ALIEXPRESS_SIGN_METHOD', 'md5'),

        // Se usan cuando el administrador no escribe nada en el panel.
        'default_keyword' => env('ALIEXPRESS_DEFAULT_KEYWORD', 'bluetooth earbuds'),
        'margin_pct' => (float) env('ALIEXPRESS_MARGIN_PCT', 30),
    ],

];
