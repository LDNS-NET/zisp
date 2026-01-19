<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantActiveUsers;
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

                // System Health (Tenant Specific via Global Scope)
                'system_health' => [
                    'cpu_avg' => round(TenantMikrotik::where('status', 'online')->avg('cpu_usage') ?? 0, 1),
                    'memory_avg' => round(TenantMikrotik::where('status', 'online')->avg('memory_usage') ?? 0, 1),
                    // 'temp_avg' removed as requested
                    'routers_online' => TenantMikrotik::where('status', 'online')->count(),
                    'routers_total' => TenantMikrotik::count(),
                ],

                // Top Consumers (Tenant Specific via Global Scope)
                'top_consumers' => TenantActiveUsers::with('user:id,username,type,phone') // Eager load phone
                    ->orderByRaw('(bytes_in + bytes_out) DESC')
                    ->take(5)
                    ->get()
                    ->map(function ($session) {
                        return [
                            'username' => $session->username,
                            'usage' => $session->bytes_in + $session->bytes_out,
                            'usage_formatted' => $this->formatBytes($session->bytes_in + $session->bytes_out),
                            'ip' => $session->ip_address,
                            'phone' => $session->user ? $session->user->phone : 'N/A', // Use phone from relationship
                        ];
                    }),

                // Smart AI Insights
                'smart_insights' => [
                    'router_risks' => TenantMikrotik::where(function ($query) {
                            $query->where('status', 'offline')
                                  ->orWhere('cpu_usage', '>', 80)
                                  ->orWhere('memory_usage', '>', 85);
                        })
                        ->get()
                        ->map(function ($router) {
                            $issues = [];
                            if ($router->status === 'offline') $issues[] = 'Router Offline';
                            if ($router->cpu_usage > 80) $issues[] = 'High CPU Load (' . $router->cpu_usage . '%)';
                            if ($router->memory_usage > 85) $issues[] = 'Memory Critical (' . $router->memory_usage . '%)';
                            
                            return [
                                'name' => $router->name,
                                'ip' => $router->ip_address ?? $router->public_ip, 
                                'issues' => $issues,
                                'severity' => $router->status === 'offline' ? 'critical' : 'warning',
                            ];
                        }),
                    
                    'user_risks' => $this->getUserRisks(),
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

    protected function getUserRisks()
    {
        // 1. Identify Frequent Disconnections (only if Radacct is used)
        $tenantUsernames = NetworkUser::where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('username')
            ->toArray();
        
        $riskyUsers = [];
        
        try {
            if (empty($tenantUsernames)) {
                return [];
            }

            $frequentDisconnects = \App\Models\Radius\Radacct::whereIn('username', $tenantUsernames)
                ->where('acctstarttime', '>=', now()->subDay())
                ->select('username', DB::raw('count(*) as session_count'))
                ->groupBy('username')
                ->having('session_count', '>', 5) // Lowered threshold to 5 for testing/sensitivity
                ->orderByDesc('session_count')
                ->limit(5)
                ->get();
                
            foreach ($frequentDisconnects as $record) {
                $user = NetworkUser::where('username', $record->username)->first();
                
                if (!$user) continue;

                // Auto-Open Ticket Logic
                $ticketStatus = 'Existing Ticket';
                $existingTicket = TenantTickets::where('client_type', NetworkUser::class)
                    ->where('client_id', $user->id)
                    ->where('status', 'open')
                    ->where('description', 'LIKE', '%Auto-detected frequent disconnections%') // Prevent dups for same issue
                    ->first();

                if (!$existingTicket) {
                    try {
                        TenantTickets::create([
                            'client_type' => NetworkUser::class,
                            'client_id' => $user->id,
                            'priority' => 'high',
                            'status' => 'open',
                            'description' => "Auto-detected frequent disconnections: User has had {$record->session_count} sessions in the last 24 hours. Please check signal levels and router logs.",
                            // ticket_number, created_by, tenant_id handled by model events
                        ]);
                        $ticketStatus = 'Ticket Auto-Opened';
                    } catch (\Exception $e) {
                        $ticketStatus = 'Ticket Creation Failed';
                    }
                }

                $riskyUsers[] = [
                    'username' => $record->username,
                    'phone' => $user->phone ?? 'N/A',
                    'location' => $user->location ?? 'Unknown',
                    'issue' => 'Frequent Connection Drops',
                    'detail' => $record->session_count . ' drops in 24h',
                    'severity' => 'warning',
                    'ticket_status' => $ticketStatus
                ];
            }
        } catch (\Exception $e) {
            // Radacct table might not exist or connection failed, skip
        }
        
        return $riskyUsers;
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
