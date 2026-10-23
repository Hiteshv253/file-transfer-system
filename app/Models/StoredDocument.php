<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoredDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function folder() { return $this->belongsTo(StoredFolder::class, 'folder_id'); }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->portal_deleted_at || $user->banned_at) return $query->whereRaw('1 = 0');
        if ($user->portalCan('view_all')) return $query;
        return $query->where(function ($q) use ($user) {
            $q->whereRaw('1 = 0');
            if ($user->portalCan('view_own')) $q->orWhere('owner_id', $user->id);
            if ($user->portalCan('view_shared')) $q->orWhereIn('id', function ($shares) use ($user) {
                $shares->select('document_id')->from('stored_document_shares')->where('user_id', $user->id);
            });
        });
    }
}
