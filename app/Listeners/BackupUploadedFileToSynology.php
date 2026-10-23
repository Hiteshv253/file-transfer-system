<?php

namespace App\Listeners;

use App\Services\Storage\SynologyBackupService;
use Common\Files\Events\FileUploaded;

class BackupUploadedFileToSynology
{
    public function __construct(
        private readonly SynologyBackupService $backup,
    ) {
    }

    public function handle(FileUploaded $event): void
    {
        $this->backup->mirror($event->fileEntry);
    }
}
