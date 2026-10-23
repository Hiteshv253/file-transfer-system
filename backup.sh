#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)/docker/lib.sh"
require_docker; load_config
compose run --rm --no-deps --entrypoint /usr/local/bin/filevault-backup backup
