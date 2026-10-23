#!/usr/bin/env bash
# Import a MATCHED, quiesced SQL export + existing local File Vault upload directory.
set -Eeuo pipefail
source "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)/docker/lib.sh"
require_docker; load_config
[[ $# -ge 2 && $# -le 3 ]] || die 'Usage: bash migrate-existing.sh database.sql[.gz] /path/server-storage [/path/original/.env]'
[[ -f "$1" && -d "$2" ]] || die 'SQL export or upload directory is missing.'
source_env="${3:-$PROJECT_ROOT/.env}"
[[ -f "$source_env" ]] || die 'Original .env is needed to retain the existing application encryption key.'
source_key="$(read_value "$source_env" APP_KEY)"
[[ -n "$source_key" ]] || die 'Original APP_KEY is missing.'
umask 0077
mkdir -p -- "$BACKUP_DIR"
bundle="$BACKUP_DIR/import-$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir "$bundle"
if [[ "$1" == *.gz ]]; then cp "$1" "$bundle/database.sql.gz"; else gzip -1 -c "$1" > "$bundle/database.sql.gz"; fi
gzip -t "$bundle/database.sql.gz"
tar -C "$2" -cf "$bundle/files.tar" .
cp "$FILEVAULT_ENV_FILE" "$bundle/deployment.env"
write_value "$bundle/deployment.env" APP_KEY "$source_key"
printf '{"format_version":1,"deployment_id":"%s","source":"existing-local-export"}\n' "$DEPLOYMENT_ID" > "$bundle/manifest.json"
printf 'Source export supplied by operator; complete file verification is required by restore.sh.\n' > "$bundle/integrity.txt"
(cd "$bundle" && sha256sum database.sql.gz files.tar deployment.env manifest.json integrity.txt > SHA256SUMS)
echo "Matched import bundle prepared at $bundle. Source SQL and files were not changed."
bash "$PROJECT_ROOT/restore.sh" "$bundle"
