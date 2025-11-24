#!/bin/bash

# Production Migration and Seeder Deployment Script
# This script runs migrations and seeds permissions with proper sudo access

set -e  # Exit on error

echo "=========================================="
echo "Production Deployment Script"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo -e "${YELLOW}Working directory: $SCRIPT_DIR${NC}"
echo ""

# Confirm production deployment
echo -e "${RED}WARNING: This will run migrations and seeders on PRODUCTION!${NC}"
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo -e "${YELLOW}Deployment cancelled.${NC}"
    exit 0
fi

echo ""
echo -e "${GREEN}Step 1: Running database migrations...${NC}"
sudo -u www-data php artisan migrate --force

echo ""
echo -e "${GREEN}Step 2: Running RolesAndPermissionsSeeder...${NC}"
sudo -u www-data php artisan db:seed --class=RolesAndPermissionsSeeder --force

echo ""
echo -e "${GREEN}Step 3: Clearing application cache...${NC}"
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

echo ""
echo -e "${GREEN}Step 4: Optimizing application...${NC}"
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo "=========================================="
echo ""
echo "Summary:"
echo "  ✓ Database migrations executed"
echo "  ✓ Permissions seeded"
echo "  ✓ Application cache cleared and optimized"
echo ""
