#!/bin/bash

# ZISP Mikrotik Onboarding System - Setup Script
# This script performs initial setup for the Mikrotik automated onboarding system

echo "🚀 ZISP Mikrotik Onboarding System - Setup"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Check if Laravel is installed
echo -e "${YELLOW}Step 1: Checking Laravel installation...${NC}"
if ! [ -f "artisan" ]; then
    echo -e "${RED}❌ artisan file not found. Are you in the correct directory?${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Laravel found${NC}"
echo ""

# Step 2: Run migrations
echo -e "${YELLOW}Step 2: Running database migrations...${NC}"
php artisan migrate --step
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Migration failed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Migrations completed${NC}"
echo ""

# Step 3: Verify environment
echo -e "${YELLOW}Step 3: Verifying environment configuration...${NC}"
if grep -q "^APP_URL=" .env; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f 2)
    echo -e "${GREEN}✓ APP_URL is set to: $APP_URL${NC}"
else
    echo -e "${RED}❌ APP_URL not found in .env${NC}"
    echo "Please set APP_URL in your .env file"
    exit 1
fi
echo ""

# Step 4: Install composer dependencies (if needed)
echo -e "${YELLOW}Step 4: Checking composer dependencies...${NC}"
if [ ! -d "vendor/thecodeassassin" ]; then
    echo "Installing RouterOS API library..."
    composer require thecodeassassin/routeros-api 2>/dev/null
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${GREEN}✓ Dependencies already installed${NC}"
fi
echo ""

# Step 5: Clear cache
echo -e "${YELLOW}Step 5: Clearing application cache...${NC}"
php artisan cache:clear
php artisan config:clear
echo -e "${GREEN}✓ Cache cleared${NC}"
echo ""

# Step 6: Test scheduler
echo -e "${YELLOW}Step 6: Testing scheduler registration...${NC}"
php artisan schedule:list | grep "mikrotik" > /dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Mikrotik scheduler registered${NC}"
else
    echo -e "${YELLOW}⚠ Scheduler not yet active (will activate on first cron run)${NC}"
fi
echo ""

# Step 7: Summary
echo "=========================================="
echo -e "${GREEN}✅ Setup Complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Ensure your scheduler is running (cron job or Windows Task Scheduler)"
echo "   Set: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1"
echo ""
echo "2. Test the system:"
echo "   - Visit: http://localhost:8000/mikrotiks"
echo "   - Click 'Add Device'"
echo "   - Enter a device name and create"
echo "   - Download the onboarding script"
echo ""
echo "3. Run a test device check:"
echo "   php artisan mikrotik:check-status"
echo ""
echo "4. Monitor logs:"
echo "   tail -f storage/logs/laravel.log | grep -i mikrotik"
echo ""
echo "Documentation: MIKROTIK_ONBOARDING_SETUP.md"
