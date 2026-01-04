<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\File;

class HealthCheckController extends Controller
{
    public function index()
    {
        return response()->json([
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk' => $this->checkDiskSpace(),
            'server' => $this->checkServerLoad(),
            'mikrotik' => $this->checkMikrotikConnectivity(),
            'queue' => $this->checkQueueStatus(),
            'errors' => $this->checkRecentErrors(),
            'services' => [
                'intasend' => $this->checkIntaSend(),
            ],
            'last_check' => now()->toDateTimeString(),
        ]);
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkCache()
    {
        try {
            Cache::put('health_check', true, 10);
            if (Cache::get('health_check')) {
                return ['status' => 'healthy', 'message' => 'Working'];
            }
            return ['status' => 'unhealthy', 'message' => 'Cache retrieval failed'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkDiskSpace()
    {
        try {
            $free = disk_free_space(base_path());
            $total = disk_total_space(base_path());
            $usedPercent = round((($total - $free) / $total) * 100, 2);
            
            return [
                'status' => $usedPercent < 90 ? 'healthy' : 'warning',
                'message' => "{$usedPercent}% used",
                'details' => [
                    'free' => $this->formatBytes($free),
                    'total' => $this->formatBytes($total),
                    'used_percent' => $usedPercent
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Could not check disk space'];
        }
    }

    private function checkIntaSend()
    {
        // Simple ping to IntaSend API or check if keys are set
        $publishableKey = config('services.intasend.publishable_key');
        if (!$publishableKey) {
            return ['status' => 'warning', 'message' => 'Keys not configured'];
        }

        try {
            $response = Http::timeout(5)->get('https://payment.intasend.com/api/v1/payment/status/');
            // IntaSend might return 401 if no auth, but if we get a response, the service is up
            return ['status' => 'healthy', 'message' => 'Service reachable'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Service unreachable'];
        }
    }

    private function checkServerLoad()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            try {
                // CPU Load
                $cpu = (int)shell_exec('powershell -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average"');
                
                // RAM Usage
                $memJson = shell_exec('powershell -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_OperatingSystem | Select-Object FreePhysicalMemory, TotalVisibleMemorySize | ConvertTo-Json"');
                $mem = json_decode($memJson, true);
                
                $free = $mem['FreePhysicalMemory'] ?? 0;
                $total = $mem['TotalVisibleMemorySize'] ?? 0;
                $used = $total - $free;
                $ramPercent = $total > 0 ? round(($used / $total) * 100, 2) : 0;

                // Load Average (Processor Queue Length)
                $loadAvg = (int)shell_exec('powershell -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_PerfFormattedData_PerfOS_System).ProcessorQueueLength"');

                // Uptime
                $uptimeSeconds = (int)shell_exec('powershell -ExecutionPolicy Bypass -Command "((Get-Date) - (Get-CimInstance Win32_OperatingSystem).LastBootUpTime).TotalSeconds"');

                return [
                    'status' => ($cpu < 90 && $ramPercent < 90) ? 'healthy' : 'warning',
                    'cpu' => $cpu,
                    'ram' => $ramPercent,
                    'load_avg' => $loadAvg,
                    'uptime' => $this->formatUptime($uptimeSeconds),
                    'message' => "CPU: {$cpu}%, RAM: {$ramPercent}%"
                ];
            } catch (\Exception $e) {
                return [
                    'status' => 'unhealthy', 
                    'message' => 'Monitoring error',
                    'cpu' => 0,
                    'ram' => 0,
                    'load_avg' => 0,
                    'uptime' => 'N/A'
                ];
            }
        }

        return [
            'status' => 'unknown', 
            'message' => 'OS not supported',
            'cpu' => 0,
            'ram' => 0,
            'load_avg' => 0,
            'uptime' => 'N/A'
        ];
    }

    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) return "{$days}d {$hours}h";
        if ($hours > 0) return "{$hours}h {$minutes}m";
        return "{$minutes}m";
    }

    private function checkMikrotikConnectivity()
    {
        try {
            $total = TenantMikrotik::count();
            $online = TenantMikrotik::where('status', 'online')->count();
            $offline = $total - $online;

            return [
                'status' => $offline === 0 ? 'healthy' : ($online > 0 ? 'warning' : 'unhealthy'),
                'total' => $total,
                'online' => $online,
                'offline' => $offline,
                'message' => "{$online}/{$total} Online"
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database error'];
        }
    }

    private function checkQueueStatus()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            return [
                'status' => $failedJobs === 0 ? 'healthy' : 'warning',
                'failed_24h' => $failedJobs,
                'message' => $failedJobs === 0 ? 'Running smoothly' : "{$failedJobs} failed jobs in 24h"
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Could not check queue status'];
        }
    }

    private function checkRecentErrors()
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (!File::exists($logPath)) {
                return ['status' => 'healthy', 'message' => 'No logs found'];
            }

            // Read last 100 lines of log
            $logs = shell_exec('powershell -command "Get-Content ' . $logPath . ' -Tail 100"');
            $errorCount = substr_count(strtoupper($logs), '.ERROR:') + substr_count(strtoupper($logs), '.CRITICAL:');

            return [
                'status' => $errorCount === 0 ? 'healthy' : ($errorCount < 5 ? 'warning' : 'unhealthy'),
                'count' => $errorCount,
                'message' => $errorCount === 0 ? 'No recent errors' : "{$errorCount} errors in recent logs"
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Could not read logs'];
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
