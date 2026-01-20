<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantTrafficAnalytics;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class TrafficAnalyticsController extends Controller
{
    /**
     * Display traffic analytics dashboard
     */
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $period = $request->get('period', '7days'); // 24hours, 7days, 30days, 90days

        return Inertia::render('Analytics/TrafficAnalytics', [
            'topConsumers' => $this->getTopConsumers($tenantId, $period),
            'bandwidthTrends' => $this->getBandwidthTrends($tenantId, $period),
            'protocolBreakdown' => $this->getProtocolBreakdown($tenantId, $period),
            'anomalies' => $this->detectAnomalies($tenantId),
            'period' => $period,
        ]);
    }

    /**
     * Get user bandwidth data for a specific user
     */
    public function getUserBandwidth(Request $request, $userId)
    {
        $tenantId = Auth::user()->tenant_id;
        $period = $request->get('period', '7days');
        $dateRange = $this->getDateRange($period);

        $data = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('DATE(date) as date'),
                DB::raw('SUM(bytes_in) as total_in'),
                DB::raw('SUM(bytes_out) as total_out'),
                DB::raw('SUM(total_bytes) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $data,
            'user' => NetworkUser::find($userId),
        ]);
    }

    /**
     * Get top bandwidth consumers
     */
    private function getTopConsumers($tenantId, $period, $limit = 10)
    {
        $dateRange = $this->getDateRange($period);

        $topUsers = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->select(
                'user_id',
                DB::raw('SUM(total_bytes) as total_usage'),
                DB::raw('SUM(bytes_in) as total_download'),
                DB::raw('SUM(bytes_out) as total_upload')
            )
            ->groupBy('user_id')
            ->orderByDesc('total_usage')
            ->limit($limit)
            ->get();

        // Attach user details
        $topUsers->load('user:id,username,full_name,package_id');

        return $topUsers->map(function ($item) {
            return [
                'user_id' => $item->user_id,
                'username' => $item->user->username ?? 'Unknown',
                'full_name' => $item->user->full_name ?? 'N/A',
                'total_usage' => $item->total_usage,
                'total_download' => $item->total_download,
                'total_upload' => $item->total_upload,
                'usage_formatted' => $this->formatBytes($item->total_usage),
            ];
        });
    }

    /**
     * Get bandwidth trends over time
     */
    private function getBandwidthTrends($tenantId, $period)
    {
        $dateRange = $this->getDateRange($period);
        $groupBy = $period === '24hours' ? 'hour' : 'date';

        $query = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']]);

        if ($period === '24hours') {
            $trends = $query->select(
                'date',
                'hour',
                DB::raw('SUM(bytes_in) as total_in'),
                DB::raw('SUM(bytes_out) as total_out'),
                DB::raw('SUM(total_bytes) as total')
            )
            ->groupBy('date', 'hour')
            ->orderBy('date')
            ->orderBy('hour')
            ->get();
        } else {
            $trends = $query->select(
                DB::raw('DATE(date) as date'),
                DB::raw('SUM(bytes_in) as total_in'),
                DB::raw('SUM(bytes_out) as total_out'),
                DB::raw('SUM(total_bytes) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        }

        return $trends;
    }

    /**
     * Get protocol breakdown
     */
    private function getProtocolBreakdown($tenantId, $period)
    {
        $dateRange = $this->getDateRange($period);

        $breakdown = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('protocol')
            ->select(
                'protocol',
                DB::raw('SUM(total_bytes) as total_usage'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->groupBy('protocol')
            ->orderByDesc('total_usage')
            ->get();

        $total = $breakdown->sum('total_usage');

        return $breakdown->map(function ($item) use ($total) {
            return [
                'protocol' => $item->protocol,
                'usage' => $item->total_usage,
                'usage_formatted' => $this->formatBytes($item->total_usage),
                'percentage' => $total > 0 ? round(($item->total_usage / $total) * 100, 2) : 0,
                'user_count' => $item->user_count,
            ];
        });
    }

    /**
     * Detect usage anomalies
     */
    private function detectAnomalies($tenantId)
    {
        // Get recent usage (last 7 days)
        $recentUsage = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->where('date', '>=', Carbon::now()->subDays(7))
            ->select(
                'user_id',
                DB::raw('SUM(total_bytes) as total_usage'),
                DB::raw('AVG(total_bytes) as avg_daily_usage')
            )
            ->groupBy('user_id')
            ->get();

        // Get historical average (previous 30 days before the last 7)
        $historicalUsage = TenantTrafficAnalytics::where('tenant_id', $tenantId)
            ->whereBetween('date', [
                Carbon::now()->subDays(37),
                Carbon::now()->subDays(7)
            ])
            ->select(
                'user_id',
                DB::raw('AVG(total_bytes) as historical_avg')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $anomalies = [];

        foreach ($recentUsage as $recent) {
            $historical = $historicalUsage->get($recent->user_id);
            
            if ($historical && $historical->historical_avg > 0) {
                $deviation = (($recent->avg_daily_usage - $historical->historical_avg) / $historical->historical_avg) * 100;
                
                // Flag if deviation > 200% (3x normal usage)
                if (abs($deviation) > 200) {
                    $user = NetworkUser::find($recent->user_id);
                    
                    $anomalies[] = [
                        'user_id' => $recent->user_id,
                        'username' => $user->username ?? 'Unknown',
                        'recent_avg' => $recent->avg_daily_usage,
                        'historical_avg' => $historical->historical_avg,
                        'deviation_percent' => round($deviation, 2),
                        'severity' => abs($deviation) > 500 ? 'critical' : 'warning',
                        'type' => $deviation > 0 ? 'spike' : 'drop',
                    ];
                }
            }
        }

        return collect($anomalies)->sortByDesc('deviation_percent')->values();
    }

    /**
     * Get date range based on period
     */
    private function getDateRange($period)
    {
        $end = Carbon::now();
        
        switch ($period) {
            case '24hours':
                $start = Carbon::now()->subDay();
                break;
            case '7days':
                $start = Carbon::now()->subDays(7);
                break;
            case '30days':
                $start = Carbon::now()->subDays(30);
                break;
            case '90days':
                $start = Carbon::now()->subDays(90);
                break;
            default:
                $start = Carbon::now()->subDays(7);
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
