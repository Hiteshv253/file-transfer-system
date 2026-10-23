#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)/docker/lib.sh"
require_docker; load_config
compose stop --timeout 300 backup app db
echo 'Containers stopped. Database, uploads, deployment config and backups have been retained.'
