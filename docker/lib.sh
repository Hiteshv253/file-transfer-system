#!/usr/bin/env bash
# Shared by the host-side management scripts. Configuration is parsed, never executed.
set -Eeuo pipefail
PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
export FILEVAULT_ENV_FILE="${FILEVAULT_ENV_FILE:-$PROJECT_ROOT/.env.docker}"
if [[ "$FILEVAULT_ENV_FILE" != /* ]]; then export FILEVAULT_ENV_FILE="$PWD/$FILEVAULT_ENV_FILE"; fi
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
require_docker() {
    command -v docker >/dev/null || die 'Install Docker Engine/Desktop and the Docker Compose v2 plugin first.'
    docker info >/dev/null 2>&1 || die 'Docker daemon is not available to this user.'
    docker compose version >/dev/null 2>&1 || die 'Docker Compose v2 is required.'
}
read_value() {
    local file="$1" key="$2" line
    line="$(sed -n "s/^${key}=//p" "$file" | tail -n 1)"
    if [[ "$line" == "'"*"'" ]]; then line="${line:1:${#line}-2}"; fi
    if [[ "$line" == '"'*'"' ]]; then line="${line:1:${#line}-2}"; fi
    printf '%s' "$line"
}
write_value() {
    local file="$1" key="$2" value="$3"
    [[ "$key" =~ ^[A-Z_0-9]+$ ]] || die 'Invalid config key.'
    [[ "$value" != *"'"* && "$value" != *$'\n'* && "$value" != *$'\r'* ]] || die "Unsupported quote/newline in $key."
    # Last definition wins in both this reader and Compose's dotenv parser.
    printf "%s='%s'\n" "$key" "$value" >> "$file"
}
load_config() {
    [[ -f "$FILEVAULT_ENV_FILE" ]] || die 'Deployment config missing. Run bash start.sh first, or restore it from a backup.'
    local key value
    for key in APP_PORT APP_URL APP_KEY DEPLOYMENT_ID DB_DATABASE DB_USERNAME DB_PASSWORD DB_ROOT_PASSWORD DB_VOLUME FILES_VOLUME RUNTIME_VOLUME BACKUP_DIR BACKUP_TARGET_ID BACKUP_INTERVAL_SECONDS BACKUP_UID BACKUP_GID BOOTSTRAP_ADMIN_EMAIL BOOTSTRAP_ADMIN_PASSWORD SESSION_SECURE_COOKIE DATA_INITIALIZED; do
        value="$(read_value "$FILEVAULT_ENV_FILE" "$key")"
        [[ -n "$value" ]] || die "Missing $key in $FILEVAULT_ENV_FILE"
        printf -v "$key" '%s' "$value"; export "$key"
    done
    [[ "$APP_PORT" =~ ^[0-9]+$ && "$APP_PORT" -gt 0 && "$APP_PORT" -lt 65536 ]] || die 'Invalid APP_PORT.'
    [[ "$DB_DATABASE" =~ ^[a-zA-Z0-9_]+$ && "$DB_USERNAME" =~ ^[a-zA-Z0-9_]+$ ]] || die 'Use simple alphanumeric database/user names.'
    [[ "$DEPLOYMENT_ID" =~ ^[a-f0-9]{32}$ ]] || die 'Invalid deployment identity.'
    [[ "$BACKUP_DIR" == /* ]] || die 'BACKUP_DIR must be an absolute Linux path.'
    [[ "$BACKUP_INTERVAL_SECONDS" =~ ^[0-9]+$ && "$BACKUP_INTERVAL_SECONDS" -ge 300 ]] || die 'Backup interval must be at least 300 seconds.'
    for value in "$DB_VOLUME" "$FILES_VOLUME" "$RUNTIME_VOLUME"; do
        [[ "$value" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ ]] || die 'Invalid Docker volume name.'
    done
    [[ "$DB_VOLUME" != "$FILES_VOLUME" && "$DB_VOLUME" != "$RUNTIME_VOLUME" && "$FILES_VOLUME" != "$RUNTIME_VOLUME" ]] || die 'Data volumes must be distinct.'
}
compose() { docker compose --project-name filevault --env-file "$FILEVAULT_ENV_FILE" -f "$PROJECT_ROOT/docker-compose.yml" "$@"; }
check_volume() {
    local volume="$1" identity
    docker volume inspect "$volume" >/dev/null 2>&1 || die "Required volume $volume is missing. Restore data; do not initialize an empty replacement."
    identity="$(docker volume inspect --format '{{index .Labels "com.filevault.deployment"}}' "$volume")"
    [[ "$identity" == "$DEPLOYMENT_ID" ]] || die "Volume $volume does not match this deployment. Restore the matching env/volumes."
}
create_volume() { docker volume create --label "com.filevault.deployment=$DEPLOYMENT_ID" "$1" >/dev/null; }
