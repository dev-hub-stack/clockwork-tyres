#!/bin/bash
#
# Reporting CRM - Queue Worker Management
# 
# Manage queue workers easily.
# Usage:
#   bash scripts/queue-worker.sh status
#   bash scripts/queue-worker.sh start
#   bash scripts/queue-worker.sh stop
#   bash scripts/queue-worker.sh restart
#   bash scripts/queue-worker.sh logs
#

APP_PATH="/var/www/reporting"
WORKER_NAME="reporting-queue-worker"

case "$1" in
    status)
        sudo supervisorctl status $WORKER_NAME:*
        ;;
    start)
        sudo supervisorctl start $WORKER_NAME:*
        echo "Workers started"
        sudo supervisorctl status $WORKER_NAME:*
        ;;
    stop)
        sudo supervisorctl stop $WORKER_NAME:*
        echo "Workers stopped"
        ;;
    restart)
        php $APP_PATH/artisan queue:restart
        sudo supervisorctl restart $WORKER_NAME:*
        echo "Workers restarted"
        sudo supervisorctl status $WORKER_NAME:*
        ;;
    logs)
        tail -f $APP_PATH/storage/logs/queue-worker.log
        ;;
    *)
        echo "Usage: $0 {status|start|stop|restart|logs}"
        exit 1
        ;;
esac
