#!/usr/bin/env bash
set -Eeuo pipefail
[[ ! -e /tmp/filevault-backup-failed ]] || exit 1
now="$(date +%s)"
as_owner() { setpriv --reuid="${BACKUP_UID:-1000}" --regid="${BACKUP_GID:-1000}" --clear-groups "$@"; }
if as_owner test -f /backups/.last-success; then
    age=$((now - $(as_owner stat -c %Y /backups/.last-success)))
    (( age < ${BACKUP_INTERVAL_SECONDS:-86400} * 2 + 3600 ))
else
    (( now - $(cat /tmp/filevault-backup-started) < 3600 ))
fi
