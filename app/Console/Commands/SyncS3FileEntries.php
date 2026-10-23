<?php

namespace App\Console\Commands;

use App\Models\FileEntry;
use App\Models\User;
use Carbon\Carbon;
use Common\Files\Traits\GetsEntryTypeFromMime;
use Common\Files\Uploads\Uploads;
use Illuminate\Console\Command;
use League\Flysystem\FileAttributes;
use Symfony\Component\Mime\MimeTypes;

class SyncS3FileEntries extends Command
{
    use GetsEntryTypeFromMime;

    protected $signature =
        'uploads:sync-s3-entries
        {--backendId= : Specific upload backend id}
        {--uploadType=bedrive : Upload type to sync into}
        {--userId= : Owner user id for imported entries}
        {--prefix= : Optional S3 prefix to scan}
        {--repair-missing : Re-upload project files to S3 when DB entry exists but remote object is missing}
        {--dry-run : Preview what would be imported without saving}';

    protected $description =
        'Import orphaned S3 objects into file_entries so they appear in the project.';

    public function handle(): int
    {
        $uploadType = Uploads::type((string) $this->option('uploadType'));
        if (!$uploadType) {
            $this->error('Upload type could not be resolved.');
            return self::FAILURE;
        }

        $backend = $this->resolveBackend($uploadType->name);
        if (!$backend) {
            $this->error('S3 backend could not be resolved for this upload type.');
            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (!$user) {
            $this->error('No user found to assign imported files to.');
            return self::FAILURE;
        }

        $disk = Uploads::disk($uploadType->name, $backend->id, throw: true);
        $prefix = trim((string) $this->option('prefix'), '/');
        $prefix = $prefix ? "$prefix/" : '';
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $created = 0;
        $skipped = 0;

        foreach ($disk->listContents($prefix, true) as $item) {
            if (!$item instanceof FileAttributes) {
                continue;
            }

            $scanned++;
            $path = $item->path();
            $entryData = $uploadType->public
                ? $this->mapPublicObject($path, $item)
                : $this->mapPrivateObject($path, $item);

            if (!$entryData) {
                $skipped++;
                continue;
            }

            $alreadyExists = FileEntry::query()
                ->where('backend_id', $backend->id)
                ->where('upload_type', $uploadType->name)
                ->where('file_name', $entryData['file_name'])
                ->when(
                    array_key_exists('disk_prefix', $entryData),
                    fn($query) => $query->where(
                        'disk_prefix',
                        $entryData['disk_prefix'],
                    ),
                )
                ->exists();

            if ($alreadyExists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $created++;
                $this->line("Would import: {$path}");
                continue;
            }

            $entry = new FileEntry([
                ...$entryData,
                'parent_id' => null,
                'public' => $uploadType->public,
                'workspace_id' => null,
                'upload_type' => $uploadType->name,
                'backend_id' => $backend->id,
                'owner_id' => $user->id,
            ]);

            if ($item->lastModified()) {
                $entry->created_at = Carbon::createFromTimestamp(
                    $item->lastModified(),
                );
                $entry->updated_at = $entry->created_at;
            }

            $entry->save();
            $entry->generatePath();

            $user->entries()->syncWithoutDetaching([
                $entry->id => ['owner' => 1],
            ]);

            $created++;
            $this->line("Imported: {$path}");
        }

        $this->newLine();
        $this->info("Scanned: {$scanned}");
        $this->info("Imported: {$created}");
        $this->info("Skipped: {$skipped}");

        if ($this->option('repair-missing')) {
            [$repaired, $missingLocal] = $this->repairMissingRemoteFiles(
                $uploadType->name,
                $backend->id,
                $dryRun,
            );

            $this->info("Repaired missing remote files: {$repaired}");
            $this->info("Missing local source files: {$missingLocal}");
        }

        return self::SUCCESS;
    }

    protected function resolveBackend(string $uploadType): mixed
    {
        if ($backendId = $this->option('backendId')) {
            return Uploads::backend((string) $backendId);
        }

        foreach (Uploads::getAllBackends($uploadType) as $backend) {
            if ($backend->isS3()) {
                return $backend;
            }
        }

        return null;
    }

    protected function resolveUser(): ?User
    {
        if ($userId = $this->option('userId')) {
            return User::find((int) $userId);
        }

        return User::query()->orderBy('id')->first();
    }

    protected function mapPrivateObject(
        string $path,
        FileAttributes $item,
    ): ?array {
        $trimmed = trim($path, '/');
        $pathInfo = pathinfo($trimmed);
        $fileName = $pathInfo['basename'] ?? null;

        if (!$fileName || str_starts_with($fileName, '.thumb-')) {
            return null;
        }

        $mime = $item->mimeType() ?: 'application/octet-stream';
        $extension = $this->guessExtension($mime);
        $diskPrefix = ($pathInfo['dirname'] ?? '.') === '.'
            ? null
            : $pathInfo['dirname'];

        return [
            'name' => $fileName,
            'file_name' => $fileName,
            'mime' => $mime,
            'file_size' => $item->fileSize() ?: 0,
            'type' => $this->getTypeFromMime($mime, $extension),
            'extension' => $extension,
            'disk_prefix' => $diskPrefix,
        ];
    }

    protected function mapPublicObject(
        string $path,
        FileAttributes $item,
    ): ?array {
        $trimmed = trim($path, '/');
        $pathInfo = pathinfo($trimmed);
        $fileName = $pathInfo['basename'] ?? null;

        if (!$fileName) {
            return null;
        }

        $mime = $item->mimeType() ?: 'application/octet-stream';
        $extension = $pathInfo['extension'] ?? $this->guessExtension($mime);
        $diskPrefix = ($pathInfo['dirname'] ?? '.') === '.'
            ? null
            : $pathInfo['dirname'];

        return [
            'name' => $fileName,
            'file_name' => $fileName,
            'mime' => $mime,
            'file_size' => $item->fileSize() ?: 0,
            'type' => $this->getTypeFromMime($mime, $extension),
            'extension' => $extension,
            'disk_prefix' => $diskPrefix,
        ];
    }

    protected function guessExtension(string $mime): ?string
    {
        return MimeTypes::getDefault()->getExtensions($mime)[0] ?? null;
    }

    protected function repairMissingRemoteFiles(
        string $uploadType,
        string $backendId,
        bool $dryRun = false,
    ): array {
        $disk = Uploads::disk($uploadType, $backendId, throw: true);
        $type = Uploads::type($uploadType);
        $repaired = 0;
        $missingLocal = 0;

        FileEntry::query()
            ->where('upload_type', $uploadType)
            ->where('backend_id', $backendId)
            ->orderBy('id')
            ->chunk(100, function ($entries) use (
                $disk,
                $type,
                $dryRun,
                &$repaired,
                &$missingLocal,
            ) {
                foreach ($entries as $entry) {
                    $remotePath = $entry->getStoragePath(false);

                    if ($disk->fileExists($remotePath)) {
                        continue;
                    }

                    $localPath = $type->public
                        ? public_path("storage/{$remotePath}")
                        : storage_path("app/uploads/{$remotePath}");

                    if (!file_exists($localPath)) {
                        $missingLocal++;
                        continue;
                    }

                    if ($dryRun) {
                        $repaired++;
                        $this->line("Would repair: {$remotePath}");
                        continue;
                    }

                    $stream = fopen($localPath, 'r');
                    if (!$stream) {
                        $missingLocal++;
                        continue;
                    }

                    try {
                        $disk->writeStream($remotePath, $stream, [
                            'visibility' => $type->visibility,
                            'mimetype' => $entry->mime,
                        ]);
                        $repaired++;
                        $this->line("Repaired: {$remotePath}");
                    } finally {
                        fclose($stream);
                    }
                }
            });

        return [$repaired, $missingLocal];
    }
}
