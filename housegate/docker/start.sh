#!/bin/sh
set -e
cd /var/www/html

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/app.conf.template > /etc/nginx/conf.d/default.conf

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# run artisan as www-data so cache/log files are never root-owned
art() { su -s /bin/sh www-data -c "php artisan $*"; }

art package:discover --ansi
art storage:link || true
art config:cache
art view:cache
art route:cache || true
art migrate --force

if [ "${RUN_SEED:-false}" = "true" ]; then
  art db:seed --force
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/app.conf