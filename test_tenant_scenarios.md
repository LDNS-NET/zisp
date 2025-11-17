# Tenant Domain Handling Test Scenarios

## Test Implementation Checklist

### ✅ Implementation Complete

1. **EnsureTenantDomain Middleware** - ✅ Updated
   - Validates tenant domains against `domains` table
   - Handles unauthenticated users properly
   - Logs invalid domain attempts
   - Redirects to appropriate pages based on context

2. **Exception Handler** - ✅ Enhanced
   - Handles `TenantCouldNotBeIdentifiedException`
   - Handles `AuthenticationException` for tenant domains
   - Handles 404 errors on tenant domains
   - General tenant error handling with logging
   - CSRF token mismatch handling

3. **Routes** - ✅ Updated
   - Central routes protected with `central` middleware
   - Tenant routes properly organized
   - SuperAdmin routes restricted to central domains

## Test Scenarios to Verify

### Scenario 1: Valid Tenant Subdomain + No Session
**Expected:** Redirect to `https://tenant-subdomain.zyraaf.cloud/login`
**Test:**
1. Clear browser cookies/session
2. Visit `https://tenant1.zyraaf.cloud`
3. Should redirect to `https://tenant1.zyraaf.cloud/login`

### Scenario 2: Valid Tenant Subdomain + Active Session
**Expected:** Load tenant dashboard or requested page
**Test:**
1. Login as tenant user
2. Visit `https://tenant1.zyraaf.cloud/dashboard`
3. Should load dashboard successfully

### Scenario 3: Invalid Subdomain
**Expected:** Redirect to `https://zyraaf.cloud/register`
**Test:**
1. Visit `https://invalid.zyraaf.cloud`
2. Should redirect to central registration page

### Scenario 4: Central Domain Access
**Expected:** Load central pages normally
**Test:**
1. Visit `https://zyraaf.cloud`
2. Should load welcome page
3. Visit `https://zyraaf.cloud/register`
4. Should load registration page

### Scenario 5: Tenant Accessing Central Routes
**Expected:** Redirect to tenant login or central registration
**Test:**
1. From `https://tenant1.zyraaf.cloud`, try to access central-only routes
2. Should be blocked and redirected appropriately

### Scenario 6: MikroTik Sync Endpoints
**Expected:** Work from any domain with valid token
**Test:**
1. POST to `https://tenant1.zyraaf.cloud/mikrotiks/{id}/sync` with valid token
2. Should work regardless of domain

## Error Handling Tests

### Test 1: 500 Error Prevention
1. Visit various invalid tenant URLs
2. Should never see 500 error page
3. Should always get proper redirects

### Test 2: Database Connection Errors
1. Temporarily break database connection
2. Visit tenant subdomain
3. Should handle gracefully without 500 errors

### Test 3: Tenant Initialization Errors
1. Corrupt tenant data in database
2. Visit tenant subdomain
3. Should redirect to central registration

## Logging Verification

Check these log files for proper error tracking:
- `storage/logs/laravel.log`

Expected log entries:
- `'Invalid tenant domain attempted'` for invalid subdomains
- `'Unauthenticated access attempt on tenant domain'` for login redirects
- `'Tenant could not be identified'` for tenant resolution failures
- `'Tenant error occurred'` for general tenant errors

## Configuration Verification

### Check these files:
1. `config/tenancy.php` - Central domains configured
2. `.env` - APP_URL set correctly
3. `database/domains` table - Contains valid tenant domains

### Middleware Registration:
- `EnsureTenantDomain` should be in `bootstrap/app.php` global web middleware
- `CentralDomainOnly` should be aliased as 'central' middleware

## Manual Testing Commands

```bash
# Clear caches to ensure latest changes
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check middleware registration
php artisan route:list --middleware=tenant.domain
php artisan route:list --middleware=central

# View tenant routes
php artisan route:list --path=tenant

# Test domain resolution
php artisan tinker
>>> \Stancl\Tenancy\Database\Models\Domain::all()
```

## Expected Behavior Summary

| Domain | Auth Status | Expected Result |
|--------|-------------|-----------------|
| `zyraaf.cloud` | Any | Load central pages |
| `tenant1.zyraaf.cloud` | Logged in | Load tenant pages |
| `tenant1.zyraaf.cloud` | Not logged in | Redirect to `/login` |
| `invalid.zyraaf.cloud` | Any | Redirect to `/register` |

## Troubleshooting

If issues occur:
1. Check Laravel logs: `tail -f storage/logs/laravel.log`
2. Verify middleware registration: `php artisan route:list`
3. Check domain table: `php artisan tinker >>> \Stancl\Tenancy\Database\Models\Domain::all()`
4. Clear all caches and retest
5. Verify APP_URL in `.env` matches central domain
