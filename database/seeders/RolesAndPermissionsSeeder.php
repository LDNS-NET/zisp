<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles (Tenant Specific)
        $roles = [
            'tenant_admin',
            'admin',
            'customer_care',
            'technical',
            'network_engineer',
            'marketing',
            'network_admin',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Define permissions (Example set, expand as needed)
        $permissions = [
            'manage network',
            'manage billing',
            'manage support',
            'manage marketing',
            'manage system users',
            'view analytics',
            'configure network',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $tenantAdminRole = Role::findByName('tenant_admin', 'web');
        $tenantAdminRole->givePermissionTo(Permission::all());

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo([
            'manage network',
            'manage support',
            'manage marketing',
            'view analytics',
            'configure network',
        ]);

        Role::findByName('customer_care', 'web')->givePermissionTo(['manage support', 'view analytics']);
        Role::findByName('technical', 'web')->givePermissionTo(['manage network', 'manage support']);
        Role::findByName('network_engineer', 'web')->givePermissionTo(['manage network', 'configure network']);
        Role::findByName('marketing', 'web')->givePermissionTo(['manage marketing', 'view analytics']);
        Role::findByName('network_admin', 'web')->givePermissionTo(['manage network', 'configure network', 'manage system users']);

        // Assign 'tenant_admin' to existing users who have a tenant_id but no roles
        $existingTenants = \App\Models\User::whereNotNull('tenant_id')->get();
        foreach ($existingTenants as $user) {
            if ($user->roles->isEmpty()) {
                $user->assignRole('tenant_admin');
            }
        }
    }
}
