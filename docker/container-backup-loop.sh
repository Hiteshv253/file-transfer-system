#!/usr/bin/env bash
set -Eeuo pipefail
date +%s > /tmp/filevault-backup-started
while true; do
    if /usr/local/bin/filevault-backup; then
        [[ ! -e /tmp/filevault-backup-failed ]] || unlink /tmp/filevault-backup-failed
        sleep "${BACKUP_INTERVAL_SECONDS:-86400}"
    else
        date +%s > /tmp/filevault-backup-failed
        echo 'AUTOMATIC BACKUP FAILED. Previous backups and original volumes remain intact; retrying in 5 minutes.' >&2
        sleep 300
    fi
done
