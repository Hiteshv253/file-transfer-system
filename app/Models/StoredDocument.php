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

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->hasPermission('admin') ? $query : $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)->orWhereIn('id', function ($shares) use ($user) {
                $shares->select('document_id')->from('stored_document_shares')->where('user_id', $user->id);
            });
        });
    }
}
