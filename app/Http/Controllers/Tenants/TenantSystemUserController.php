<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

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
                ];
            });
        
        $roles = Role::whereIn('name', [
            'tenant_admin', 'admin', 'customer_care', 'technical', 
            'network_engineer', 'marketing', 'network_admin'
        ])->get();

        return inertia('Settings/Staff/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:255|unique:users',
            'phone' => 'required|string|max:20',
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
            'phone' => 'required|string|max:20',
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
}
