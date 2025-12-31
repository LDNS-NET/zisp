<?php

return [
    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    |
    | This determines whether to use sandbox or production endpoints.
    | Values: 'sandbox' or 'production'
    |
    */
    'environment' => env('MPESA_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Consumer Key & Secret
    |--------------------------------------------------------------------------
    |
    | Your Daraja API app credentials from developer.safaricom.co.ke
    |
    */
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Business Shortcode
    |--------------------------------------------------------------------------
    |
    | Your business shortcode (Paybill or Till number)
    | Sandbox: 174379
    |
    */
    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    /*
    |--------------------------------------------------------------------------
    | Lipa Na M-Pesa Passkey
    |--------------------------------------------------------------------------
    |
    | Passkey for STK Push. Get from Daraja portal.
    |
    */
    'passkey' => env('MPESA_PASSKEY'),

    /*
    |--------------------------------------------------------------------------
    | Callback & Result URLs
    |--------------------------------------------------------------------------
    |
    | URLs where M-Pesa will send payment notifications and results
    |
    */
    'callback_url' => env('MPESA_CALLBACK_URL', env('APP_URL') . '/mpesa/callback'),
    'result_url' => env('MPESA_RESULT_URL', env('APP_URL') . '/mpesa/result'),
    'timeout_url' => env('MPESA_TIMEOUT_URL', env('APP_URL') . '/mpesa/timeout'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'base_url' => env('MPESA_ENV', 'sandbox') === 'production' 
        ? 'https://api.safaricom.co.ke' 
        : 'https://sandbox.safaricom.co.ke',
];
