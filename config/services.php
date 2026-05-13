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

    'qontak' => [
        'client_id' => env('QONTAK_CLIENT_ID'),
        'client_secret' => env('QONTAK_CLIENT_SECRET'),
        'refresh_token' => env('QONTAK_REFRESH_TOKEN'),
        'channel_integration_id' => env('QONTAK_CHANNEL_INTEGRATION_ID'),
        'template_id' => env('QONTAK_TEMPLATE_ID'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
        'prefix' => env('MIDTRANS_PREFIX', 'GATENSI'),
        'payment_methods' => [
            'credit_card' => env('MIDTRANS_CC_ENABLED', true),
            'bca_va' => env('MIDTRANS_BCA_ENABLED', true),
            'bni_va' => env('MIDTRANS_BNI_ENABLED', true),
            'mandiri_va' => env('MIDTRANS_MANDIRI_ENABLED', true),
            'gopay' => env('MIDTRANS_GOPAY_ENABLED', true),
        ],
    ],

];
