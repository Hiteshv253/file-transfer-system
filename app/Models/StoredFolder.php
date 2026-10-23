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
