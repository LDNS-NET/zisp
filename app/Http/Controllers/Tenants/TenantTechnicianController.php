<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantTechnician;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class TenantTechnicianController extends Controller
{
    public function index(Request $request)
    {
        $technicians = TenantTechnician::with(['user', 'installations' => function ($q) {
                $q->whereIn('status', ['scheduled', 'in_progress']);
            }])
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('employee_id', 'like', "%{$request->search}%");
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest()
            ->paginate($request->get('per_page', 10));

        $stats = [
            'total' => TenantTechnician::count(),
            'active' => TenantTechnician::where('status', 'active')->count(),
            'inactive' => TenantTechnician::where('status', 'inactive')->count(),
            'on_leave' => TenantTechnician::where('status', 'on_leave')->count(),
        ];

        return Inertia::render('Tenants/Technicians/Index', [
            'technicians' => $technicians,
            'stats' => $stats,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'employee_id' => 'required|string|max:50|unique:tenant_technicians,employee_id',
            'status' => 'required|in:active,inactive,on_leave',
            'specialization' => 'nullable|string',
            'skills' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Create user account for technician
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? $validated['employee_id'] . '@technician.local',
            'password' => Hash::make('password123'), // Default password
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        $user->assignRole('technician');

        $validated['user_id'] = $user->id;
        $technician = TenantTechnician::create($validated);

        return redirect()->back()->with('success', 'Technician added successfully.');
    }

    public function update(Request $request, TenantTechnician $technician)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $technician->user_id,
            'phone' => 'required|string|max:20',
            'employee_id' => 'required|string|max:50|unique:tenant_technicians,employee_id,' . $technician->id,
            'status' => 'required|in:active,inactive,on_leave',
            'specialization' => 'nullable|string',
            'skills' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Update user account
        if ($technician->user) {
            $technician->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? $technician->user->email,
            ]);
        }

        $technician->update($validated);

        return redirect()->back()->with('success', 'Technician updated successfully.');
    }

    public function destroy(TenantTechnician $technician)
    {
        // Soft delete
        $technician->delete();

        return back()->with('success', 'Technician deleted successfully.');
    }

    public function updateLocation(Request $request, TenantTechnician $technician)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
            'activity_type' => 'nullable|string',
            'installation_id' => 'nullable|exists:tenant_installations,id',
        ]);

        $technician->updateLocation(
            $validated['latitude'],
            $validated['longitude'],
            $validated['accuracy'] ?? null,
            $validated['speed'] ?? null
        );

        // If installation_id is provided, update the location record
        if (isset($validated['installation_id'])) {
            $technician->locations()->latest()->first()->update([
                'installation_id' => $validated['installation_id'],
                'activity_type' => $validated['activity_type'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
        ]);
    }

    public function getAvailable(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        
        $technicians = TenantTechnician::active()
            ->with(['installations' => function ($q) use ($date) {
                $q->where('scheduled_date', $date)
                  ->whereIn('status', ['scheduled', 'in_progress']);
            }])
            ->get()
            ->map(function ($tech) {
                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'phone' => $tech->phone,
                    'specialization' => $tech->specialization,
                    'skills' => $tech->skills,
                    'current_jobs' => $tech->installations->count(),
                    'average_rating' => $tech->average_rating,
                    'last_location' => [
                        'latitude' => $tech->latitude,
                        'longitude' => $tech->longitude,
                        'updated_at' => $tech->last_location_update,
                    ],
                ];
            });

        return response()->json($technicians);
    }

    public function trackingData(Request $request)
    {
        $technicians = TenantTechnician::active()
            ->with(['currentLocation', 'installations' => function ($q) {
                $q->today()->whereIn('status', ['scheduled', 'in_progress']);
            }])
            ->get()
            ->map(function ($tech) {
                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'phone' => $tech->phone,
                    'status' => $tech->status,
                    'current_location' => $tech->currentLocation ? [
                        'latitude' => $tech->currentLocation->latitude,
                        'longitude' => $tech->currentLocation->longitude,
                        'accuracy' => $tech->currentLocation->accuracy,
                        'speed' => $tech->currentLocation->speed,
                        'recorded_at' => $tech->currentLocation->recorded_at,
                    ] : null,
                    'active_installations' => $tech->installations->map(function ($inst) {
                        return [
                            'id' => $inst->id,
                            'installation_number' => $inst->installation_number,
                            'customer_name' => $inst->customer_name,
                            'address' => $inst->installation_address,
                            'latitude' => $inst->latitude,
                            'longitude' => $inst->longitude,
                            'status' => $inst->status,
                        ];
                    }),
                ];
            });

        return response()->json($technicians);
    }
}
