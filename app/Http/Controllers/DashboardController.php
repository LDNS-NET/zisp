<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantLeads;
use App\Models\Tenants\TenantTickets;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantSMS;
use App\Models\Tenants\TenantEquipment;
use App\Models\Package;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $userId = Auth::id();
        $user = Auth::user();
        $tenant = $user?->tenant;

        return Inertia::render('Dashboard', [
            'subscription_expires_at' => $user?->subscription_expires_at,
            'stats' => [
                // Tenant Wallet
                'account_balance' => $tenant ? $tenant->wallet_balance : 0,
                'wallet_id' => $tenant ? $tenant->wallet_id : null,

                // Charts
                'sms_chart' => $this->monthlyCount(TenantSMS::class, 'created_at'),
                'payments_chart' => $this->monthlySum(TenantPayment::class, 'paid_at', 'amount'),
                'user_types_chart' => $this->getUserTypesByMonth(),

                // User Distribution
                'user_distribution' => NetworkUser::select('type', DB::raw('COUNT(*) as total'))
                    ->groupBy('type')
                    ->pluck('total', 'type')
                    ->toArray(),

                // Users Summary
                'users' => [
                    'name'=> NetworkUser::where('username', $userId)->paginate(5),
                    'total' => NetworkUser::count(),
                    'hotspot' => NetworkUser::where('type', 'hotspot')->count(),
                    'pppoe' => NetworkUser::where('type', 'pppoe')->count(),
                    'static' => NetworkUser::where('type', 'static')->count(),
                    'activeUsers' => NetworkUser::where('online', true)
                        ->where('created_by', $userId)
                        ->count(),
                    'expired' => NetworkUser::whereDate('expires_at', '<', now())->count(),
                ],

                // Leads
                'leads' => [
                    'total' => TenantLeads::count(),
                    'pending' => TenantLeads::where('status', 'new')->count(),
                    'converted' => TenantLeads::where('status', 'converted')->count(),
                    'lost' => TenantLeads::where('status', 'lost')->count(),
                ],

                // Tickets
                'tickets' => [
                    'open' => TenantTickets::where('status', 'open')->count(),
                    'closed' => TenantTickets::where('status', 'closed')->count(),
                    'assigned_to_me' => TenantTickets::where('status', 'open')
                        ->where('created_by', $userId)
                        ->count(),
                ],

                // Mikrotik Devices
                'mikrotiks' => [
                    'total' => TenantMikrotik::count(),
                    'connected' => TenantMikrotik::where('status', 'online')->count(),
                    'disconnected' => TenantMikrotik::where('status', 'offline')->count(),
                ],

                // SMS
                'sms' => [
                    'total_sent' => TenantSMS::count(),
                    'sent_this_month' => TenantSMS::whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->count(),
                ],

                // Packages
                'packages' => [
                    'total' => Package::count(),
                    'active' => Package::where('created_by', $userId)->count(),
                ],

                // Equipment
                'equipment' => [
                    'total' => TenantEquipment::count(),
                    'total_value' => TenantEquipment::sum('price'),
                ],

                // Recent Activity
                'recent_activity' => [
                    'latest_users' => NetworkUser::latest()
                        ->take(5)
                        ->get(['username', 'type', 'created_at']),
                    'latest_leads' => TenantLeads::latest()
                        ->take(5)
                        ->get(['name', 'status', 'created_at']),
                ],

                // Trial Info (used for access suspension logic)
                'trial_info' => $tenant ? [
                    'trial_ends_at' => $tenant->created_at->addDays(18)->toFormattedDateString(),
                    'is_suspended' => $this->isTenantSuspended($tenant),
                ] : [
                    'trial_ends_at' => 'N/A',
                    'is_suspended' => true,
                ],
            ],
            'currency' => $tenant?->currency ?? 'KES',
        ]);
    }

    public function data()
    {
        $user = Auth::user();

        return response()->json([
            'subscription_expires_at' => $user?->subscription_expires_at,
        ]);
    }

    protected function isTenantSuspended($tenant = null): bool
    {
        if (!$tenant) {
            $tenant = Auth::user()?->tenant;
        }
        if (!$tenant) {
            return true;
        }

        $trialEnds = $tenant->created_at->addDays(18);
        $hasRecentPayment = TenantPayment::whereDate('paid_at', '>=', now()->subDays(30))->exists();

        return now()->greaterThan($trialEnds) && !$hasRecentPayment;
    }

    protected function monthlyCount($model, $dateColumn)
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'sqlite') {
            $data = $model::selectRaw("strftime('%m', $dateColumn) as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        } else {
            $data = $model::selectRaw("MONTH($dateColumn) as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        return $this->fillMissingMonths($data);
    }

    protected function monthlySum($model, $dateColumn, $sumColumn)
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'sqlite') {
            $data = $model::selectRaw("strftime('%m', $dateColumn) as month, SUM($sumColumn) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        } else {
            $data = $model::selectRaw("MONTH($dateColumn) as month, SUM($sumColumn) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        return $this->fillMissingMonths($data);
    }


    protected function fillMissingMonths(array $data)
    {
        $filled = [];
        for ($i = 1; $i <= 12; $i++) {
            $filled[] = $data[$i] ?? 0;
        }
        return $filled;
    }

    protected function getUserTypesByMonth()
    {
        $currentYear = now()->year;
        $connection = DB::connection()->getDriverName();

        // Get monthly user counts by type for current year
        if ($connection === 'sqlite') {
            $hotspotUsers = NetworkUser::where('type', 'hotspot')
                ->selectRaw("strftime('%m', created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $pppoeUsers = NetworkUser::where('type', 'pppoe')
                ->selectRaw("strftime('%m', created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $staticUsers = NetworkUser::where('type', 'static')
                ->selectRaw("strftime('%m', created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        } else {
            $hotspotUsers = NetworkUser::where('type', 'hotspot')
                ->selectRaw("MONTH(created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $pppoeUsers = NetworkUser::where('type', 'pppoe')
                ->selectRaw("MONTH(created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $staticUsers = NetworkUser::where('type', 'static')
                ->selectRaw("MONTH(created_at) as month, COUNT(*) as total")
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();
        }

        return [
            'hotspot' => $this->fillMissingMonths($hotspotUsers),
            'pppoe' => $this->fillMissingMonths($pppoeUsers),
            'static' => $this->fillMissingMonths($staticUsers),
        ];
    }
}
