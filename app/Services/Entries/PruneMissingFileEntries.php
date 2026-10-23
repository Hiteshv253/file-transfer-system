<?php

namespace App\Services\Entries;

use App\Models\FileEntry;
use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PruneMissingFileEntries
{
    public function execute(Collection $entries): bool
    {
        $staleEntryIds = $entries
            ->filter(
                fn($entry) => $entry instanceof FileEntry &&
                    $entry->type !== 'folder' &&
                    !$entry->trashed() &&
                    $this->entryIsMissingFromStorage($entry),
            )
            ->pluck('id')
            ->values();

        if ($staleEntryIds->isEmpty()) {
            return false;
        }

        FileEntry::whereIn('id', $staleEntryIds)->delete();

        Log::warning('Soft deleted stale file entries missing from storage.', [
            'entry_ids' => $staleEntryIds->all(),
        ]);

        return true;
    }

    protected function entryIsMissingFromStorage(FileEntry $entry): bool
    {
        try {
            return !$entry->getDisk()->exists($entry->getStoragePath());
        } catch (Throwable $e) {
            Log::warning('Soft deleted file entry with invalid upload disk.', [
                'entry_id' => $entry->id,
                'upload_type' => $entry->upload_type,
                'backend_id' => $entry->backend_id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
