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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'crm' => [
        'api_key' => env('CRM_API_KEY'),
    ],

    'stripe' => [
        'key'            => env('STRIPE_CLIENT_ID'),
        'secret'         => env('STRIPE_API_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'postpay' => [
        'merchant_id' => env('POSTPAY_MERCHANT_ID'),
        'secret'      => env('POSTPAY_SECRET_ID'),
        'sandbox'     => env('POSTPAY_SANDBOX', false),
    ],

    'tunerstop' => [
        'order_status_sync_enabled' => env('TUNERSTOP_ORDER_STATUS_SYNC_ENABLED', false),
        'order_status_sync_url' => env('TUNERSTOP_ORDER_STATUS_SYNC_URL'),
        'token' => env('TUNERSTOP_API_TOKEN'),
    ],

];
