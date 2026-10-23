#!/usr/bin/env bash
set -Eeuo pipefail
cd /var/www/html
umask 0027
: "${APP_KEY:?APP_KEY must be retained across deployments}"
: "${DEPLOYMENT_ID:?Missing deployment identity}"

mkdir -p storage/app/locks storage/app/server-storage storage/app/editable-views storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache
chown www-data:www-data storage storage/app storage/app/locks storage/app/server-storage storage/app/editable-views storage/framework storage/framework/{sessions,views,cache,cache/data} storage/logs bootstrap/cache
touch storage/app/locks/storage-backup.lock
chown www-data:www-data storage/app/locks/storage-backup.lock
chmod 0660 storage/app/locks/storage-backup.lock

marker=storage/app/server-storage/.filevault-volume-id
if [[ -f "$marker" && "$(cat "$marker")" != "$DEPLOYMENT_ID" ]]; then
    echo 'ERROR: Upload volume belongs to another deployment. Refusing startup.' >&2; exit 1
fi
if [[ ! -f "$marker" ]]; then printf '%s\n' "$DEPLOYMENT_ID" > "$marker"; chown www-data:www-data "$marker"; fi

# The legacy installation middleware reads this marker file directly.
printf 'INSTALLED=true\n' > .env
chown root:www-data .env
chmod 0640 .env

# Startup/schema changes cannot overlap a consistent backup.
exec 9<storage/app/locks/storage-backup.lock
flock -x 9
php artisan config:clear
php artisan package:discover --ansi
php artisan migrate --force --no-interaction
php docker/bootstrap-admin.php
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data bootstrap/cache storage/framework storage/logs
flock -u 9
exec 9<&-
exec "$@"
