# Installation Access Control Implementation

## Overview
This document describes the access control rules for the installation management system, ensuring proper visibility and permissions based on user roles.

## Access Rules

### Technicians (technical, technician roles)
Technicians have **restricted access** and can only see:

1. **Unpicked Installations**
   - Status: `new`
   - `picked_by`: `null`
   - These are available for any technician to pick

2. **Their Own Picked Installations**
   - `picked_by` = their user ID
   - Installations they have personally picked

3. **Their Assigned Installations**
   - `technician_id` = their user ID
   - Installations assigned to them by admins

**What technicians CANNOT see:**
- Installations picked by other technicians
- Installations assigned to other technicians
- Any installation that doesn't match the above criteria

### Admins (tenant_admin, admin, network_engineer roles)
Admins have **full access** and can see:
- All installations regardless of status
- All picked and unpicked installations
- All technician assignments
- Complete system overview

## Implementation Details

### Controller Changes (`TenantInstallationController.php`)

#### 1. Index Method
- Added role-based filtering
- Technicians see filtered list based on access rules
- Admins see all installations
- Different stats displayed based on role:
  - **Admin stats**: total, new, pending, scheduled, in_progress, completed, cancelled, today
  - **Technician stats**: available, my_picked, my_assigned, in_progress, completed

#### 2. Show Method
- Added authorization check before displaying installation details
- Technicians are blocked (403 error) if trying to view installations they don't have access to
- Admins can view any installation

### Database Requirements

#### Required Columns
The `tenant_installations` table must have:
- `picked_by` (foreignId, nullable) - References the user who picked the installation
- `picked_at` (timestamp, nullable) - When the installation was picked
- `technician_id` (foreignId, nullable) - The assigned technician
- `status` (enum) - Installation status

#### Migration
Run the migration to add missing columns:
```bash
php artisan migrate
```

Migration file: `2026_01_23_071900_add_picked_by_to_tenant_installations_table.php`

## Workflow

### Picking an Installation (Technician)
1. Technician views "All Installations" page
2. Sees only unpicked installations (status=new, picked_by=null)
3. Clicks "Pick This Job"
4. Provides scheduled date, time, and estimated duration
5. Installation is updated:
   - `picked_by` = technician's ID
   - `picked_at` = current timestamp
   - `technician_id` = technician's ID
   - `status` = 'pending'
6. Installation is now hidden from other technicians
7. Installation appears in technician's "My Installations" page

### Unpicking an Installation (Technician)
1. Only the technician who picked it can unpick
2. Cannot unpick if status is in_progress, completed, or cancelled
3. When unpicked:
   - `picked_by` = null
   - `picked_at` = null
   - `technician_id` = null
   - `status` = 'new'
4. Installation becomes available for other technicians again

### Admin Override
- Admins can view and manage all installations at any time
- Admins can reassign installations
- Admins can see which technician picked which installation

## Navigation

### Technician Navigation
- **My Installations**: Shows only their picked/assigned installations
- **All Installations**: Shows unpicked installations + their own installations

### Admin Navigation
- **All Installations**: Shows complete list of all installations

## Security Considerations

1. **Authorization Checks**: Implemented at controller level to prevent unauthorized access
2. **Database Filtering**: Queries are filtered based on role to prevent data leakage
3. **403 Errors**: Technicians attempting to access restricted installations receive proper error
4. **Role Verification**: Uses Laravel's role system to determine access level

## Testing Checklist

- [ ] Technician can see unpicked installations
- [ ] Technician can pick an installation
- [ ] After picking, installation disappears from other technicians' view
- [ ] Technician can see their picked installations in "My Installations"
- [ ] Technician can unpick their own installations
- [ ] Technician cannot unpick another technician's installations
- [ ] Technician cannot view another technician's picked installations
- [ ] Admin can see all installations regardless of pick status
- [ ] Admin can view any installation detail page
- [ ] Proper 403 error when technician tries to access restricted installation
