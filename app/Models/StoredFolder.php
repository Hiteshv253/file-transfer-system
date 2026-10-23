<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoredFolder extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function documents() { return $this->hasMany(StoredDocument::class, 'folder_id'); }

    public function scopeForUploadDate(Builder $query, User $user, string $date): Builder
    {
        $start = \Carbon\Carbon::parse($date, config('local-storage.timezone'))->startOfDay();
        $end = $start->copy()->addDay()->setTimezone(config('app.timezone'));
        $start->setTimezone(config('app.timezone'));
        return $query->where(function ($dated) use ($user, $date, $start, $end) {
            // Keep every ancestor of a visible file, including deeply nested folders.
            $dated->whereExists(function ($files) use ($user, $date) {
                $files->selectRaw('1')->from('stored_folders as dated_leaf')
                    ->join('stored_documents as dated_file', 'dated_file.folder_id', '=', 'dated_leaf.id')
                    ->whereNull('dated_leaf.deleted_at')
                    ->whereRaw('dated_leaf.tree_path LIKE CONCAT(stored_folders.tree_path, ?)', ['%'])
                    ->whereIn('dated_file.id', StoredDocument::accessibleTo($user)->where('folder_date', $date)->select('id'));
            })->orWhere(function ($empty) use ($start, $end) {
                // A newly created empty directory must also remain browsable on its day.
                $empty->where('stored_folders.created_at', '>=', $start)->where('stored_folders.created_at', '<', $end)->whereNotExists(function ($files) {
                    $files->selectRaw('1')->from('stored_folders as occupied_leaf')
                        ->join('stored_documents as occupied_file', 'occupied_file.folder_id', '=', 'occupied_leaf.id')
                        ->whereNull('occupied_leaf.deleted_at')->whereNull('occupied_file.deleted_at')
                        ->whereRaw('occupied_leaf.tree_path LIKE CONCAT(stored_folders.tree_path, ?)', ['%']);
                });
            });
        });
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->banned_at || $user->portal_deleted_at) return $query->whereRaw('1 = 0');
        if ($user->portalCan('view_all')) return $query;
        return $query->where(function ($allowed) use ($user) {
            $allowed->whereRaw('1 = 0');
            if ($user->portalCan('view_own')) $allowed->orWhere('stored_folders.owner_id', $user->id);
            if ($user->portalCan('view_shared')) $allowed->orWhereExists(function ($shared) use ($user) {
                $shared->selectRaw('1')->from('stored_folders as leaf')
                    ->join('stored_documents as d', 'd.folder_id', '=', 'leaf.id')
                    ->join('stored_document_shares as s', 's.document_id', '=', 'd.id')
                    ->where('s.user_id', $user->id)->whereNull('d.deleted_at')->whereNull('leaf.deleted_at')
                    ->whereRaw('leaf.tree_path LIKE CONCAT(stored_folders.tree_path, ?)', ['%']);
            });
        });
    }
}
