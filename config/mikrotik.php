<?php

return [
    'host' => env('MIKROTIK_HOST', '192.168.88.1'),
    'username' => env('MIKROTIK_USERNAME', 'admin'),
    'password' => env('MIKROTIK_PASSWORD', 'password'),
    'port' => (int) env('MIKROTIK_PORT', 8728),
];
