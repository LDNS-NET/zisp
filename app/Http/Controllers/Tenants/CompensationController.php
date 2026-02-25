<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\Compensation;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\TenantSMSTemplate;
use App\Models\Tenants\TenantSMS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CompensationController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $location = $request->get('location');
        $router_id = $request->get('router_id');

        $usersQuery = NetworkUser::query()
            ->with(['package', 'hotspotPackage'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                });
            })
            ->when($location, function ($q) use ($location) {
                return $q->where('location', $location);
            });

        // Filtered count (total matching users regardless of pagination)
        $filteredCount = $usersQuery->count();
        
        // If neither search nor location is provided, return empty paginated data for the users
        if (empty($search) && empty($location)) {
            $users = NetworkUser::whereRaw('1=0')->paginate(10);
            $filteredCount = 0;
        } else {
            $users = $usersQuery->latest()->paginate(10);
        }

        // Get unique locations for filter
        $locations = NetworkUser::whereNotNull('location')->distinct()->pluck('location');
        $routers = TenantMikrotik::all();

        // Get recent compensations
        $recentCompensations = Compensation::with(['user', 'creator'])
            ->latest()
            ->paginate(10, ['*'], 'compensations_page');

        // Summary Statistics
        $stats = [
            'total_users' => NetworkUser::count(),
            'suspended_users' => NetworkUser::where('status', 'suspended')->count(),
            'total_compensations' => Compensation::count(),
            'today_compensations' => Compensation::whereDate('created_at', now()->toDateString())->count(),
            'filtered_count' => $filteredCount,
            'is_filtered' => !empty($search) || !empty($location) || !empty($router_id),
        ];

        // Default SMS Template for compensation
        $defaultTemplate = TenantSMSTemplate::firstOrCreate(
            ['name' => 'Compensation'],
            [
                'content' => 'Hello {{name}}, your account has been compensated with {{duration}} {{unit}}. Your new expiry date is {{new_expiry}}. Thank you!',
                'created_by' => Auth::id() ?? 1,
            ]
        );

        return inertia('Compensations/Index', [
            'users' => $users->through(fn($user) => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'account_number' => $user->account_number,
                'phone' => $user->phone,
                'location' => $user->location,
                'expires_at' => $user->expires_at,
                'status' => $user->status,
                'package_name' => $user->package?->name ?? $user->hotspotPackage?->name ?? 'N/A',
            ]),
            'compensations' => $recentCompensations,
            'locations' => $locations,
            'routers' => $routers,
            'stats' => $stats,
            'default_template' => $defaultTemplate,
            'filters' => [
                'search' => $search,
                'location' => $location,
                'router_id' => $router_id,
            ]
        ]);
    }

    public function store(Request $request, \App\Services\SmsGatewayService $smsService)
    {
        $validated = $request->validate([
            'user_ids' => 'required_without:apply_to_all|array',
            'user_ids.*' => 'exists:network_users,id',
            'apply_to_all' => 'nullable|boolean',
            'search' => 'nullable|string',
            'location' => 'nullable|string',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:minutes,hours,days,weeks,months',
            'reason' => 'nullable|string|max:255',
            'notify_users' => 'nullable|boolean',
            'sms_template' => 'required_if:notify_users,true|nullable|string',
        ]);

        if ($request->get('apply_to_all')) {
            $search = $request->get('search');
            $location = $request->get('location');

            // Safety check: Don't allow bulk apply to all if no filters are active
            if (empty($search) && empty($location)) {
                return back()->with('error', 'Please apply a search or location filter before using bulk compensation.');
            }

            $usersQuery = NetworkUser::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($subQ) use ($search) {
                        $subQ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('account_number', 'like', "%{$search}%");
                    });
                })
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                });
            
            $users = $usersQuery->get();
        } else {
            $users = NetworkUser::whereIn('id', $validated['user_ids'])->get();
        }

        $processedCount = 0;
        $tenantId = Auth::user()->tenant_id;

        DB::transaction(function () use ($users, $validated, &$processedCount, $smsService, $tenantId) {
            foreach ($users as $user) {
                $oldExpiry = $user->expires_at;
                $baseDate = ($oldExpiry && $oldExpiry->isFuture()) ? $oldExpiry : now();
                
                $newExpiry = clone $baseDate;
                $value = $validated['duration_value'];

                switch ($validated['duration_unit']) {
                    case 'minutes': $newExpiry->addMinutes($value); break;
                    case 'hours': $newExpiry->addHours($value); break;
                    case 'days': $newExpiry->addDays($value); break;
                    case 'weeks': $newExpiry->addWeeks($value); break;
                    case 'months': $newExpiry->addMonths($value); break;
                }

                // Record compensation
                Compensation::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'duration_value' => $value,
                    'duration_unit' => $validated['duration_unit'],
                    'old_expires_at' => $oldExpiry,
                    'new_expires_at' => $newExpiry,
                    'created_by' => Auth::id(),
                    'reason' => $validated['reason'],
                ]);

                // Record in package_renewals for unified history
                \App\Models\Tenants\PackageRenewal::create([
                    'user_id' => $user->id,
                    'package_id' => $user->package_id,
                    'amount_paid' => 0,
                    'started_at' => now(),
                    'expires_at' => $newExpiry,
                    'status' => 'active',
                    'type' => 'compensation',
                    'tenant_id' => $user->tenant_id,
                ]);

                // Update user expiry and status if suspended
                $updateData = ['expires_at' => $newExpiry];
                if ($user->status === 'suspended') {
                    $updateData['status'] = 'active';
                }
                $user->update($updateData);

                // Send SMS if requested
                if (!empty($validated['notify_users']) && !empty($user->phone)) {
                    $templateContent = $validated['sms_template'] ?: TenantSMSTemplate::where('name', 'Compensation')->value('content');
                    
                    if ($templateContent) {
                        $message = str_replace(
                            ['{{name}}', '{{duration}}', '{{unit}}', '{{new_expiry}}'],
                            [
                                $user->full_name ?? $user->username,
                                $value,
                                $validated['duration_unit'],
                                $newExpiry->format('Y-m-d H:i')
                            ],
                            $templateContent
                        );
                        
                        try {
                            $smsService->sendSMS($tenantId, $user->phone, $message);
                        } catch (\Exception $e) {
                            Log::error("Failed to send compensation SMS to {$user->phone}: " . $e->getMessage());
                        }
                    }
                }

                $processedCount++;
            }
        });

        return back()->with('success', "Successfully compensated {$processedCount} users.");
    }
}
