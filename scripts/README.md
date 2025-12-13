# Server Scripts

This directory contains executable scripts for server management.

## Scripts Overview

| Script | Description | Usage |
|--------|-------------|-------|
| `setup-supervisor.sh` | Configure supervisor for queue workers | `sudo bash scripts/setup-supervisor.sh` |
| `deploy.sh` | Deploy latest code and restart services | `bash scripts/deploy.sh` |
| `queue-worker.sh` | Manage queue workers | `bash scripts/queue-worker.sh {status\|start\|stop\|restart\|logs}` |

## Quick Start (New Server)

```bash
# 1. Clone repository
git clone <repo-url> /var/www/reporting
cd /var/www/reporting

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
nano .env  # Edit your settings

# 4. Setup application
php artisan key:generate
php artisan migrate
php artisan storage:link

# 5. Setup queue workers
sudo bash scripts/setup-supervisor.sh

# 6. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Queue Worker Management

```bash
# Check status
bash scripts/queue-worker.sh status

# Restart workers (after code changes)
bash scripts/queue-worker.sh restart

# View live logs
bash scripts/queue-worker.sh logs

# Stop workers (for maintenance)
bash scripts/queue-worker.sh stop

# Start workers
bash scripts/queue-worker.sh start
```

## Deployment

For regular deployments after initial setup:

```bash
bash scripts/deploy.sh
```

This will:
1. Pull latest code from git
2. Install composer dependencies
3. Clear and rebuild caches
4. Run migrations
5. Restart queue workers
6. Optimize Filament
7. Set correct permissions

## Supervisor Configuration

The supervisor config is stored at:
- `/etc/supervisor/conf.d/reporting-queue-worker.conf`

To manually manage supervisor:

```bash
# View all status
sudo supervisorctl status

# Reload config after changes
sudo supervisorctl reread
sudo supervisorctl update

# Restart specific worker group
sudo supervisorctl restart reporting-queue-worker:*
```

## Logs

- Queue worker logs: `/var/www/reporting/storage/logs/queue-worker.log`
- Laravel logs: `/var/www/reporting/storage/logs/laravel.log`
- Supervisor logs: `/var/log/supervisor/supervisord.log`

## Sync Services

The Reporting CRM receives sync data from TunerStop Admin:

- **Orders**: `POST /api/order-sync/comprehensive-sync`
- **Products**: `POST /api/webhooks/products/sync`
- **Addons**: `POST /api/webhooks/addons/sync`
- **Addon Categories**: `POST /api/webhooks/addon-categories/sync`

## Troubleshooting

### Workers not starting
```bash
# Check supervisor status
sudo supervisorctl status

# Check logs
tail -100 /var/www/reporting/storage/logs/queue-worker.log

# Test queue worker manually
cd /var/www/reporting
php artisan queue:work --once
```

### Permission issues
```bash
sudo chown -R www-data:www-data /var/www/reporting
sudo chmod -R 775 /var/www/reporting/storage
```

### Route cache issues
```bash
php artisan route:clear
php artisan route:cache
php artisan route:list | grep sync
```
