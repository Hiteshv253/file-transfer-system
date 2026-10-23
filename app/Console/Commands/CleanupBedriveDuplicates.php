<?php

namespace App\Console\Commands;

use App\Models\FileEntry;
use Common\Files\Uploads\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupBedriveDuplicates extends Command
{
    protected $signature = 'bedrive:cleanup-duplicates
        {--backendId= : Target backend id to normalize bedrive entries to}
        {--dry-run : Preview only without changing database}';

    protected $description = 'Normalize bedrive backend ids and merge duplicate file entry rows while preserving children and access links.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $targetBackendId = $this->resolveTargetBackendId();

        if (!$targetBackendId) {
            $this->error('Could not resolve target bedrive backend id.');
            return self::FAILURE;
        }

        $rowsToNormalize = FileEntry::withTrashed()
            ->where('upload_type', 'bedrive')
            ->where('backend_id', '!=', $targetBackendId)
            ->count();

        if ($rowsToNormalize > 0) {
            $this->line("Normalizing {$rowsToNormalize} bedrive rows to backend: {$targetBackendId}");
            if (!$dryRun) {
                DB::table('file_entries')
                    ->where('upload_type', 'bedrive')
                    ->where('backend_id', '!=', $targetBackendId)
                    ->update(['backend_id' => $targetBackendId]);
            }
        }

        $groups = FileEntry::withTrashed()
            ->selectRaw('MIN(id) as keep_id, parent_id, type, name, file_name, disk_prefix, COUNT(*) as duplicate_count')
            ->where('upload_type', 'bedrive')
            ->where('backend_id', $targetBackendId)
            ->groupBy('parent_id', 'type', 'name', 'file_name', 'disk_prefix')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('keep_id')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate bedrive entries found.');
            return self::SUCCESS;
        }

        $mergedGroups = 0;
        $deletedRows = 0;

        foreach ($groups as $group) {
            $duplicates = FileEntry::withTrashed()
                ->where('upload_type', 'bedrive')
                ->where('backend_id', $targetBackendId)
                ->where('parent_id', $group->parent_id)
                ->where('type', $group->type)
                ->where('name', $group->name)
                ->where('file_name', $group->file_name)
                ->when(
                    $group->disk_prefix,
                    fn($query) => $query->where('disk_prefix', $group->disk_prefix),
                    fn($query) => $query->whereNull('disk_prefix'),
                )
                ->orderBy('id')
                ->get();

            $keep = $duplicates->shift();
            if (!$keep || $duplicates->isEmpty()) {
                continue;
            }

            $duplicateIds = $duplicates->pluck('id')->all();
            $mergedGroups++;
            $deletedRows += count($duplicateIds);

            $this->line(
                sprintf(
                    'Merging %d duplicates into entry #%d (%s)',
                    count($duplicateIds),
                    $keep->id,
                    $keep->name
                )
            );

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keep, $duplicateIds, $targetBackendId) {
                DB::table('file_entries')
                    ->whereIn('parent_id', $duplicateIds)
                    ->update([
                        'parent_id' => $keep->id,
                        'backend_id' => $targetBackendId,
                    ]);

                foreach ($duplicateIds as $duplicateId) {
                    $links = DB::table('file_entry_models')
                        ->where('file_entry_id', $duplicateId)
                        ->get();

                    foreach ($links as $link) {
                        DB::table('file_entry_models')->updateOrInsert(
                            [
                                'model_id' => $link->model_id,
                                'model_type' => $link->model_type,
                                'file_entry_id' => $keep->id,
                                'relation_type' => $link->relation_type ?? 'access',
                            ],
                            [
                                'owner' => $link->owner,
                                'permissions' => $link->permissions,
                                'origin' => $link->origin ?? 'cleanup',
                                'updated_at' => now(),
                                'created_at' => $link->created_at ?? now(),
                            ],
                        );
                    }
                }

                DB::table('file_entry_models')
                    ->whereIn('file_entry_id', $duplicateIds)
                    ->delete();

                DB::table('file_entries')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            });
        }

        $this->newLine();
        $this->info("Target backend: {$targetBackendId}");
        $this->info("Duplicate groups merged: {$mergedGroups}");
        $this->info("Duplicate rows removed: {$deletedRows}");

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
