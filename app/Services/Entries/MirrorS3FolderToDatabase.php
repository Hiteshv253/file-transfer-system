<?php

namespace App\Services\Entries;

use App\Models\FileEntry;
use App\Models\User;
use Common\Files\Traits\GetsEntryTypeFromMime;
use Common\Files\Uploads\UploadBackend;
use Common\Files\Uploads\Uploads;
use Common\Workspaces\ActiveWorkspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use Symfony\Component\Mime\MimeTypes;

class MirrorS3FolderToDatabase
{
    use GetsEntryTypeFromMime;

    public function __construct(private ActiveWorkspace $activeWorkspace) {}

    public function execute(?FileEntry $folder = null): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $backend = $this->resolveBackend();
        if (!$backend) {
            return;
        }

        $prefix = app(ResolveStorageFolderPath::class)->execute($folder?->id);
        $disk = Uploads::disk('bedrive', $backend->id, throw: true);
        $items = collect($disk->listContents($prefix ?: '', false)->toArray());
        $expectedKeys = [];

        $items->each(function ($item) use (
            $folder,
            $user,
            $backend,
            &$expectedKeys,
        ) {
            if ($item instanceof DirectoryAttributes) {
                $entry = $this->upsertFolder($item, $folder, $user, $backend);
                $expectedKeys[] = $this->entryKey($entry);
            } elseif ($item instanceof FileAttributes) {
                if (str_starts_with(basename($item->path()), '.thumb-')) {
                    return;
                }
                $entry = $this->upsertFile($item, $folder, $user, $backend);
                $expectedKeys[] = $this->entryKey($entry);
            }
        });

        $this->pruneMissingChildren($folder, $backend, collect($expectedKeys));
    }

    private function upsertFolder(
        DirectoryAttributes $item,
        ?FileEntry $parent,
        User $user,
        UploadBackend $backend,
    ): FileEntry {
        $name = basename(rtrim($item->path(), '/'));

        $entry = $this->resolveSingleEntry(
            FileEntry::withTrashed()
                ->where('type', 'folder')
                ->where('name', $name)
                ->where('parent_id', $parent?->id)
                ->where('upload_type', 'bedrive')
                ->where('backend_id', $backend->id),
        );

        if (!$entry) {
            $entry = new FileEntry();
        }

        $entry->fill([
            'name' => $name,
            'file_name' => $name,
            'type' => 'folder',
            'parent_id' => $parent?->id,
            'workspace_id' => $this->activeWorkspace->id ?? 0,
            'upload_type' => 'bedrive',
            'backend_id' => $backend->id,
            'owner_id' => $entry->owner_id ?: $user->id,
            'public' => false,
            'deleted_at' => null,
        ]);
        $entry->save();
        $entry->generatePath();

        $this->attachUserAccess($user, $entry);

        return $entry->fresh();
    }

    private function upsertFile(
        FileAttributes $item,
        ?FileEntry $parent,
        User $user,
        UploadBackend $backend,
    ): FileEntry {
        $path = trim($item->path(), '/');
        $pathInfo = pathinfo($path);
        $name = $pathInfo['basename'] ?? $path;
        $diskPrefix = ($pathInfo['dirname'] ?? '.') === '.'
            ? null
            : $pathInfo['dirname'];
        $mime = $item->mimeType() ?: 'application/octet-stream';
        $extension = $pathInfo['extension'] ?? $this->guessExtension($mime);

        $entry = $this->resolveSingleEntry(
            FileEntry::withTrashed()
                ->where('type', '!=', 'folder')
                ->where('file_name', $name)
                ->where('parent_id', $parent?->id)
                ->where('upload_type', 'bedrive')
                ->where('backend_id', $backend->id)
                ->when(
                    $diskPrefix,
                    fn($query) => $query->where('disk_prefix', $diskPrefix),
                    fn($query) => $query->whereNull('disk_prefix'),
                ),
        );

        if (!$entry) {
            $entry = new FileEntry();
        }

        $entry->fill([
            'name' => $name,
            'file_name' => $name,
            'mime' => $mime,
            'file_size' => $item->fileSize() ?: 0,
            'parent_id' => $parent?->id,
            'type' => $this->getTypeFromMime($mime, $extension),
            'extension' => $extension,
            'public' => false,
            'workspace_id' => $this->activeWorkspace->id ?? 0,
            'upload_type' => 'bedrive',
            'backend_id' => $backend->id,
            'owner_id' => $entry->owner_id ?: $user->id,
            'disk_prefix' => $diskPrefix,
            'deleted_at' => null,
        ]);
        $entry->save();
        $entry->generatePath();

        $this->attachUserAccess($user, $entry);

        return $entry->fresh();
    }

    private function pruneMissingChildren(
        ?FileEntry $parent,
        UploadBackend $backend,
        Collection $expectedKeys,
    ): void {
        $seenKeys = [];

        FileEntry::query()
            ->where('parent_id', $parent?->id)
            ->where('upload_type', 'bedrive')
            ->where('backend_id', $backend->id)
            ->get()
            ->reject(function (FileEntry $entry) use ($expectedKeys, &$seenKeys) {
                $key = $this->entryKey($entry);

                if (!$expectedKeys->contains($key)) {
                    return false;
                }

                if (in_array($key, $seenKeys, true)) {
                    return false;
                }

                $seenKeys[] = $key;

                return true;
            })
            ->each
            ->delete();
    }

    private function attachUserAccess(User $user, FileEntry $entry): void
    {
        DB::table('file_entry_models')->updateOrInsert(
            [
                'model_id' => $user->id,
                'model_type' => User::MODEL_TYPE,
                'file_entry_id' => $entry->id,
                'relation_type' => 'access',
            ],
            [
                'owner' => false,
                'origin' => 's3-mirror',
                'permissions' => json_encode([
                    'view' => true,
                    'download' => true,
                    'edit' => true,
                    'delete' => true,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function resolveSingleEntry($query): ?FileEntry
    {
        $entries = $query->orderBy('id')->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $entry = $entries->shift();

        if ($entries->isNotEmpty()) {
            $entries->each->delete();
        }

        return $entry;
    }

    private function entryKey(FileEntry $entry): string
    {
        return $entry->type === 'folder'
            ? "folder:{$entry->name}"
            : 'file:' . trim(($entry->disk_prefix ? "{$entry->disk_prefix}/" : '') . $entry->file_name, '/');
    }

    private function resolveBackend(): ?UploadBackend
    {
        foreach (Uploads::getAllBackends('bedrive') as $backend) {
            if ($backend->isS3()) {
                return $backend;
            }
        }

        return null;
    }

    private function guessExtension(string $mime): ?string
    {
        return MimeTypes::getDefault()->getExtensions($mime)[0] ?? null;
    }
}
