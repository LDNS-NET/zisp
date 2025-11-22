<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WireGuard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for WireGuard VPN server integration.
    |
    */

    // Server endpoint (public IP or hostname)
    'server_endpoint' => env('WG_SERVER_ENDPOINT', '159.89.111.189'),

    // Server public key (base64 encoded)
    'server_public_key' => env('WG_SERVER_PUBLIC_KEY', ''),

    // WireGuard subnet (CIDR notation)
    'subnet' => env('WG_SUBNET', '10.100.0.0/16'),

    // Server listening port
    'server_port' => env('WG_SERVER_PORT', 51820),

    // WireGuard interface name on server
    'wg_interface' => env('WG_INTERFACE', 'wg0'),

    // WireGuard binary path
    'wg_binary' => env('WG_BINARY', '/usr/bin/wg'),
];

