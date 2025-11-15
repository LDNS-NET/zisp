<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZISP Trusted IP
    |--------------------------------------------------------------------------
    |
    | The IP address (with CIDR notation) that is allowed to access
    | the Mikrotik API service. This IP should be your ZISP server's
    | public IP address.
    |
    | Example: '207.154.232.10/32'
    |
    */
    'trusted_ip' => env('ZISP_TRUSTED_IP', '207.154.232.10/32'),
];

