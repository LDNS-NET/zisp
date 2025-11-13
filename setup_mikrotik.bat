@echo off
REM ZISP Mikrotik Onboarding System - Windows Setup Script
REM This script performs initial setup for the Mikrotik automated onboarding system

echo.
echo 🚀 ZISP Mikrotik Onboarding System - Setup
echo ==========================================
echo.

REM Step 1: Check if Laravel is installed
echo Step 1: Checking Laravel installation...
if not exist "artisan" (
    echo ❌ artisan file not found. Are you in the correct directory?
    exit /b 1
)
echo ✓ Laravel found
echo.

REM Step 2: Run migrations
echo Step 2: Running database migrations...
php artisan migrate --step
if errorlevel 1 (
    echo ❌ Migration failed
    exit /b 1
)
echo ✓ Migrations completed
echo.

REM Step 3: Verify environment
echo Step 3: Verifying environment configuration...
for /f "tokens=2 delims==" %%A in ('findstr /R "^APP_URL=" .env') do set APP_URL=%%A
if defined APP_URL (
    echo ✓ APP_URL is set to: %APP_URL%
) else (
    echo ❌ APP_URL not found in .env
    echo Please set APP_URL in your .env file
    exit /b 1
)
echo.

REM Step 4: Install composer dependencies (if needed)
echo Step 4: Checking composer dependencies...
if not exist "vendor\thecodeassassin" (
    echo Installing RouterOS API library...
    composer require thecodeassassin/routeros-api
    echo ✓ Dependencies installed
) else (
    echo ✓ Dependencies already installed
)
echo.

REM Step 5: Clear cache
echo Step 5: Clearing application cache...
php artisan cache:clear
php artisan config:clear
echo ✓ Cache cleared
echo.

REM Step 6: Test scheduler
echo Step 6: Testing scheduler registration...
php artisan schedule:list | findstr /R "mikrotik" > nul
if errorlevel 1 (
    echo ⚠ Scheduler not yet active (will activate on first run)
) else (
    echo ✓ Mikrotik scheduler registered
)
echo.

REM Step 7: Summary
echo ==========================================
echo ✅ Setup Complete!
echo.
echo Next steps:
echo 1. Set up Windows Task Scheduler:
echo    - Create a task that runs: php artisan schedule:run
echo    - Set it to run every 1 minute
echo    - See SCHEDULER_SETUP.md for detailed instructions
echo.
echo 2. Test the system:
echo    - Visit: http://localhost:8000/mikrotiks
echo    - Click 'Add Device'
echo    - Enter a device name and create
echo    - Download the onboarding script
echo.
echo 3. Run a test device check:
echo    php artisan mikrotik:check-status
echo.
echo 4. Monitor logs:
echo    tail -f storage/logs/laravel.log ^| grep -i mikrotik (requires bash)
echo.
echo Documentation: MIKROTIK_ONBOARDING_SETUP.md
echo.
pause
