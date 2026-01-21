<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Models\TenantActivity;
use App\Models\UserDevice;
use Spatie\Permission\Models\Permission;

class TenantSystemUserController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        
        if (!$tenantId) {
            abort(403, 'Unauthorized. Tenant ID missing.');
        }

        $users = User::where('tenant_id', $tenantId)
            ->with('roles')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'role' => $user->roles->first()?->name,
                    'last_login_at' => $user->last_login_at,
                    'is_suspended' => $user->is_suspended,
                    'working_hours' => $user->working_hours,
                    'allowed_ips' => $user->allowed_ips,
                    'security_config' => $user->security_config,
                    'is_device_lock_enabled' => $user->is_device_lock_enabled,
                    'permissions' => $user->permissions->pluck('name'),
                ];
            });
        
        $roles = Role::whereIn('name', [
            'admin', 'customer_care', 'technical', 
            'network_engineer', 'marketing', 'network_admin'
        ])->get();

        $permissions = Permission::all();
        $activities = TenantActivity::where('tenant_id', $tenantId)->latest()->take(100)->get();

        return inertia('Settings/Staff/Index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'activities' => $activities,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'tenant_id' => Auth::user()->tenant_id,
            'email_verified_at' => now(), // Auto-verify staff for now or handle later
        ]);

        $user->assignRole($request->role);

        return back()->with('success', 'Staff member created successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($id)],
            'role' => 'required|string|exists:roles,name',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'phone' => $request->phone,
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Sync roles (assuming one role per staff for simplicity)
        $user->syncRoles([$request->role]);

        return back()->with('success', 'Staff member updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
        
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return back()->with('success', 'Staff member deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
        
        if ($user->id === Auth::id()) {
             return back()->with('error', 'You cannot suspend yourself.');
        }

        $user->update([
            'is_suspended' => !$user->is_suspended
        ]);

        $status = $user->is_suspended ? 'suspended' : 'activated';
        return back()->with('success', "Staff member $status successfully.");
    }

    public function updateSecurity(Request $request, $id)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'working_hours' => 'nullable|array',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'is_device_lock_enabled' => 'nullable|boolean',
            'max_devices' => 'nullable|integer|min:1',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->update([
            'working_hours' => $validated['working_hours'] ?? $user->working_hours,
            'allowed_ips' => $validated['allowed_ips'] ?? $user->allowed_ips,
            'is_device_lock_enabled' => $validated['is_device_lock_enabled'] ?? $user->is_device_lock_enabled,
            'security_config' => array_merge($user->security_config ?? [], [
                'max_devices' => $validated['max_devices'] ?? ($user->security_config['max_devices'] ?? 1)
            ]),
        ]);

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        TenantActivity::log('staff.security_updated', "Updated security settings for staff member: {$user->name}", $user);

        return back()->with('success', 'Security settings updated successfully.');
    }

    public function devices($id)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
        return response()->json($user->devices);
    }

    public function toggleDeviceLock($id, $deviceId)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
        $device = $user->devices()->where('id', $deviceId)->firstOrFail();

        $device->update(['is_locked' => !$device->is_locked]);

        $status = $device->is_locked ? 'locked' : 'unlocked';
        TenantActivity::log('staff.device_toggled', "Device $status for staff member: {$user->name}", $user, ['device' => $device->device_id]);

        return back()->with('success', "Device $status successfully.");
    }

    public function activity()
    {
        $tenantId = Auth::user()->tenant_id;
        $activities = TenantActivity::where('tenant_id', $tenantId)
            ->with('user')
            ->latest()
            ->paginate(50);

        return inertia('Settings/Staff/Activity', [
            'activities' => $activities,
        ]);
    }
}
