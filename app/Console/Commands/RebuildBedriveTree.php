<?php

namespace App\Console\Commands;

use App\Models\FileEntry;
use Common\Files\Uploads\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildBedriveTree extends Command
{
    protected $signature = 'bedrive:rebuild-tree
        {--backendId= : Target backend id to normalize bedrive entries to}
        {--dry-run : Preview only without changing database}';

    protected $description = 'Rebuild bedrive folder hierarchy from disk_prefix and repair parent/path relations for S3-backed entries.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $backendId = $this->resolveTargetBackendId();

        if (!$backendId) {
            $this->error('Could not resolve target bedrive backend id.');
            return self::FAILURE;
        }

        $this->line("Target backend: {$backendId}");

        if (!$dryRun) {
            DB::table('file_entries')
                ->where('upload_type', 'bedrive')
                ->where('backend_id', '!=', $backendId)
                ->update(['backend_id' => $backendId]);
        }

        $createdFolders = 0;
        $updatedFiles = 0;
        $regeneratedPaths = 0;

        $files = FileEntry::query()
            ->where('upload_type', 'bedrive')
            ->where('backend_id', $backendId)
            ->where('type', '!=', 'folder')
            ->orderBy('id')
            ->get();

        foreach ($files as $file) {
            $segments = array_values(array_filter(explode('/', trim((string) $file->disk_prefix, '/'))));

            $parentId = null;
            foreach ($segments as $segment) {
                $folder = FileEntry::query()
                    ->where('upload_type', 'bedrive')
                    ->where('backend_id', $backendId)
                    ->where('type', 'folder')
                    ->where('name', $segment)
                    ->where('parent_id', $parentId)
                    ->orderBy('id')
                    ->first();

                if (!$folder) {
                    $createdFolders++;
                    if ($dryRun) {
                        $parentId = -1;
                        continue;
                    }

                    $folder = FileEntry::create([
                        'name' => $segment,
                        'file_name' => $segment,
                        'type' => 'folder',
                        'mime' => null,
                        'file_size' => 0,
                        'extension' => null,
                        'parent_id' => $parentId > 0 ? $parentId : null,
                        'workspace_id' => $file->workspace_id ?? 0,
                        'upload_type' => 'bedrive',
                        'backend_id' => $backendId,
                        'owner_id' => $file->owner_id,
                        'public' => false,
                        'disk_prefix' => null,
                    ]);
                    $folder->generatePath();
                    $regeneratedPaths++;
                }

                if ($folder && $folder->id > 0) {
                    $parentId = $folder->id;
                }
            }

            $desiredParentId = $parentId > 0 ? $parentId : null;
            if ($file->parent_id !== $desiredParentId) {
                $updatedFiles++;
                if (!$dryRun) {
                    $file->parent_id = $desiredParentId;
                    $file->save();
                    $file->generatePath();
                    $regeneratedPaths++;
                }
            }
        }

        // Regenerate all folder paths deepest-first so parent chains are consistent.
        $folders = FileEntry::query()
            ->where('upload_type', 'bedrive')
            ->where('backend_id', $backendId)
            ->where('type', 'folder')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        foreach ($folders as $folder) {
            if ($dryRun) {
                continue;
            }
            $folder->generatePath();
            $regeneratedPaths++;
        }

        $this->newLine();
        $this->info("Folders created: {$createdFolders}");
        $this->info("Files re-parented: {$updatedFiles}");
        $this->info("Paths regenerated: {$regeneratedPaths}");

        return self::SUCCESS;
    }

    private function resolveTargetBackendId(): ?string
    {
        if ($backendId = $this->option('backendId')) {
            return (string) $backendId;
        }

        foreach (Uploads::getAllBackends('bedrive') as $backend) {
            if ($backend->isS3()) {
                return $backend->id;
            }
        }

        return null;
    }
}
