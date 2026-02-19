<?php

use App\Models\Tenants\TenantActiveUsers;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = TenantActiveUsers::withoutGlobalScopes()
    ->select('username', 'ip_address', 'bytes_in', 'bytes_out', 'status', 'last_seen_at')
    ->orderByRaw('(bytes_in + bytes_out) DESC')
    ->limit(10)
    ->get();

echo "Top 10 consumers in tenant_active_users:\n";
foreach ($users as $user) {
    echo "User: {$user->username}, IP: {$user->ip_address}, In: {$user->bytes_in}, Out: {$user->bytes_out}, Status: {$user->status}, Last Seen: {$user->last_seen_at}\n";
}

$allCount = TenantActiveUsers::withoutGlobalScopes()->count();
$activeCount = TenantActiveUsers::withoutGlobalScopes()->where('status', 'active')->count();
echo "\nTotal sessions: $allCount, Active sessions: $activeCount\n";

$zeroUsageCount = TenantActiveUsers::withoutGlobalScopes()->where('bytes_in', 0)->where('bytes_out', 0)->count();
echo "Sessions with zero usage: $zeroUsageCount\n";
