<?php

namespace App\Listeners;

use App\Services\Storage\SynologyBackupService;
use Common\Files\Events\FileEntriesDeleted;

class DeleteSynologyBackupForEntries
{
    public function __construct(
        private readonly SynologyBackupService $backup,
    ) {
    }

    public function handle(FileEntriesDeleted $event): void
    {
        if (!$event->permanently || empty($event->entries)) {
            return;
        }

        $this->backup->deleteEntries($event->entries);
    }
}
