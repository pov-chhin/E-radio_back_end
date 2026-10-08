#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
    php artisan optimize
fi

# Uploads and YouTube episodes are converted by queued jobs, so something has to run the queue. On a host that runs a
# single container (a Dokploy application) keep one worker beside Apache: as www-data, because it writes to storage,
# started again if it ever stops, and recycled hourly. RUN_QUEUE_WORKER=0 turns it off where a separate queue service
# does the job (compose.yaml). It starts only with the web server, never for a one-off `php artisan ...` run through
# this script.
if [ "${RUN_QUEUE_WORKER:-1}" = "1" ] && [ "${1:-}" = "apache2-foreground" ]; then
    (
        while true; do
            runuser -u www-data -- php artisan queue:work --sleep=3 --tries=1 --timeout=720 --max-time=3600 || true
            sleep 5
        done
    ) &
fi

exec "$@"
