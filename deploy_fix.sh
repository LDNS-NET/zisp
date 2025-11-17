#!/bin/bash

# Clear all caches to ensure the fix takes effect
php artisan config:clear
php artisan route:clear  
php artisan cache:clear
php artisan view:clear

echo "Caches cleared. The tenant domain isolation fix has been deployed."
echo ""
echo "Key changes made:"
echo "1. Removed redundant tenant initialization that was causing 500 errors"
echo "2. Let Stancl Tenancy middleware handle tenant context properly"
echo "3. Fixed middleware execution order"
echo ""
echo "Test scenarios:"
echo "- Visit tenant subdomain: should work without 500 errors"
echo "- Visit invalid subdomain: should redirect to registration"
echo "- Tenant on central domain: should redirect to their subdomain"
