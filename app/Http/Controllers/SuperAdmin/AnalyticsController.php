<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        // 1. Monthly Recurring Revenue (MRR) - Last 6 months
        // Assuming TenantPayment is where subscription payments are recorded.
        // Note: TenantPayment is tenant-specific, so we need to be careful.
        // If TenantPayment is a central table (which it seems to be based on previous context), we can query it directly.
        // However, if it's tenant-specific (separate databases/tables), this would be harder.
        // Given the context of "SuperAdmin" and "TenantPayment" model existing in `App\Models\Tenants`, 
        // and previous usage `TenantPayment::withoutGlobalScopes()`, it suggests a single database multi-tenancy or shared table.
        
        $mrrData = TenantPayment::withoutGlobalScopes()
            ->select(
                DB::raw('DATE_FORMAT(paid_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 2. Tenant Growth - Last 6 months
        $tenantGrowth = Tenant::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 3. Active vs Inactive Tenants
        $tenantStatus = User::where('role', '!=', 'superadmin')
            ->select(
                DB::raw('CASE WHEN is_suspended = 1 THEN "suspended" ELSE "active" END as status'),
                DB::raw('count(*) as total')
            )
            ->groupBy('status')
            ->get();

        return Inertia::render('SuperAdmin/Analytics/Index', [
            'mrrData' => $mrrData,
            'tenantGrowth' => $tenantGrowth,
            'tenantStatus' => $tenantStatus,
        ]);
    }
}
