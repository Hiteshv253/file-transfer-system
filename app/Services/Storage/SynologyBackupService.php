<?php

namespace App\Services\Storage;

use Common\Files\FileEntry;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SynologyBackupService
{
    private ?FilesystemAdapter $disk = null;

    public function enabled(): bool
    {
        return (bool) config('filesystems.synology_backup.enabled');
    }

    public function mirror(FileEntry $entry): void
    {
        if (!$this->enabled() || $entry->type === 'folder') {
            return;
        }

        $sourcePath = $entry->getStoragePath();
        $stream = $entry->getDisk()->readStream($sourcePath);

        if (!$stream) {
            Log::warning('Synology backup skipped: could not open source file.', [
                'entry_id' => $entry->id,
                'path' => $sourcePath,
            ]);
            return;
        }

        try {
            $disk = $this->disk();
            if ($disk->fileExists($sourcePath)) {
                $disk->delete($sourcePath);
            }

            $disk->writeStream($sourcePath, $stream, [
                'visibility' => config(
                    'filesystems.synology_backup.visibility',
                    'private',
                ),
                'mimetype' => $entry->mime,
            ]);
        } catch (Throwable $e) {
            Log::error('Synology backup write failed.', [
                'entry_id' => $entry->id,
                'path' => $sourcePath,
                'message' => $e->getMessage(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function deleteEntries(Collection|array $entries): void
    {
        if (!$this->enabled()) {
            return;
        }

        $disk = $this->disk();
        $retainThumbnails = (bool) config(
            'filesystems.synology_backup.retain_thumbnails',
            false,
        );

        collect($entries)
            ->filter(fn($entry) => $entry instanceof FileEntry)
            ->each(function (FileEntry $entry) use ($disk, $retainThumbnails) {
                if ($entry->type === 'folder') {
                    return;
                }

                $this->deletePath($disk, $entry->getStoragePath(), $entry->id);

                if ($entry->thumbnail && !$retainThumbnails) {
                    $this->deletePath(
                        $disk,
                        $entry->getStoragePath(true),
                        $entry->id,
                    );
                }
            });
    }

    public function hasFile(string $path): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->disk()->fileExists($path);
    }

    private function deletePath(
        FilesystemAdapter $disk,
        string $path,
        int $entryId,
    ): void {
        try {
            if ($disk->fileExists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            Log::error('Synology backup delete failed.', [
                'entry_id' => $entryId,
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function disk(): FilesystemAdapter
    {
        if ($this->disk) {
            return $this->disk;
        }

        $driver = config('filesystems.synology_backup.driver', 'local');
        $config = config('filesystems.synology_backup.config', []);

        $this->disk = Storage::build([
            'driver' => $driver,
            'throw' => true,
            'root' => config('filesystems.synology_backup.root'),
            'prefix' => config('filesystems.synology_backup.prefix') ?: null,
            'visibility' => config(
                'filesystems.synology_backup.visibility',
                'private',
            ),
            ...$config,
        ]);

        return $this->disk;
    }
}
