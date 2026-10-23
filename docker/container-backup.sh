#!/usr/bin/env bash
set -Eeuo pipefail
umask 0077
cd /var/www/html
lock=storage/app/locks/storage-backup.lock
[[ -f "$lock" ]] || { echo 'Storage lock is missing; refusing an inconsistent backup.' >&2; exit 1; }
as_owner() { setpriv --reuid="${BACKUP_UID:-1000}" --regid="${BACKUP_GID:-1000}" --clear-groups "$@"; }
write_file() { as_owner sh -c 'umask 077; cat > "$1"' sh "$1"; }
# Host-user writes also work on a NAS with root_squash; root is used only to
# read the private www-data-owned uploads volume and coordinate the lock.
[[ "$(as_owner cat /backups/.filevault-target-id)" == "$BACKUP_TARGET_ID" ]] || { echo 'Backup target is missing or changed. Check the backup disk/NAS mount.' >&2; exit 1; }
exec 8>storage/app/locks/backup-job.lock
flock -w 3600 -x 8
exec 9<"$lock"
flock -w 3600 -x 9

stamp="$(date -u +%Y%m%dT%H%M%SZ)-$$"
target="/backups/.incomplete-$stamp"
as_owner mkdir "$target"
trap 'echo "Backup failed/incomplete: $target. Previous completed backups were retained." >&2' ERR
echo "Verifying private files and creating consistent snapshot $stamp..."
runuser -u www-data -- php artisan local-storage:verify --hashes | write_file "$target/integrity.txt"
export MYSQL_PWD="$DB_PASSWORD"
mariadb-dump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
    --single-transaction --quick --skip-lock-tables --hex-blob --no-tablespaces \
    --default-character-set=utf8mb4 "$DB_DATABASE" | gzip -1 | write_file "$target/database.sql.gz"
unset MYSQL_PWD
tar -C storage/app/server-storage -cf - . | write_file "$target/files.tar"
as_owner cat /run/filevault/deployment.env | write_file "$target/deployment.env"
php -r 'echo json_encode(["format_version"=>1,"deployment_id"=>getenv("DEPLOYMENT_ID"),"created_at"=>gmdate("c"),"database"=>getenv("DB_DATABASE")], JSON_PRETTY_PRINT).PHP_EOL;' | write_file "$target/manifest.json"
as_owner gzip -t "$target/database.sql.gz"
as_owner tar -tf "$target/files.tar" >/dev/null
as_owner sh -c 'cd "$1"; sha256sum database.sql.gz files.tar deployment.env manifest.json integrity.txt > SHA256SUMS' sh "$target"
as_owner sync -f "$target"
as_owner mv "$target" "/backups/$stamp"
printf '%s\n' "$stamp" | write_file /backups/.last-success.tmp
as_owner mv /backups/.last-success.tmp /backups/.last-success
as_owner sync -f /backups
echo "Backup complete: /backups/$stamp (database + uploads + deployment secrets + SHA256SUMS)."
