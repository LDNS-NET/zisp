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

    'intasend' => [
        'public_key' => env('INTASEND_PUBLIC_KEY'),
        'secret_key' => env('INTASEND_SECRET_KEY'),
        'test_env' => env('INTASEND_TEST_ENV', false),
        'base_url' => env('INTASEND_TEST_ENV', false) 
            ? 'https://api.intasend.com/api/v1' 
            : 'https://api.intasend.com/api/v1',
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'genieacs' => [
        'nbi_url' => env('GENIEACS_NBI_URL', 'http://127.0.0.1:7557'),
        'cwmp_url' => env('GENIEACS_CWMP_URL', 'http://127.0.0.1:7547'),
        'username' => env('GENIEACS_USERNAME'),
        'password' => env('GENIEACS_PASSWORD'),
    ],

    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        'environment' => env('QUICKBOOKS_ENVIRONMENT', 'sandbox'),
    ],

];
