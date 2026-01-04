<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HealthCheckController extends Controller
{
    public function index()
    {
        return response()->json([
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk' => $this->checkDiskSpace(),
            'services' => [
                'intasend' => $this->checkIntaSend(),
                // Add more services here
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
