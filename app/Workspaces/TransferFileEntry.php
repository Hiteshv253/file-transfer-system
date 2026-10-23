<?php

namespace App\Workspaces;

use App\Models\FileEntry;
use App\Models\User;
use DB;
use Illuminate\Database\Query\Builder;

class TransferFileEntry
{
    public function execute(
        int $workspaceId,
        int $newOwner,
        int $oldOwner,
    ): void {
        $oldOwnerFileIds = DB::table('file_entries')
            ->where('workspace_id', $workspaceId)
            ->where('owner_id', $oldOwner)
            ->pluck('id');

        if (!$oldOwnerFileIds->isEmpty()) {
            // make sure files are not shared with new owner, as that will cause duplicate errors
            DB::table('file_entry_models')
                ->where('model_type', User::MODEL_TYPE)
                ->whereIn('file_entry_id', $oldOwnerFileIds)
                ->where('model_id', $newOwner)
                ->delete();

            DB::table('file_entry_models')
                ->where('model_type', User::MODEL_TYPE)
                ->whereIn('file_entry_id', $oldOwnerFileIds)
                ->update(['model_id' => $newOwner, 'owner' => true]);
        }

        FileEntry::where('workspace_id', $workspaceId)
            ->where('owner_id', $oldOwner)
            ->update(['owner_id' => $newOwner]);
    }
}
