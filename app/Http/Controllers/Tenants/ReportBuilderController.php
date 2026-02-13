<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantCustomReport;
use App\Models\Tenants\TenantReportRun;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantTrafficAnalytics;
use App\Models\Tenants\TenantReportDataPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exports\UniversalReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ReportBuilderController extends Controller
{
    /**
     * List saved reports and available metrics
     */
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        
        $reports = TenantCustomReport::where('tenant_id', $tenantId)
            ->with(['runs' => function($q) {
                $q->latest()->limit(5);
            }])
            ->latest()
            ->paginate(10, ['*'], 'reports_page');

        // High-Level Intelligence Aggregation
        $now = Carbon::now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $tenantPayments = TenantPayment::where('tenant_id', $tenantId)->where('status', 'success');

        $thisMonthRevenue = (clone $tenantPayments)
            ->whereBetween('paid_at', [$thisMonthStart, $now])
            ->sum('amount');

        $lastMonthRevenue = (clone $tenantPayments)
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        $activeSubscribers = NetworkUser::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $newSubscribersLast30 = NetworkUser::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        $subscriberGrowth = $activeSubscribers > 0 
            ? ($newSubscribersLast30 / $activeSubscribers) * 100 
            : 0;

        $arpu = $activeSubscribers > 0 ? $thisMonthRevenue / $activeSubscribers : 0;

        // Top 3 Zones for Dashboard Summary
        $topZones = DB::table('tenant_payments')
            ->join('network_users', 'tenant_payments.user_id', '=', 'network_users.id')
            ->where('tenant_payments.tenant_id', $tenantId)
            ->where('tenant_payments.status', 'success')
            ->select('network_users.location as zone', DB::raw('SUM(amount) as revenue'))
            ->groupBy('network_users.location')
            ->orderBy('revenue', 'desc')
            ->limit(3)
            ->get();

        $intelligence = [
            'revenue' => [
                'current' => (float)$thisMonthRevenue,
                'growth' => round($revenueGrowth, 1),
                'label' => 'Monthly Revenue (MRR)'
            ],
            'subscribers' => [
                'current' => $activeSubscribers,
                'growth' => round($subscriberGrowth, 1),
                'label' => 'Active Subscribers'
            ],
            'arpu' => [
                'current' => round($arpu, 2),
                'label' => 'Avg Revenue Per User (ARPU)'
            ],
            'top_zones' => $topZones
        ];

        $availableMetrics = [
            'revenue' => [
                'name' => 'Total Revenue',
                'description' => 'Real-time sum of successful payments',
                'table' => 'tenant_payments',
                'dimensions' => ['date', 'payment_method'],
            ],
            'users_active' => [
                'name' => 'Subscriber Growth',
                'description' => 'Detailed view of user registrations',
                'table' => 'network_users',
                'dimensions' => ['date', 'type'],
            ],
            'traffic_total' => [
                'name' => 'Network Traffic',
                'description' => 'Total bytes transferred (telemetry)',
                'table' => 'tenant_traffic_analytics',
                'dimensions' => ['date', 'hour'],
            ],
            'new_leads' => [
                'name' => 'Marketing Leads',
                'description' => 'Count of new sales inquiries',
                'table' => 'tenant_leads',
                'dimensions' => ['date', 'status'],
            ],
            'manual_data' => [
                'name' => 'Operational Metrics',
                'description' => 'User-inputted manual data entries',
                'table' => 'tenant_report_data_points',
                'dimensions' => ['date', 'category', 'created_by'],
            ]
        ];

        // Paginated Collection Activity (Professional Scale)
        $recentDataPoints = TenantReportDataPoint::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->latest()
            ->paginate(15, ['*'], 'data_points_page')
            ->through(fn($item) => [
                'id' => $item->id,
                'category' => $item->category,
                'value' => $item->value,
                'description' => $item->description,
                'creator' => $item->creator ? ['name' => $item->creator->name] : null,
                'created_at' => $item->created_at->toDateTimeString(),
            ]);

        // Revenue by Zone (Professional Search & Pagination)
        $zoneSearch = $request->input('zone_search');
        $zoneQuery = DB::table('tenant_payments')
            ->join('network_users', 'tenant_payments.user_id', '=', 'network_users.id')
            ->where('tenant_payments.tenant_id', $tenantId)
            ->where('tenant_payments.status', 'success');

        if ($zoneSearch) {
            $zoneQuery->where('network_users.location', 'like', "%{$zoneSearch}%");
        }

        $zoneRevenue = $zoneQuery->select(
                'network_users.location as zone',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(tenant_payments.amount) as total_revenue')
            )
            ->groupBy('network_users.location')
            ->orderBy('total_revenue', 'desc')
            ->paginate(12, ['*'], 'zone_page')
            ->withQueryString();

        return Inertia::render('Analytics/ReportBuilder', [
            'reports' => $reports,
            'metrics' => $availableMetrics,
            'recentDataPoints' => $recentDataPoints,
            'intelligence' => $intelligence,
            'zoneRevenue' => $zoneRevenue,
            'filters' => [
                'zone_search' => $zoneSearch
            ]
        ]);
    }

    /**
     * Save a new report configuration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'required|array',
            'schedule' => 'nullable|array',
        ]);

        $report = TenantCustomReport::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $validated['name'],
            'config' => $validated['config'],
            'schedule' => $validated['schedule'],
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Report configuration saved.');
    }

    /**
     * Trigger report generation
     */
    public function generate(TenantCustomReport $report)
    {
        $tenantId = Auth::user()->tenant_id;
        $run = TenantReportRun::create([
            'report_id' => $report->id,
            'generated_at' => now(),
            'status' => 'processing',
        ]);

        try {
            $config = $report->config;
            $data = $this->getMetricData($tenantId, $config);
            
            $filename = 'reports/tenant_' . $tenantId . '/report_' . $report->id . '_' . time() . '.xlsx';
            
            Excel::store(new UniversalReportExport($data['results'], $data['headers']), $filename, 'public');
            
            $run->update([
                'status' => 'completed',
                'file_path' => Storage::url($filename),
                'generated_at' => now(),
            ]);

            return back()->with('success', 'Intelligence report generated successfully.');
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return back()->with('error', 'Report failure: ' . $e->getMessage());
        }
    }

    /**
     * Professional Metric Aggregation Logic
     */
    private function getMetricData($tenantId, $config)
    {
        $metric = $config['metric'];
        $days = $this->getDateRangeDays($config['filters']['date_range'] ?? 'last_30_days');
        $startDate = Carbon::now()->subDays($days);

        $query = DB::table($this->getMetricTable($metric))
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate);

        switch ($metric) {
            case 'revenue':
                $results = $query->select(
                    DB::raw('DATE(paid_at) as date'),
                    DB::raw('SUM(amount) as total_revenue'),
                    'payment_method'
                )
                ->where('status', 'success')
                ->groupBy('date', 'payment_method')
                ->get();
                $headers = ['Date', 'Amount', 'Method'];
                break;

            case 'users_active':
                $results = $query->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as new_subscribers')
                )
                ->groupBy('date')
                ->get();
                $headers = ['Date', 'New Subscribers'];
                break;

            default:
                $results = $query->limit(100)->get();
                $headers = array_keys((array) ($results[0] ?? []));
        }

        return ['results' => $results, 'headers' => $headers];
    }

    private function getMetricTable($metric)
    {
        $map = [
            'revenue' => 'tenant_payments',
            'users_active' => 'network_users',
            'traffic_total' => 'tenant_traffic_analytics',
            'new_leads' => 'tenant_leads',
            'manual_data' => 'tenant_report_data_points'
        ];
        return $map[$metric] ?? 'tenant_report_data_points';
    }

    private function getDateRangeDays($range)
    {
        $ranges = [
            'last_7_days' => 7,
            'last_30_days' => 30,
            'last_90_days' => 90,
            'this_year' => 365
        ];
        return $ranges[$range] ?? 30;
    }

    /**
     * Delete report configuration
     */
    public function destroy(TenantCustomReport $report)
    {
        $report->delete();
        return back()->with('success', 'Report deleted.');
    }

    /**
     * Store a manual data point
     */
    public function storeDataPoint(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'value' => 'nullable|numeric',
            'description' => 'required|string|max:1000',
        ]);

        TenantReportDataPoint::create([
            'tenant_id' => Auth::user()->tenant_id,
            'category' => $validated['category'],
            'value' => $validated['value'],
            'description' => $validated['description'],
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Data point recorded successfully.');
    }

    /**
     * Update a manual data point
     */
    public function updateDataPoint(Request $request, TenantReportDataPoint $dataPoint)
    {
        $this->authorizeAccess($dataPoint);

        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'value' => 'nullable|numeric',
            'description' => 'required|string|max:1000',
        ]);

        $dataPoint->update($validated);

        return back()->with('success', 'Data point updated.');
    }

    /**
     * Delete a manual data point
     */
    public function destroyDataPoint(TenantReportDataPoint $dataPoint)
    {
        $this->authorizeAccess($dataPoint);
        $dataPoint->delete();
        return back()->with('success', 'Data point removed.');
    }

    /**
     * Update report configuration
     */
    public function update(Request $request, TenantCustomReport $report)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'required|array',
            'schedule' => 'nullable|array',
        ]);

        $report->update([
            'name' => $validated['name'],
            'config' => $validated['config'],
            'schedule' => $validated['schedule'],
        ]);

        return back()->with('success', 'Report configuration updated.');
    }

    /**
     * Helper to authorize access to data points
     */
    private function authorizeAccess(TenantReportDataPoint $dataPoint)
    {
        if ($dataPoint->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        // Allow creator or admin
        if ($dataPoint->created_by !== Auth::id() && !Auth::user()->hasRole('tenant_admin')) {
            abort(403, 'Unauthorized to modify this data entry.');
        }
    }
}
