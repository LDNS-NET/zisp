<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantReportDataPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class FinancialAnalyticsController extends Controller
{
    /**
     * Display financial intelligence dashboard
     */
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $currency = Auth::user()->tenant?->currency ?? 'KES';

        // Base query for zone revenue
        $zoneQuery = NetworkUser::select('network_users.location', DB::raw('SUM(COALESCE(packages.price, tenant_hotspot_packages.price, 0)) as revenue'))
            ->leftJoin('packages', 'network_users.package_id', '=', 'packages.id')
            ->leftJoin('tenant_hotspot_packages', 'network_users.hotspot_package_id', '=', 'tenant_hotspot_packages.id')
            ->whereNotNull('network_users.location')
            ->where('network_users.location', '!=', '')
            ->groupBy('network_users.location')
            ->orderByDesc('revenue');

        if ($request->search) {
            $zoneQuery->where('network_users.location', 'like', '%' . $request->search . '%');
        }

        return Inertia::render('Analytics/FinancialDashboard', [
            'metrics' => [
                'financial_health' => $this->getFinancialHealth(),
                'mrr_trend' => $this->getMrrTrend(),
                'cash_flow_forecast' => $this->getCashFlowForecast(),
                'zone_revenue' => $zoneQuery->paginate(10)->withQueryString(),
                'top_zones' => (clone $zoneQuery)->limit(5)->get(),
                'payment_methods' => $this->getPaymentMethodsBreakdown(),
                'fiscal_intelligence' => [
                    'profit_margin' => $this->getProfitMarginData(),
                    'churn_rate' => $this->getChurnMetrics(),
                    'clv' => $this->getEstimatedCLV(),
                ],
            ],
            'currency' => $currency,
            'filters' => $request->only(['search']),
        ]);
    }

    protected function getFinancialHealth()
    {
        $totalUsers = NetworkUser::count();
        $activePaidUsers = NetworkUser::where('status', 'active')->count();
        
        // Sum of price for all active packages
        $totalPotentialRevenue = NetworkUser::where('status', 'active')
            ->with(['package', 'hotspotPackage'])
            ->get()
            ->sum(function ($u) {
                return $u->package?->price ?? $u->hotspotPackage?->price ?? 0;
            });

        return [
            'arpu' => $totalUsers > 0 ? round($totalPotentialRevenue / $totalUsers, 2) : 0,
            'mrr' => $totalPotentialRevenue,
            'active_yield' => $activePaidUsers > 0 ? round($totalPotentialRevenue / $activePaidUsers, 2) : 0,
        ];
    }

    protected function getMrrTrend()
    {
        // Monthly Recurring Revenue trend over last 6 months
        return TenantPayment::selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total")
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    protected function getCashFlowForecast()
    {
        // 90-day projection based on average daily revenue and pending renewals
        $last30DaysRevenue = TenantPayment::where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');
        
        $avgDailyRevenue = $last30DaysRevenue / 30;

        $forecast = [];
        for ($i = 1; $i <= 3; $i++) {
            $month = now()->addMonths($i);
            $forecast[] = [
                'month' => $month->format('M Y'),
                'projected' => round($avgDailyRevenue * 30, 2),
            ];
        }

        return $forecast;
    }

    protected function getZoneRevenueHeatmap()
    {
        return NetworkUser::select('network_users.location', DB::raw('SUM(COALESCE(packages.price, tenant_hotspot_packages.price, 0)) as revenue'))
            ->leftJoin('packages', 'network_users.package_id', '=', 'packages.id')
            ->leftJoin('tenant_hotspot_packages', 'network_users.hotspot_package_id', '=', 'tenant_hotspot_packages.id')
            ->whereNotNull('network_users.location')
            ->where('network_users.location', '!=', '')
            ->groupBy('network_users.location')
            ->orderByDesc('revenue')
            ->get();
    }

    protected function getPaymentMethodsBreakdown()
    {
        return TenantPayment::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->where('status', 'paid')
            ->groupBy('payment_method')
            ->get();
    }

    protected function getProfitMarginData()
    {
        // Net Profit = (Total Revenue - Operational Costs)
        $totalRevenue = TenantPayment::where('status', 'paid')->sum('amount');
        
        $totalCosts = TenantReportDataPoint::where('category', 'like', '%cost%')
            ->orWhere('category', 'like', '%expense%')
            ->sum('value');

        $netProfit = $totalRevenue - $totalCosts;

        return [
            'revenue' => $totalRevenue,
            'costs' => $totalCosts,
            'net_profit' => $netProfit,
            'margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0,
        ];
    }

    protected function getChurnMetrics()
    {
        // Churn Rate = (Expired Users not renewed in 30 days) / (Total Active Users 30 days ago)
        $totalActiveNow = NetworkUser::where('status', 'active')->count();
        $expiredRecently = NetworkUser::where('status', 'expired')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();
            
        return [
            'rate' => $totalActiveNow > 0 ? round(($expiredRecently / ($totalActiveNow + $expiredRecently)) * 100, 1) : 0,
            'count' => $expiredRecently,
        ];
    }

    protected function getEstimatedCLV()
    {
        // CLV = ARPU * Customer Lifespan
        // Lifespan approximation = 1 / Churn Rate
        $health = $this->getFinancialHealth();
        $churn = $this->getChurnMetrics();
        
        $churnRateDecimal = $churn['rate'] / 100;
        $lifespanMonths = $churnRateDecimal > 0 ? (1 / $churnRateDecimal) : 12; // Default to 12 months if 0 churn
        
        return round($health['arpu'] * $lifespanMonths, 2);
    }
}
