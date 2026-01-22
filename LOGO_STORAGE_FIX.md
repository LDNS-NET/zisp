# Logo Storage Fix Guide

## Issue
The logo preview was failing because the application was trying to load images from a hardcoded production URL instead of using relative paths.

## What Was Fixed

### 1. Frontend Changes (General.vue)
- Added error handling for logo image loading
- Implemented `getLogoUrl()` function to convert absolute URLs to relative paths
- Added `logoError` state to track failed image loads
- Added fallback UI when logo fails to load (shows "No logo" placeholder)
- Added `@error` handler on the image element to catch loading failures

### 2. How It Works Now
- When a logo URL is received from the backend (e.g., `https://zispbilling.cloud/storage/logos/xyz.png`)
- The `getLogoUrl()` function extracts just the path part (`/storage/logos/xyz.png`)
- The browser loads the image from the current domain instead of the hardcoded domain
- If the image fails to load, a placeholder is shown instead of a broken image

## Required Setup

### Ensure Storage Symlink Exists
The `public/storage` directory must be symlinked to `storage/app/public`. Run this command:

```bash
php artisan storage:link
```

### Verify File Permissions
Ensure the storage directory is writable:

```bash
# On Linux/Mac
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# On Windows (run as Administrator in PowerShell)
icacls storage /grant Users:F /t
icacls bootstrap\cache /grant Users:F /t
```

### Check .env Configuration
Ensure your `.env` file has the correct APP_URL for your environment:

```env
# For local development
APP_URL=http://localhost

# For production
APP_URL=https://yourdomain.com
```

## Testing

1. Upload a new logo in Settings > General
2. The logo should preview immediately after upload
3. Refresh the page - the logo should still display
4. If you see "No logo" placeholder, check:
   - Storage symlink exists: `ls -la public/storage` (Linux/Mac) or `dir public\storage` (Windows)
   - File exists in `storage/app/public/logos/`
   - File permissions are correct

## Troubleshooting

### Logo still not showing?

1. **Check browser console** for specific error messages
2. **Verify storage link**: 
   ```bash
   php artisan storage:link
   ```
3. **Check if file exists**:
   ```bash
   ls storage/app/public/logos/
   ```
4. **Clear application cache**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```
5. **Check web server configuration** - ensure it serves files from `public/storage`

### Different domain in production?

If your production environment uses a different domain, the fix ensures that:
- Images are loaded using relative paths (not absolute URLs)
- The browser requests images from the current domain
- No cross-origin issues occur
