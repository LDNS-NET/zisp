<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\Compensation;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // If router_id is provided, we need to join with TenantActiveUsers or similar 
        // OR better, users might have a router_id if we added it to NetworkUser.
        // Let's check how Routers are linked to users.
        // Usually, users authenticate against any router in their tenant, but maybe they are assigned.
        // I'll search for 'router_id' in NetworkUser fillable.
        
        $users = $usersQuery->latest()->paginate(10);

        // Get unique locations for filter
        $locations = NetworkUser::whereNotNull('location')->distinct()->pluck('location');
        $routers = TenantMikrotik::all();

        // Get recent compensations
        $recentCompensations = Compensation::with(['user', 'creator'])
            ->latest()
            ->paginate(10, ['*'], 'compensations_page');

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
            'filters' => [
                'search' => $search,
                'location' => $location,
                'router_id' => $router_id,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|exists:network_users,id',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:minutes,hours,days,weeks,months',
            'reason' => 'nullable|string|max:255',
        ]);

        $users = NetworkUser::whereIn('id', $validated['user_ids'])->get();
        $processedCount = 0;

        DB::transaction(function () use ($users, $validated, &$processedCount) {
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

                // Update user expiry and status if suspended
                $updateData = ['expires_at' => $newExpiry];
                if ($user->status === 'suspended') {
                    $updateData['status'] = 'active';
                }
                $user->update($updateData);

                $processedCount++;
            }
        });

        return back()->with('success', "Successfully compensated {$processedCount} users.");
    }
}
