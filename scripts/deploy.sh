#!/bin/bash
#
# Reporting CRM - Deploy Script
# 
# Quick deployment script for pulling latest code and restarting services.
# Run with: bash scripts/deploy.sh
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

APP_PATH="/var/www/reporting"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Reporting CRM - Deploy${NC}"
echo -e "${GREEN}========================================${NC}"

cd $APP_PATH

# Pull latest code
echo -e "${YELLOW}Pulling latest code...${NC}"
git pull origin main

# Install/update dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# Clear and cache
echo -e "${YELLOW}Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo -e "${YELLOW}Rebuilding caches...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (if any)
echo -e "${YELLOW}Running migrations...${NC}"
php artisan migrate --force

# Restart queue workers
echo -e "${YELLOW}Restarting queue workers...${NC}"
php artisan queue:restart
sudo supervisorctl restart reporting-queue-worker:*

# Regenerate Filament assets (if applicable)
echo -e "${YELLOW}Optimizing Filament...${NC}"
php artisan filament:optimize 2>/dev/null || true

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
