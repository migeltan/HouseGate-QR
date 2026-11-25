#!/bin/sh
set -e
cd /var/www/html

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/app.conf.template > /etc/nginx/conf.d/default.conf

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache

# public/ is owned by root, so the storage symlink must be created as root
php artisan storage:link --force || true

chown -R www-data:www-data storage bootstrap/cache

# run artisan as www-data so cache/log files are never root-owned
art() { su -s /bin/sh www-data -c "php artisan $*"; }

art package:discover --ansi
art config:cache
art view:cache
art route:cache || true
art migrate --force

if [ "${RUN_SEED:-false}" = "true" ]; then
  art db:seed --force
fi

# the container disk is wiped on every start, so re-import congressman photos each boot
if [ -d database/data/photos ]; then
  art congressmen:import-photos || true
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/app.conf