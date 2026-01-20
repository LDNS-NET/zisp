<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantActiveUsers;
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
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        $currency = Auth::user()->tenant?->currency ?? 'KES';

        return Inertia::render('Analytics/FinancialDashboard', [
            'metrics' => [
                'financial_health' => $this->getFinancialHealth(),
                'mrr_trend' => $this->getMrrTrend(),
                'cash_flow_forecast' => $this->getCashFlowForecast(),
                'zone_revenue' => $this->getZoneRevenueHeatmap(),
                'payment_methods' => $this->getPaymentMethodsBreakdown(),
            ],
            'currency' => $currency,
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
        return NetworkUser::select('location', DB::raw('SUM(COALESCE(packages.price, tenant_hotspot_packages.price, 0)) as revenue'))
            ->leftJoin('packages', 'network_users.package_id', '=', 'packages.id')
            ->leftJoin('tenant_hotspot_packages', 'network_users.hotspot_package_id', '=', 'tenant_hotspot_packages.id')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->groupBy('location')
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
}
