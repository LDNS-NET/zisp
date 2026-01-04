use App\Models\Tenants\TenantSMSTemplate;
use App\Models\Radius\Radacct;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        // 1. Tenant Metrics
        $totalTenants = User::count();
        $activeTenants = User::where('is_suspended', false)->count();
        $suspendedTenants = User::where('is_suspended', true)->count();
        
        $lastMonthTenants = User::where('created_at', '<', now()->subMonth())->count();
        $tenantGrowth = $lastMonthTenants > 0 ? (($totalTenants - $lastMonthTenants) / $lastMonthTenants) * 100 : 100;

        // 2. End User Metrics
        $totalEndUsers = NetworkUser::count();
        // Real-time online users from Radacct (Active sessions with recent updates)
        $onlineUsers = Radacct::whereNull('acctstoptime')
            ->where(function ($query) {
                $query->where('acctupdatetime', '>=', now()->subMinutes(10))
                      ->orWhere('acctstarttime', '>=', now()->subMinutes(10));
            })
            ->count();
        
        // 3. Financial Metrics
        $totalRevenue = TenantPayment::where('status', 'paid')->sum('amount');
        $thisMonthRevenue = TenantPayment::where('status', 'paid')->whereMonth('created_at', now()->month)->sum('amount');
        $lastMonthRevenue = TenantPayment::where('status', 'paid')->whereMonth('created_at', now()->subMonth()->month)->sum('amount');
        $revenueGrowth = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 100;

        // 4. Revenue Trend (Last 30 Days)
        $revenueTrend = TenantPayment::where('status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 5. Revenue by Gateway
        $revenueByGateway = TenantPayment::where('status', 'paid')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get();

        // 6. 6-Month MRR Data
        $mrrData = TenantPayment::where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(paid_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 7. 6-Month Tenant Growth
        $tenantGrowthData = Tenant::where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 8. Recent Activity (Enhanced)
        $recentTenants = User::latest()->take(5)->get();
        $recentPayments = TenantPayment::where('status', 'paid')->latest()->take(5)->with('tenant')->get();
        
        $recentActivity = collect()
            ->merge($recentTenants->map(fn($u) => [
                'type' => 'tenant',
                'icon' => 'UserPlus',
                'title' => 'New Tenant Registered',
                'description' => "{$u->name} ({$u->email}) joined the platform.",
                'time' => $u->created_at->diffForHumans(),
                'timestamp' => $u->created_at,
            ]))
            ->merge($recentPayments->map(fn($p) => [
                'type' => 'payment',
                'icon' => 'CreditCard',
                'title' => 'Payment Received',
                'description' => "Received {$p->currency} {$p->amount} from {$p->phone} via " . ucfirst($p->payment_method),
                'time' => $p->created_at->diffForHumans(),
                'timestamp' => $p->created_at,
            ]))
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        return Inertia::render('SuperAdmin/Dashboard/Index', [
            'metrics' => [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'suspended' => $suspendedTenants,
                    'growth' => round($tenantGrowth, 1),
                ],
                'users' => [
                    'total' => $totalEndUsers,
                    'online' => $onlineUsers,
                ],
                'finance' => [
                    'total_revenue' => $totalRevenue,
                    'this_month' => $thisMonthRevenue,
                    'growth' => round($revenueGrowth, 1),
                ],
                'sms' => [
                    'total' => TenantSMS::count(),
                    'this_month' => TenantSMS::whereMonth('created_at', now()->month)->count(),
                ]
            ],
            'charts' => [
                'revenue_trend' => $revenueTrend,
                'revenue_by_gateway' => $revenueByGateway,
                'mrr_data' => $mrrData,
                'tenant_growth' => $tenantGrowthData,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }
}