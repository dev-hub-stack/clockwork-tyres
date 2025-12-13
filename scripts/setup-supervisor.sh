#!/bin/bash
#
# Reporting CRM - Supervisor Queue Worker Setup Script
# 
# This script sets up supervisor to manage Laravel queue workers.
# Run with: sudo bash scripts/setup-supervisor.sh
#
# Requirements:
# - Ubuntu/Debian server
# - PHP installed
# - Laravel application in /var/www/reporting
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Reporting CRM - Supervisor Setup${NC}"
echo -e "${GREEN}========================================${NC}"

# Configuration
APP_PATH="/var/www/reporting"
APP_USER="www-data"
NUM_WORKERS=2
SUPERVISOR_CONF="/etc/supervisor/conf.d/reporting-queue-worker.conf"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (sudo)${NC}"
    exit 1
fi

# Check if app directory exists
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}Application directory not found: $APP_PATH${NC}"
    exit 1
fi

# Check if artisan exists
if [ ! -f "$APP_PATH/artisan" ]; then
    echo -e "${RED}Laravel artisan not found in: $APP_PATH${NC}"
    exit 1
fi

# Install supervisor if not installed
echo -e "${YELLOW}Checking supervisor installation...${NC}"
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}Installing supervisor...${NC}"
    apt-get update
    apt-get install -y supervisor
fi

# Create log directory if not exists
echo -e "${YELLOW}Creating log directory...${NC}"
mkdir -p "$APP_PATH/storage/logs"
chown -R $APP_USER:$APP_USER "$APP_PATH/storage/logs"

# Create supervisor configuration
echo -e "${YELLOW}Creating supervisor configuration...${NC}"
cat > $SUPERVISOR_CONF << EOF
[program:reporting-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_PATH/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=$APP_PATH
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$APP_USER
numprocs=$NUM_WORKERS
redirect_stderr=true
stdout_logfile=$APP_PATH/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF

echo -e "${GREEN}Supervisor config created: $SUPERVISOR_CONF${NC}"

# Reload supervisor
echo -e "${YELLOW}Reloading supervisor...${NC}"
supervisorctl reread
supervisorctl update
supervisorctl restart reporting-queue-worker:*

# Check status
echo -e "${YELLOW}Checking worker status...${NC}"
sleep 2
supervisorctl status reporting-queue-worker:*

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Useful commands:"
echo -e "  ${YELLOW}sudo supervisorctl status${NC}                          - Check all worker status"
echo -e "  ${YELLOW}sudo supervisorctl restart reporting-queue-worker:*${NC} - Restart all workers"
echo -e "  ${YELLOW}sudo supervisorctl stop reporting-queue-worker:*${NC}    - Stop all workers"
echo -e "  ${YELLOW}sudo supervisorctl start reporting-queue-worker:*${NC}   - Start all workers"
echo -e "  ${YELLOW}tail -f $APP_PATH/storage/logs/queue-worker.log${NC}     - View logs"
echo ""
