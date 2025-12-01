<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantLeads;
use App\Models\Tenants\TenantTickets;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantSms;
use App\Models\Tenants\TenantEquipment;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $userId = Auth::id();

        $tenant = tenant();
        $subscription = \App\Models\TenantSubscription::where('tenant_id', $tenant->id)->first();

        // Create subscription if it doesn't exist (for existing tenants)
        if (!$subscription && $tenant) {
            $subscription = \App\Models\TenantSubscription::createForTenant($tenant);
            Log::info('Created subscription for existing tenant', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id
            ]);
        }

        // Debug subscription data
        if ($subscription) {
            Log::info('Subscription data for dashboard', [
                'tenant_id' => $tenant->id,
                'subscription_status' => $subscription->status,
                'is_on_trial' => $subscription->isOnTrial(),
                'trial_days_remaining' => $subscription->getTrialDaysRemaining(),
                'trial_ends_at' => $subscription->trial_ends_at,
            ]);
        }

        $trialDuration = $subscription ? $subscription->getTrialDurationRemaining() : ['days' => 0, 'hours' => 0];
        return inertia('Dashboard', [
            'stats' => [
                'account_balance' => $tenant ? $tenant->wallet_balance : 0,
                'wallet_id' => $tenant ? $tenant->wallet_id : null,
                // Subscription info (preserving your logic)
                'subscription' => $subscription ? [
                    'status' => $subscription->status,
                    'is_active' => $subscription->isActive(),
                    'is_on_trial' => $subscription->isOnTrial(),
                    'is_expired' => $subscription->isExpired(),
                    'trial_days_remaining' => $trialDuration['days'],
                    'trial_hours_remaining' => $trialDuration['hours'],
                    'current_period_days_remaining' => $subscription->getCurrentPeriodDaysRemaining(),
                    'next_billing_date' => $subscription->next_billing_date,
                    'amount' => $subscription->amount,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'current_period_ends_at' => $subscription->current_period_ends_at,
                ] : null,
                // Charts
                'sms_chart' => $this->monthlyCount(TenantSms::class, 'created_at'),
                'payments_chart' => [
                    'daily' => $this->getDailyRevenue(),
                    'weekly' => $this->getWeeklyRevenue(),
                    'monthly' => $this->getMonthlyRevenue(),
                    'yearly' => $this->getYearlyRevenue(),
                ],
                'user_distribution' => NetworkUser::leftJoin('packages', 'network_users.package_id', '=', 'packages.id')
                    ->select(DB::raw('COALESCE(packages.name, "No Package") as package_name'), DB::raw('COUNT(*) as total'))
                    ->groupBy('package_name')
                    ->pluck('total', 'package_name')
                    ->toArray(),
                'user_growth_chart' => $this->getUserGrowthData(),
                // Users Summary
                'users' => [
                    'total' => NetworkUser::count(),
                    'hotspot' => NetworkUser::where('type', 'hotspot')->count(),
                    'pppoe' => NetworkUser::where('type', 'pppoe')->count(),
                    'static' => NetworkUser::where('type', 'static')->count(),
                    'activeUsers' => NetworkUser::where('online', true)->where('created_by', $userId)->count(),
                    'expired' => NetworkUser::whereDate('expires_at', '<', now())->count(),
                ],
                // Leads
                'leads' => [
                    'total' => TenantLeads::count(),
                    'pending' => TenantLeads::where('status', 'pending')->count(),
                    'converted' => TenantLeads::where('status', 'converted')->count(),
                    'lost' => TenantLeads::where('status', 'lost')->count(),
                ],
                // Tickets
                'tickets' => [
                    'open' => TenantTickets::where('status', 'open')->count(),
                    'closed' => TenantTickets::where('status', 'closed')->count(),
                    'assigned_to_me' => TenantTickets::where('status', 'open')->where('created_by', $userId)->count(),
                ],
                // Mikrotik Devices
                'mikrotiks' => [
                    'total' => TenantMikrotik::count(),
                    'connected' => TenantMikrotik::where('status', 'connected')->count(),
                    'disconnected' => TenantMikrotik::where('status', 'disconnected')->count(),
                ],
                // SMS
                'sms' => [
                    'total_sent' => TenantSms::count(),
                    'sent_this_month' => TenantSms::whereMonth('created_at', $now->month)
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
                    'latest_users' => NetworkUser::latest()->take(5)->get(['username', 'type', 'created_at']),
                    'latest_leads' => TenantLeads::latest()->take(5)->get(['name', 'status', 'created_at']),
                ],
                // Trial info (renamed from duplicate subscription)
                'trial_info' => tenant() ? [
                    'trial_ends_at' => tenant()->created_at->addDays(18)->toFormattedDateString(),
                    'is_suspended' => $this->isTenantSuspended(),
                ] : [
                    'trial_ends_at' => 'N/A',
                    'is_suspended' => true,
                ],
            ]
        ]);
    }

    protected function isTenantSuspended(): bool
    {
        $tenant = tenant();
        if (!$tenant) {
            return true;
        }

        $trialEnds = $tenant->created_at->addDays(18);
        $hasRecentPayment = TenantPayment::whereDate('paid_at', '>=', now()->subDays(30))->exists();
        return now()->greaterThan($trialEnds) && !$hasRecentPayment;
    }

    protected function monthlyCount($model, $dateColumn)
    {
        $data = $model::selectRaw("MONTH($dateColumn) as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        return $this->fillMissingMonths($data);
    }

    protected function monthlySum($model, $dateColumn, $sumColumn)
    {
        $data = $model::selectRaw("MONTH($dateColumn) as month, SUM($sumColumn) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        return $this->fillMissingMonths($data);
    }

    protected function getDailyRevenue()
    {
        $data = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');
            $amount = TenantPayment::whereDate('paid_at', $date->toDateString())
                ->sum('amount');
            $data[] = (float) ($amount ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    protected function getWeeklyRevenue()
    {
        $data = [];
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $labels[] = $weekStart->format('M d') . '-' . $weekEnd->format('d');
            $amount = TenantPayment::whereBetween('paid_at', [$weekStart, $weekEnd])
                ->sum('amount');
            $data[] = (float) ($amount ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    protected function getMonthlyRevenue()
    {
        $data = [];
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $amount = TenantPayment::whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('amount');
            $data[] = (float) ($amount ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    protected function getYearlyRevenue()
    {
        $data = [];
        $labels = [];

        for ($i = 4; $i >= 0; $i--) {
            $year = now()->subYears($i)->year;
            $labels[] = (string) $year;
            $amount = TenantPayment::whereYear('paid_at', $year)
                ->sum('amount');
            $data[] = (float) ($amount ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    protected function getUserGrowthData()
    {
        $currentYear = now()->year;
        $totalUsers = [];
        $activeUsers = [];
        $newUsers = [];

        for ($month = 1; $month <= 12; $month++) {
            $endOfMonth = Carbon::create($currentYear, $month, 1)->endOfMonth();

            // Total users created by end of this month
            $total = NetworkUser::where('created_at', '<=', $endOfMonth)->count();
            $totalUsers[] = (int) $total;

            // Active users (not expired) at end of this month
            $active = NetworkUser::where('created_at', '<=', $endOfMonth)
                ->where(function ($query) use ($endOfMonth) {
                    $query->where('expires_at', '>', $endOfMonth)
                        ->orWhereNull('expires_at');
                })
                ->count();
            $activeUsers[] = (int) $active;

            // New users created in this month
            $new = NetworkUser::whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $month)
                ->count();
            $newUsers[] = (int) $new;
        }

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'new_users' => $newUsers,
        ];
    }

    protected function fillMissingMonths(array $data)
    {
        $filled = [];
        for ($i = 1; $i <= 12; $i++) {
            $filled[] = $data[$i] ?? 0;
        }
        return $filled;
    }
}
