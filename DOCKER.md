# File Vault: Docker deployment and data recovery

The stack has Apache/PHP, MariaDB and an automatic backup worker. **Only the app
port is published: `8085` by default.** The database is reachable only inside the
Compose network. The existing XAMPP app, MySQL and port 8015 are not changed.

## Start everything

Requirements: Docker Engine + Compose v2 (with `up --wait` support), Bash,
OpenSSL, tar, gzip and coreutils. On Windows use Docker Desktop with Linux
containers and a WSL2 Linux terminal. Docker/virtualization installation requires
host setup; the script does not silently install or replace your Docker daemon.
The `common/` application source must be present before building.

From the project directory, for local use:

```bash
bash start.sh
```

Open **http://localhost:8085/files**. For a server or phone on your network, supply
the actual server IP/domain on the **first** run:

```bash
APP_PORT=8085 \
APP_URL=http://192.168.1.50:8085 \
BACKUP_DIR=/mnt/independent-backup/filevault \
bash start.sh
```

Mount the independent disk/NAS first and replace the example IP/path. Allow
8085 through your server firewall where appropriate. Default BACKUP_DIR is
`$HOME/filevault-backups`; it is not automatically an independent/off-server disk.

The script creates `.env.docker` with random app/database/admin secrets, creates
labelled external volumes, builds the image, waits for MariaDB, applies migrations,
creates the first super admin only if none exists, and starts the web app and
backup worker. Re-running the script retains the same secrets, file volumes and
passwords. Existing deployments get a backup before app migrations run.

Initial email: `admin@filevault.local`. Read the generated initial password locally:

```bash
grep '^BOOTSTRAP_ADMIN_PASSWORD=' .env.docker
```

The initial password applies to a new database only. Restored users keep their
previous passwords. Keep `.env.docker` private; it is ignored by Git and excluded
from the Docker build context. Change deployment settings in this file after the
first run; environment overrides do not regenerate an existing deployment.
For HTTPS set APP_URL to the HTTPS address and SESSION_SECURE_COOKIE to `true`
behind your TLS reverse proxy. Plain HTTP on a public server is not a secure login
transport.

## Where data lives

| Data | Default external volume | Container location |
| --- | --- | --- |
| MariaDB tables/users/audit/permissions | `filevault_database` | `/var/lib/mysql` |
| Original uploaded files | `filevault_uploads` | `/var/www/html/storage/app/server-storage` |
| Sessions, locks, cache and logs | `filevault_runtime` | `/var/www/html/storage` |
| Complete recovery snapshots | Host BACKUP_DIR bind mount | `/backups` in backup worker |

Files are written and fetched through the same uploads volume at
`YYYY/MM/YYYY-MM-DD/<unique-id>`. Upload success requires database commit, size
and SHA-256 validation and file `fsync`. Filenames cannot overwrite another file.
MariaDB uses InnoDB transaction flushing and doublewrite protection.

Volumes are external to Compose: container stop/removal/rebuild does not own
their lifecycle. Startup checks their deployment labels and refuses a missing
initialized volume instead of silently creating an empty replacement. The upload
volume also has an identity marker. The backup destination has its own marker;
a missing/swapped NAS mount causes an error rather than silently accepting a new
empty backup target.

**Do not remove/prune these volumes, discard `.env.docker`, or reinitialize an
existing database.** A Docker administrator can still delete volumes manually;
external volumes are not an access-control or immutable-storage feature.

```bash
bash stop.sh     # stop services; retain data and backups
bash start.sh    # resume/rebuild without resetting the database or files
```

## Automatic and manual backups

The worker takes its first snapshot after the app becomes healthy, then once
every 86,400 seconds. Change BACKUP_INTERVAL_SECONDS in `.env.docker` if needed
(minimum 300). This is an interval after each completed snapshot, not a fixed
wall-clock cron time. Restarting the backup worker takes another snapshot.

```bash
bash backup.sh
```

Each completed directory contains:

```text
YYYYMMDDTHHMMSSZ-process/
  database.sql.gz    # transaction-consistent application database
  files.tar         # private file volume, matching the database snapshot
  deployment.env    # APP_KEY, DB credentials, volume and recovery configuration
  manifest.json
  integrity.txt     # original-file audit results
  SHA256SUMS
```

The backup takes an exclusive shared-volume lock, audits active files, takes a
transactional database dump, archives the files, validates the archives and writes
checksums. Only then is `.incomplete-*` renamed into a completed snapshot.
**Uploads and deletion approvals briefly return a retry response while the lock
is held; downloads continue.** Pending/failed uploads remain in the browser batch
and can be retried with the same receipt. Full snapshots of very large video
libraries take longer and require a suitable backup window/storage budget.

No backup retention deletion is enabled. Failed snapshots never replace a good
snapshot. Monitor free space and archive/prune old snapshots deliberately after
verifying independent copies. Recovery snapshots contain user files, passwords
hashes and deployment secrets: restrict access and use encrypted backup media.

Check service health and errors:

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail=100 backup
docker compose --env-file .env.docker exec app php artisan local-storage:verify --hashes
```

BACKUP_DIR on the **same server disk** does not protect against that disk failing,
host loss, theft or ransomware. Put it on independent storage and keep an
off-server/offline copy with its secrets and checksum manifest. Recovery can only
recover through the last successful available snapshot; files uploaded afterward
are outside that recovery point. RAID, a volume, checksums and `fsync` alone cannot
promise zero loss under every hardware/server failure. Approval-based deletion
also remains intentional application behavior, not immutable retention.

## Restore a completed snapshot

On the current or a replacement Docker host, place the project and a trusted
completed snapshot on disk, then run:

```bash
bash restore.sh /mnt/independent-backup/filevault/20261023T020000Z-123
```

The script checks every expected checksum, gzip and archive path/type before
stopping services. It makes a safety backup of a running current deployment,
preserves its env file and **allocates three NEW volumes**. It imports SQL into
the empty database, restores files into the empty uploads volume and verifies
files against the restored database before starting the app. Old volumes are
never overwritten or deleted. If a restore fails, retain both old and new volumes
and inspect the error. Use the saved `.env.docker.pre-restore.*` with its matching
old volume names to return to the previous deployment; never mix a database and
uploads volume from different snapshots.

On a replacement host without `.env.docker`, APP_PORT, APP_URL and BACKUP_DIR may
be supplied to `restore.sh`; otherwise the port defaults to 8085 and the backup
directory defaults to the supplied snapshot's parent. Existing host deployment
settings are retained when restoring onto an already configured host.

## Move the existing XAMPP/local File Vault data

**First Docker startup creates a separate database; it does not automatically
read or copy your existing XAMPP database/files. The old data is left untouched.**
Do not use an empty Docker library as evidence that the source files disappeared.

1. Stop writes to the old app while creating a matched database export and copy
   of `storage/app/server-storage`. Keep its original `.env`/APP_KEY. Export the
   whole application database, including users, stored_documents, sharing,
   deletion history and migrations. A files-only copy cannot restore permissions
   or listings. Use an SQL export without `CREATE DATABASE` / `USE` statements
   selecting another database (the normal single-database dump format).
2. Run `bash start.sh` once to prepare the Docker stack/config.
3. From Linux/WSL, import the matched source export and uploads:

```bash
bash migrate-existing.sh \
  /path/to/old-database.sql \
  /path/to/old-project/storage/app/server-storage \
  /path/to/old-project/.env
```

The helper packages a recovery snapshot, retains the original APP_KEY and invokes
the same new-volume restore. It leaves the source SQL/files untouched. It imports
the local File Vault store; it does not download legacy S3 files into local storage.
Verify user login, the inventory count and downloads before retiring the old app.

## Validation performed in this workspace

Docker Engine/CLI is unavailable here, so **the image build and real multi-container
backup/restore have not been executed**. Before a production cutover, on a host
with Docker run the first-start, upload/download, container recreation and restore
drill, comparing inventory IDs/counts and file hashes.

`node --test tests/docker-deployment.test.cjs` checks Bash syntax, YAML structure,
the single exposed port, external volumes, secret retention, missing/mismatched
volume rejection and new-volume restore orchestration using a fake Docker CLI.
It does not simulate Docker itself. `php tests/local-storage-smoke.php` exercises
real local database/storage upload verification, `fsync`, permissions and the
backup write/deletion lock without changing production records.

References: [Docker external volumes](https://docs.docker.com/reference/compose-file/volumes/),
[Compose health-based startup](https://docs.docker.com/compose/how-tos/startup-order/),
[MariaDB dump options](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).
