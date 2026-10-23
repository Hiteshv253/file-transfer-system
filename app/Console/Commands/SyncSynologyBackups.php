<?php

namespace App\Console\Commands;

use App\Services\Storage\SynologyBackupService;
use Common\Files\FileEntry;
use Illuminate\Console\Command;

class SyncSynologyBackups extends Command
{
    protected $signature =
        'uploads:sync-synology-backup
        {--entry-id= : Mirror only one file entry}
        {--missing-only : Skip files already present in Synology backup}';

    protected $description =
        'Mirror existing uploaded files from primary storage to Synology backup.';

    public function handle(SynologyBackupService $backup): int
    {
        if (!$backup->enabled()) {
            $this->error(
                'Synology backup is disabled. Enable SYNOLOGY_BACKUP_ENABLED first.',
            );
            return self::FAILURE;
        }

        $query = FileEntry::query()
            ->where('type', '!=', 'folder')
            ->whereNotNull('upload_type');

        if ($entryId = $this->option('entry-id')) {
            $query->where('id', (int) $entryId);
        }

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($entries) use (
            $backup,
            &$count,
        ) {
            foreach ($entries as $entry) {
                if (
                    $this->option('missing-only') &&
                    $backup->hasFile($entry->getStoragePath())
                ) {
                    continue;
                }

                $backup->mirror($entry);
                $count++;
                $this->line("Mirrored entry #{$entry->id}: {$entry->name}");
            }
        });

        $this->info("Synology backup sync completed for {$count} entries.");

        return self::SUCCESS;
    }
}
