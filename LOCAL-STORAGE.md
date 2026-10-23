# Local file storage

For the Docker stack, persistent volumes, automatic backups, recovery and local-data
migration, see [DOCKER.md](DOCKER.md). Run `bash start.sh` on a Docker-enabled Linux/WSL host.

Run `php artisan serve --host=127.0.0.1 --port=8015` and open
http://127.0.0.1:8015/files. MySQL must be running; this workspace uses port 3306.

Create a super admin with `php artisan local-storage:admin admin@example.com`.
The command generates a password and never changes an existing account.
The super admin can create up to five regular accounts in the dashboard.

Files are private, outside the public directory, at
`storage/app/server-storage/YYYY/MM/YYYY-MM-DD/<unique-id>`.
The original filename is retained in the database and used for downloads.
Dates use Asia/Kolkata. There is no scheduled expiration.

Users can upload, download and preview PDFs belonging to them or shared with
them during upload. Only an owner or super admin can request deletion.
The upload panel accepts multiple files through drag-and-drop or the file
picker, including PDF, images, audio, video and other file types. It shows
individual and overall transfer progress, runs up to three requests at once,
and allows failed uploads to be retried without resending successful files.
Sharing choices apply to the whole selected batch. Each file may be up to 100 MB.
Pending and rejected requests leave the file available. Approval removes the
physical file and soft deletes its document row, retaining metadata and review
history. Super admins can view all files, review requests and create accounts.

The local portal is enabled by default (`LOCAL_FILE_PORTAL=true`). It replaces
the legacy UI and blocks its APIs, including public registration, direct
deletion and cloud storage settings. Existing cloud files and credentials
are not migrated or erased. New uploads never use S3. Set
`LOCAL_FILE_PORTAL=false` and clear config cache to restore the legacy UI.

Back up both the database and `storage/app/server-storage` to retain files and
their access/audit records. Uploads allow 100 MB maximum, also subject to PHP
and web server limits. Use HTTPS when exposing the application beyond localhost.

Run `php tests/local-storage-smoke.php` for transactional integration checks
covering sharing, PDF preview, access control, review decisions, audit records,
account creation and quota enforcement. Test rows are rolled back and test
files are removed.

Run `node --test tests/local-file-upload.test.cjs` to check the upload queue,
progress, concurrency, drag-and-drop selection, size limits and selective retry.

## Daily volume and reconciliation

The library supports 50/100/200 files per page, with date, uploader (admin),
file type and name filters. Cursor pagination and an upload snapshot prevent
new uploads from shifting the existing pages. Use Refresh for new uploads to
include arrivals while browsing. A daily user register counts active files;
the filtered inventory CSV includes every matching file, its ID, original name,
byte count, uploader and SHA-256. Files are never overwritten by original name.

Every new browser upload has a stable UUID receipt. Retries for the same owner
and receipt return the same document ID instead of creating a second record.
File size and SHA-256 are checked against the stored bytes before the upload
is confirmed. Temporary connection/server failures retry twice automatically.
The batch report includes every selected file, including rejected and failed
entries. Batch metadata and receipt IDs are saved in this browser for the
signed-in user. After refresh, server receipts are reconciled; pending original
files must be reselected because browser storage does not retain their contents.
Clearing browser data removes this recovery metadata. Successful upload receipts
and files remain in the server database and storage.

Uploads refresh the library and daily totals when finished. The selected filters
still apply; View uploaded files opens the upload date with the other filters
cleared. Each visible row is checked for disk existence and size so a missing
or truncated file remains visible with a storage issue badge.

Run `php artisan local-storage:verify --hashes` for a read-only storage audit.
It reports missing/corrupted files and returns a nonzero exit code if any are
found. Older files uploaded before receipt tracking can be checked by size but
have no historical checksum. This command does not schedule itself or create
backups. Retain independent, regular backups of both the database and private
storage; checksums and receipts cannot recover data from a failed server disk.

Run `php tests/local-storage-volume.php` for the 5 users × 200 files scenario:
1,000 real small-file uploads with identical original names, retried receipts,
disk/hash checks, all-page traversal during a new upload, user isolation,
filters, daily counts and full CSV export. Test records and files are cleaned
up afterward. This tests correctness with small files, not large-media throughput.

## Responsive interface

On phones, the library, deletion requests and daily register display as cards
with visible field labels. Larger screens retain tables. The layout includes
scrollable date folders, wrapping filenames, touch-sized actions, mobile-safe
form font sizes, and sharing checkboxes that work without a Ctrl/Cmd modifier.

The headless Chrome check covers login and dashboard widths from 320 to 1440 px,
including long names, a selected upload queue and pending approval controls:

```powershell
$env:FILEVAULT_TEST_PASSWORD = 'your-admin-password'
node tests/local-storage-responsive.cjs
```

It uses a separate Chrome profile, exercises local form state, and does not
upload files or modify server records. Screenshots are saved under
`storage/app/responsive-qa`. This is browser viewport emulation; it does not
replace testing on physical iOS/Android devices.

The workspace opens with a compact summary and View files, Upload and Delete
requests tabs; administrators also have a Users tab. Tab switches preserve the
upload queue and transfers. Each tab has a URL (`?tab=files`, `?tab=upload`,
`?tab=requests`, `?tab=users`) and supports keyboard arrows and browser history.
Filters, date folders, daily counts and completed batch details are collapsible.
File/request lists scroll within their panels to keep the overall page compact.
