#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)/docker/lib.sh"
require_docker
umask 0077
[[ -f "$PROJECT_ROOT/common/foundation/src/CommonServiceProvider.php" ]] || die 'The common/ source is missing; include the project submodule before building.'

if [[ ! -f "$FILEVAULT_ENV_FILE" ]]; then
    command -v openssl >/dev/null || die 'openssl is required to generate deployment secrets.'
    for volume in filevault_database filevault_uploads filevault_runtime; do
        if docker volume inspect "$volume" >/dev/null 2>&1; then die "Existing $volume found without deployment config. Recover .env.docker from backup first."; fi
    done
    touch "$FILEVAULT_ENV_FILE"; chmod 0600 "$FILEVAULT_ENV_FILE"
    write_value "$FILEVAULT_ENV_FILE" APP_PORT "${APP_PORT:-8085}"
    write_value "$FILEVAULT_ENV_FILE" APP_URL "${APP_URL:-http://localhost:${APP_PORT:-8085}}"
    write_value "$FILEVAULT_ENV_FILE" APP_KEY "base64:$(openssl rand -base64 32)"
    write_value "$FILEVAULT_ENV_FILE" DEPLOYMENT_ID "$(openssl rand -hex 16)"
    write_value "$FILEVAULT_ENV_FILE" DB_DATABASE filevault
    write_value "$FILEVAULT_ENV_FILE" DB_USERNAME filevault
    write_value "$FILEVAULT_ENV_FILE" DB_PASSWORD "$(openssl rand -hex 24)"
    write_value "$FILEVAULT_ENV_FILE" DB_ROOT_PASSWORD "$(openssl rand -hex 32)"
    write_value "$FILEVAULT_ENV_FILE" DB_VOLUME filevault_database
    write_value "$FILEVAULT_ENV_FILE" FILES_VOLUME filevault_uploads
    write_value "$FILEVAULT_ENV_FILE" RUNTIME_VOLUME filevault_runtime
    write_value "$FILEVAULT_ENV_FILE" BACKUP_DIR "${BACKUP_DIR:-$HOME/filevault-backups}"
    write_value "$FILEVAULT_ENV_FILE" BACKUP_TARGET_ID "$(openssl rand -hex 16)"
    write_value "$FILEVAULT_ENV_FILE" BACKUP_INTERVAL_SECONDS "${BACKUP_INTERVAL_SECONDS:-86400}"
    write_value "$FILEVAULT_ENV_FILE" BACKUP_UID "$(id -u)"
    write_value "$FILEVAULT_ENV_FILE" BACKUP_GID "$(id -g)"
    write_value "$FILEVAULT_ENV_FILE" BOOTSTRAP_ADMIN_EMAIL "${BOOTSTRAP_ADMIN_EMAIL:-admin@filevault.local}"
    write_value "$FILEVAULT_ENV_FILE" BOOTSTRAP_ADMIN_PASSWORD "$(openssl rand -hex 12)"
    write_value "$FILEVAULT_ENV_FILE" SESSION_SECURE_COOKIE false
    write_value "$FILEVAULT_ENV_FILE" DATA_INITIALIZED false
fi
load_config
mkdir -p -- "$BACKUP_DIR"
if [[ -f "$BACKUP_DIR/.filevault-target-id" ]]; then
    [[ "$(cat "$BACKUP_DIR/.filevault-target-id")" == "$BACKUP_TARGET_ID" ]] || die 'Backup target identity mismatch. Mount the correct backup disk/NAS.'
elif [[ "$DATA_INITIALIZED" == true ]]; then
    die 'Backup target marker is missing. The backup disk/NAS may not be mounted; refusing startup.'
else
    printf '%s\n' "$BACKUP_TARGET_ID" > "$BACKUP_DIR/.filevault-target-id"
fi
compose config --quiet
for volume in "$DB_VOLUME" "$FILES_VOLUME" "$RUNTIME_VOLUME"; do
    if docker volume inspect "$volume" >/dev/null 2>&1; then check_volume "$volume"
    elif [[ "$DATA_INITIALIZED" == true ]]; then die "Previously initialized volume $volume is missing. Refusing to start an empty deployment."
    else create_volume "$volume"; fi
done
echo 'Building app image. Existing uploads and database are not part of the image.'
compose build app
compose up -d --wait --wait-timeout 240 db
if [[ "$DATA_INITIALIZED" == true && "${FILEVAULT_SKIP_PREDEPLOY_BACKUP:-0}" != 1 ]]; then
    echo 'Taking a consistent backup before application startup/migrations...'
    compose run --rm --no-deps --entrypoint /usr/local/bin/filevault-backup backup
fi
compose up -d --no-build --wait --wait-timeout 300 app
if [[ "$DATA_INITIALIZED" != true ]]; then write_value "$FILEVAULT_ENV_FILE" DATA_INITIALIZED true; export DATA_INITIALIZED=true; fi
compose up -d --no-build backup
echo "Ready: $APP_URL/files"
echo "Initial admin email: $BOOTSTRAP_ADMIN_EMAIL"
echo "Initial admin password: stored as BOOTSTRAP_ADMIN_PASSWORD in $FILEVAULT_ENV_FILE (existing passwords are never reset)."
echo "Automatic backup directory: $BACKUP_DIR"
echo 'For protection from server disk failure, place BACKUP_DIR on an independent disk/NAS and keep an off-server copy.'
compose ps
