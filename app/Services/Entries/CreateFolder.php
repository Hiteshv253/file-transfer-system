<?php

namespace App\Services\Entries;

use App\Models\Folder;
use Common\Files\Uploads\Uploads;
use Common\Workspaces\ActiveWorkspace;
use Illuminate\Support\Arr;

class CreateFolder
{
    public function __construct(private Folder $folder) {}

    public function execute(array $data): Folder
    {
        $ownerId = $data['ownerId'];
        $parentId = Arr::get($data, 'parentId');
        $folderName = $data['name'];
        $uploadType = Uploads::type('bedrive');
        $backendId = $uploadType?->backendIds[0] ?? null;

        $exists = $this->folder
            ->where('parent_id', $parentId)
            ->where('workspace_id', app(ActiveWorkspace::class)->id)
            ->where('name', $data['name'])
            ->where('type', 'folder')
            ->where('owner_id', $ownerId)
            ->first();

        if (!is_null($exists)) {
            throw new FolderExistsException();
        }

        $folder = $this->folder->create([
            'name' => $folderName,
            'file_name' => $folderName,
            'type' => 'folder',
            'parent_id' => $parentId,
            'owner_id' => $ownerId,
            'workspace_id' => app(ActiveWorkspace::class)->id ?? 0,
            'public' => false,
            'upload_type' => $uploadType?->name,
            'backend_id' => $backendId,
        ]);

        $folder->generatePath();

        $folder->users()->attach($ownerId, ['owner' => true]);

        return $folder;
    }
}
