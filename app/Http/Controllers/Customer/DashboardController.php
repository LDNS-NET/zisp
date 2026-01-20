<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::guard('customer')->user();
        $user->load(['package', 'hotspotPackage', 'tenant']);
        
        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->pluck('provider')
            ->toArray();

        // Default M-Pesa for Kenya if not explicitly configured (System Fallback)
        if (($user->tenant->country_code ?? 'KE') === 'KE' && !in_array('mpesa', $gateways)) {
            $gateways[] = 'mpesa';
        }

        if ($user->type === 'hotspot') {
            $package = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $package = \App\Models\Package::withoutGlobalScopes()->find($user->package_id);
        }
        
        $daysRemaining = null;
        if ($user->expires_at) {
            $daysRemaining = now()->diffInDays($user->expires_at, false);
        }

        // Data Usage Aggregation (Current Month)
        $usage = \Illuminate\Support\Facades\DB::connection('radius')
            ->table('radacct')
            ->selectRaw('SUM(acctinputoctets) as upload, SUM(acctoutputoctets) as download')
            ->where('username', $user->username)
            ->where('acctstarttime', '>=', now()->startOfMonth())
            ->first();

        $totalDownloadGB = round(($usage->download ?? 0) / (1024 * 1024 * 1024), 2);
        $totalUploadGB = round(($usage->upload ?? 0) / (1024 * 1024 * 1024), 2);

        return Inertia::render('Customer/Dashboard', [
            'user' => $user,
            'package' => $package,
            'daysRemaining' => $daysRemaining,
            'usage' => [
                'download_gb' => $totalDownloadGB,
                'upload_gb' => $totalUploadGB,
                'total_gb' => $totalDownloadGB + $totalUploadGB,
            ],
            'paymentMethods' => array_values(array_unique($gateways)),
            'country' => $user->tenant->country_code ?? 'KE',
            'currency' => $user->tenant->currency ?? 'KES',
        ]);
    }
}
