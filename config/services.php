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

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    ],

    'kopokopo' => [
        'client_id' => env('KOPOKOPO_CLIENT_ID'),
        'client_secret' => env('KOPOKOPO_CLIENT_SECRET'),
        'till' => env('KOPOKOPO_TILL_NUMBER'),
        'callback_url' => env('KOPOKOPO_CALLBACK_URL', env('APP_URL') . '/kopokopo/webhook'),
        'base_url' => env('KOPOKOPO_ENV', 'sandbox') === 'production'
            ? 'https://kopokopo.com'
            : 'https://sandbox.kopokopo.com',
    ],

    // System-level Talksasa SMS credentials (used as default gateway)
    'talksasa' => [
        'api_key' => env('TALKSASA_API_KEY'),
        'sender_id' => env('TALKSASA_SENDER_ID'),
    ],

];
