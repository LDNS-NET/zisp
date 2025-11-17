# Tenant Domain Isolation Test Guide

## ✅ Implementation Summary

### Key Changes Made:
1. **EnsureTenantDomain Middleware** - Enhanced to:
   - Detect tenant users on central domains and redirect them to their subdomain
   - Force logout when tenant users access central domains
   - Verify tenant data isolation (user belongs to current tenant)
   - Initialize tenant context properly for subdomains

2. **Exception Handler** - Enhanced to:
   - Handle tenant access from central domains
   - Prevent cross-domain authentication issues

3. **Auth Routes** - Updated to:
   - Registration only on central domains
   - Login works on both domains but with proper redirection
   - Clear documentation of domain-specific behavior

## 🎯 Expected Behavior

### For Tenant Users:
- **Central Domain Access**: Automatically redirected to their subdomain login
- **Subdomain Access**: Can only login on their assigned subdomain
- **Data Isolation**: Can only access data belonging to their tenant
- **Session Isolation**: Sessions are domain-specific

### For Super Admins:
- **Central Domain Access**: Full access to central features
- **Subdomain Access**: Can access any tenant subdomain for management

### For Guests:
- **Central Domain**: Can register and login as central users
- **Valid Tenant Subdomain**: Redirected to tenant login
- **Invalid Subdomain**: Redirected to central registration

## 🧪 Test Scenarios

### Scenario 1: Tenant User Accessing Central Domain
**Steps:**
1. Login as tenant user on their subdomain (e.g., `tenant1.zyraaf.cloud`)
2. Copy session cookie or try to access `zyraaf.cloud`
3. **Expected**: Redirected to `https://tenant1.zyraaf.cloud/login`

### Scenario 2: Tenant User Wrong Subdomain
**Steps:**
1. Login as tenant1 user
2. Try to access `https://tenant2.zyraaf.cloud`
3. **Expected**: Redirected to `https://tenant1.zyraaf.cloud/login`

### Scenario 3: Data Isolation Verification
**Steps:**
1. Login as tenant1 on `https://tenant1.zyraaf.cloud`
2. Access `/dashboard` - should show tenant1 data only
3. Try to access `/api/users` - should return tenant1 users only
4. **Expected**: No cross-tenant data leakage

### Scenario 4: Session Domain Isolation
**Steps:**
1. Login as tenant1 on their subdomain
2. Check session cookie domain - should be `tenant1.zyraaf.cloud`
3. Try accessing central domain with same session
4. **Expected**: Session invalid on central domain

### Scenario 5: Super Admin Cross-Domain Access
**Steps:**
1. Login as super admin on central domain
2. Access `https://tenant1.zyraaf.cloud`
3. **Expected**: Should work (super admin bypass)

### Scenario 6: Registration Domain Restrictions
**Steps:**
1. Try to register on `https://tenant1.zyraaf.cloud/register`
2. **Expected**: Redirected to central domain registration

## 🔍 Debugging Commands

### Check Middleware Registration:
```bash
php artisan route:list --middleware=tenant.domain
php artisan route:list --middleware=central
```

### Check Tenant Domains:
```bash
php artisan tinker
>>> \Stancl\Tenancy\Database\Models\Domain::all()
>>> \App\Models\User::where('tenant_id', '!=', null)->with('tenant.domains')->get()
```

### Monitor Logs:
```bash
tail -f storage/logs/laravel.log | grep -E "(Tenant user accessing central|User accessing wrong tenant|Invalid tenant domain)"
```

### Test Tenant Context:
```bash
php artisan tinker
>>> \Stancl\Tenancy\Tenancy::initialize('tenant1');
>>> tenant()->id
```

## 🚨 Important Notes

### Session Configuration:
Ensure your `config/session.php` has:
```php
'domain' => env('SESSION_DOMAIN', null), // Should be null for domain-specific sessions
'same_site' => 'lax',
```

### Environment Variables:
```env
APP_URL=https://zyraaf.cloud
SESSION_DOMAIN=null
```

### Database Checks:
1. Verify `domains` table has correct tenant domains
2. Verify `users` table has correct `tenant_id` values
3. Verify `tenants` table has proper tenant records

## 📋 Verification Checklist

- [ ] Tenant users cannot login on central domain
- [ ] Tenant users are redirected to their subdomain from central domain
- [ ] Tenant users can only access their own data
- [ ] Sessions are domain-isolated
- [ ] Super admins can access any domain
- [ ] Registration only works on central domain
- [ ] Invalid subdomains redirect to registration
- [ ] No 500 errors in any scenario

## 🔧 Troubleshooting

### Issue: Tenant can still access central domain
**Check:**
1. Ensure `EnsureTenantDomain` is registered in `bootstrap/app.php`
2. Clear caches: `php artisan config:clear && php artisan route:clear`
3. Check middleware order in web group

### Issue: Data leakage between tenants
**Check:**
1. Verify tenant context is properly initialized
2. Check database queries include tenant_id filters
3. Verify model relationships use tenant scoping

### Issue: Session sharing across domains
**Check:**
1. Verify SESSION_DOMAIN is null in config
2. Check cookie domain settings
3. Clear browser cookies and retest

### Issue: 500 errors on tenant domains
**Check:**
1. Verify domain exists in `domains` table
2. Check tenant initialization in logs
3. Verify all required middleware are applied

## 🎯 Success Criteria

When all tests pass, you should have:
- Complete tenant domain isolation
- No cross-tenant data access
- Proper domain-based authentication
- Automatic redirection to correct domains
- No 500 errors in any scenario
