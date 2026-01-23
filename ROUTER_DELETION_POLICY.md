# MikroTik Router Deletion Policy

## Overview
This document outlines the access control policy for MikroTik router deletion operations to ensure system stability and proper documentation.

## Access Control

### Who Can Delete Routers
Only the following roles have permission to delete MikroTik routers:
- **Admin** (Super Admin)
- **Tenant Admin**
- **Network Engineer**

### Restricted Roles
The following roles **CANNOT** delete routers directly:
- **Technical Staff**
- **Technicians**
- **Network Admin** (can view/manage but not delete)
- **Other Staff Roles**

## Deletion Operations

### 1. Soft Delete (Move to Recycle Bin)
- **Route**: `DELETE /mikrotiks/{mikrotik}`
- **Method**: `TenantMikrotikController::destroy()`
- **Access**: Admin, Tenant Admin, Network Engineer only
- **Requires**: Current password confirmation
- **Effect**: Router is soft-deleted and moved to Recycle Bin
- **Reversible**: Yes, can be restored

### 2. Restore from Recycle Bin
- **Route**: `POST /mikrotiks/{mikrotik}/restore`
- **Method**: `TenantMikrotikController::restore()`
- **Access**: Admin, Tenant Admin, Network Engineer only
- **Requires**: Current password confirmation
- **Effect**: Router is restored from Recycle Bin

### 3. Permanent Delete (Force Delete)
- **Route**: `DELETE /mikrotiks/{mikrotik}/force-delete`
- **Method**: `TenantMikrotikController::forceDelete()`
- **Access**: Admin, Tenant Admin, Network Engineer only
- **Requires**: Current password confirmation
- **Effect**: Router is permanently deleted from database
- **Reversible**: No, this action is irreversible

## Request Process for Non-Admin Users

If a technician or other non-admin user believes a router should be removed from the system, they must:

1. **Contact Administrator**: Reach out to a Tenant Admin, Network Engineer, or System Admin
2. **Provide Documentation**: Submit a written request including:
   - Router identification (name, location, IP address)
   - Reason for deletion request
   - Impact assessment
   - Date of request
   - Requestor name and role

3. **Wait for Approval**: An authorized administrator will review the request and take appropriate action

## Error Messages

### For Unauthorized Delete Attempts
```
403 Forbidden: You do not have permission to delete routers. 
Please submit a router deletion request to your administrator 
with documentation explaining why it should be removed.
```

### For Unauthorized Restore Attempts
```
403 Forbidden: You do not have permission to restore routers. 
Please contact your administrator.
```

### For Unauthorized Force Delete Attempts
```
403 Forbidden: You do not have permission to permanently delete routers. 
Please contact your administrator.
```

## Implementation Details

### Route Protection
```php
// Admin-only operations
Route::middleware(['role:tenant_admin|admin|network_engineer'])->group(function () {
    Route::delete('mikrotiks/{mikrotik}', [TenantMikrotikController::class, 'destroy']);
    Route::delete('mikrotiks/{mikrotik}/force-delete', [TenantMikrotikController::class, 'forceDelete']);
    Route::post('mikrotiks/{mikrotik}/restore', [TenantMikrotikController::class, 'restore']);
});

// General operations (all network staff)
Route::middleware(['role:tenant_admin|admin|network_engineer|network_admin|technical'])->group(function () {
    Route::resource('mikrotiks', TenantMikrotikController::class)->except(['destroy']);
});
```

### Controller Authorization
Each deletion method includes explicit role checking:
```php
$user = auth()->user();
if (!$user->hasAnyRole(['admin', 'tenant_admin', 'network_engineer'])) {
    abort(403, 'Permission denied message...');
}
```

## Rationale

### Why Restrict Deletion?
1. **System Stability**: Accidental router deletion can disrupt network services
2. **Audit Trail**: Ensures all deletions are performed by authorized personnel
3. **Documentation**: Forces proper documentation of why routers are removed
4. **Accountability**: Clear chain of responsibility for infrastructure changes
5. **Recovery**: Prevents irreversible mistakes by non-authorized users

### What Technicians Can Do
Technicians and technical staff can still:
- View all routers
- Monitor router status
- Reboot routers
- Update router configurations
- Test connections
- Download scripts
- Manage active sessions
- View interfaces and resources
- Update router identity

## Best Practices

1. **Always Document**: Even authorized users should document deletion reasons
2. **Use Soft Delete First**: Move to Recycle Bin before permanent deletion
3. **Verify Impact**: Check for active users and services before deletion
4. **Communicate**: Notify relevant teams before removing routers
5. **Backup Configs**: Export router configurations before deletion

## Related Files

- **Routes**: `routes/web.php` (lines 450-469)
- **Controller**: `app/Http/Controllers/Tenants/TenantMikrotikController.php`
- **Model**: `app/Models/Tenants/TenantMikrotik.php`

## Change Log

- **2026-01-23**: Initial policy implementation
  - Separated delete operations from general operations
  - Added role-based authorization checks
  - Created documentation for deletion request process
