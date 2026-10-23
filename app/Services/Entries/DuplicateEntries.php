<?php

namespace App\Services\Entries;

use Common\Files\Actions\FileUploadValidator;
use Common\Files\Actions\GetUserSpaceUsage;
use Common\Files\Events\FileEntryCreated;
use Common\Files\FileEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DuplicateEntries
{
    public function __construct(
        protected array $entryIds,
        protected ?int $destinationId = null,
        protected ?int $ownerId = null,
    ) {}

    public function execute()
    {
        $setPermissions = app(SetPermissionsOnEntry::class);

        $totalBytes = FileEntry::whereIn('id', $this->entryIds)->sum(
            'file_size',
        );
        $usage = new GetUserSpaceUsage(uploadType: 'bedrive');
        if (!$usage->hasEnoughSpaceToUpload($totalBytes)) {
            throw ValidationException::withMessages([
                'storage' => FileUploadValidator::notEnoughSpaceMessage(),
            ]);
        }

        $copies = $this->copyEntries($this->entryIds, $this->destinationId);

        return $copies->map(
            fn(FileEntry $entry) => $setPermissions->execute($entry),
        );
    }

    private function copyEntries(
        array|Collection $entryIds,
        ?int $parentId = null,
    ) {
        $copies = collect();

        foreach (
            FileEntry::with('owner')->whereIn('id', $entryIds)->cursor()
            as $entry
        ) {
            if ($entry->type === 'folder') {
                $copies[] = $this->copyFolderEntry($entry, $parentId);
            } else {
                $copies[] = $this->copyFileEntry($entry, $parentId);
            }
        }

        return $copies;
    }

    private function copyFileEntry(
        FileEntry $original,
        ?int $parentId = null,
    ): FileEntry {
        $copy = $this->copyModel($original, $parentId);
        $this->copyFile($original, $copy);

        event(new FileEntryCreated($copy));

        return $copy;
    }

    private function copyFolderEntry(FileEntry $original, ?int $parentId = null)
    {
        $copy = $this->copyModel($original, $parentId);
        $this->copyChildEntries($copy, $original);

        return $copy;
    }

    private function copyChildEntries(FileEntry $copy, FileEntry $original)
    {
        $entryIds = FileEntry::where('parent_id', $original->id)->pluck('id');

        if (!$entryIds->isEmpty()) {
            $this->copyEntries($entryIds, $copy->id);
        }
    }

    private function copyModel(FileEntry $original, ?int $parentId = null)
    {
        $newName = $original->name;
        $newOwnerId = $this->getCopyOwnerId();
        $copyingIntoSameDrive = $newOwnerId === $original->owner_id;

        // if we are copying into same folder, add " - Copy" to the end of copies names
        if ($parentId === $original->parent_id && $copyingIntoSameDrive) {
            $newName = "$original->name - " . __('Copy');
        }

        $copy = $original->replicate();
        $copy->name = $newName;
        $copy->path = null;
        $copy->parent_id = $parentId;
        $copy->owner_id = $newOwnerId;
        $copy->disk_prefix = app(ResolveStorageFolderPath::class)->execute(
            $parentId,
        );

        if ($original->type !== 'folder') {
            $copy->file_name = app(ResolveUniqueStorageName::class)->execute(
                $original->file_name,
                $copy->disk_prefix,
                $original->upload_type,
                $original->backend_id,
            );
        }

        // folder file size will be updated by "FolderTotalSizeSubscriber"
        // so no need to set it here, otherwise it will be doubled
        if ($original->type === 'folder') {
            $copy->file_size = 0;
        }
        $copy->save();

        $copy->generatePath();

        // set owner
        $copy->users()->attach($newOwnerId, ['owner' => true]);

        $copy->load('users');

        return $copy;
    }

    private function copyFile(FileEntry $original, FileEntry $copy)
    {
        $original->getDisk()->copy(
            $original->getStoragePath(),
            $copy->getStoragePath(),
        );

        if ($original->thumbnail) {
            $original->getDisk()->copy(
                $original->getStoragePath(true),
                $copy->getStoragePath(true),
            );
        }
    }

    private function getCopyOwnerId(): int
    {
        return $this->ownerId ?? Auth::user()->id;
    }
}
