<?php

namespace Common\Files\Actions\Deletion;

use Common\Files\Events\FileEntriesDeleted;
use Common\Files\FileEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermanentlyDeleteEntries extends SoftDeleteEntries
{
    /**
     * Permanently delete file entries, related records and files from disk.
     */
    protected function delete(Collection|array $entries): void
    {
        $this->chunkChildEntries($entries, function ($chunk) {
            $entryPayload = $chunk->values()->all();
            $this->deleteEntries($chunk);
            $this->deleteFiles($chunk);
            event(
                new FileEntriesDeleted(
                    $chunk->pluck('id')->toArray(),
                    true,
                    $entryPayload,
                ),
            );
        });
    }

    /**
     * Delete file entries from database.
     */
    private function deleteEntries(Collection $entries): void
    {
        $entryIds = $entries->pluck('id');

        // detach users
        DB::table('file_entry_models')
            ->whereIn('file_entry_id', $entryIds)
            ->delete();

        // detach tags
        DB::table('taggables')
            ->where('taggable_type', FileEntry::MODEL_TYPE)
            ->whereIn('taggable_id', $entryIds)
            ->delete();

        FileEntry::query()
            ->whereIn('id', $entries->pluck('id'))
            ->forceDelete();
    }

    /**
     * Delete files from disk.
     */
    private function deleteFiles(Collection $entries): void
    {
        $entries
            ->filter(function (FileEntry $entry) {
                return $entry->type !== 'folder';
            })
            ->each(function (FileEntry $entry) {
                if (
                    !$entry->public &&
                    !$entry->disk_prefix &&
                    !str_contains($entry->file_name, '.')
                ) {
                    $deleted = $entry->getDisk()->deleteDirectory(
                        $entry->file_name,
                    );

                    if ($deleted === false) {
                        Log::warning(
                            'Permanent delete did not remove storage directory.',
                            [
                                'entry_id' => $entry->id,
                                'path' => $entry->file_name,
                                'backend_id' => $entry->backend_id,
                            ],
                        );
                    }
                } else {
                    $deleted = $entry->getDisk()->delete(
                        $entry->getStoragePath(),
                    );
                    if ($deleted === false) {
                        Log::warning(
                            'Permanent delete did not remove storage file.',
                            [
                                'entry_id' => $entry->id,
                                'path' => $entry->getStoragePath(),
                                'backend_id' => $entry->backend_id,
                            ],
                        );
                    }

                    if ($entry->thumbnail) {
                        $thumbDeleted = $entry->getDisk()->delete(
                            $entry->getStoragePath(true),
                        );
                        if ($thumbDeleted === false) {
                            Log::warning(
                                'Permanent delete did not remove thumbnail.',
                                [
                                    'entry_id' => $entry->id,
                                    'path' => $entry->getStoragePath(true),
                                    'backend_id' => $entry->backend_id,
                                ],
                            );
                        }
                    }
                }
            });
    }
}
