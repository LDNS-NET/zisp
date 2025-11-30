<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WireGuard Interface
    |--------------------------------------------------------------------------
    |
    | The name of the WireGuard interface on the server (e.g., wg0, wg1)
    |
    */
    'wg_interface' => env('WG_INTERFACE', 'wg0'),

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | WireGuard server endpoint and keys
    |
    */
    'server_endpoint' => env('WG_SERVER_ENDPOINT'),
    'server_port' => env('WG_SERVER_PORT', 51820),
    'server_public_key' => env('WG_SERVER_PUBLIC_KEY'),
    'server_private_key' => env('WG_SERVER_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | VPN Subnet
    |--------------------------------------------------------------------------
    |
    | Unified VPN subnet for all routers (10.100.0.0/16)
    | Server is always 10.100.0.1/16
    |
    */
    'subnet' => env('WG_SUBNET', '10.100.0.0/16'),
    'server_address' => env('WG_SERVER_ADDRESS', '10.100.0.1/16'),

    /*
    |--------------------------------------------------------------------------
    | File Paths
    |--------------------------------------------------------------------------
    |
    | Paths to WireGuard configuration and backups
    |
    */
    'config_path' => env('WG_CONFIG_PATH', '/etc/wireguard/wg0.conf'),
    'backup_dir' => env('WG_BACKUP_DIR', '/etc/wireguard/backups'),
    'wg_binary' => env('WG_BINARY', '/usr/bin/wg'),
    'wg_quick_binary' => env('WG_QUICK_BINARY', '/usr/bin/wg-quick'),

    /*
    |--------------------------------------------------------------------------
    | Backup Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep configuration backups
    |
    */
    'backup_retention_days' => env('WG_BACKUP_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Automatic Sync
    |--------------------------------------------------------------------------
    |
    | Enable automatic synchronization when database changes occur
    |
    */
    'auto_sync_enabled' => env('WG_AUTO_SYNC', true),
];
