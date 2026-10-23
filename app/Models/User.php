<?php

namespace App\Models;

use Common\Auth\BaseUser;
use Common\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends BaseUser
{
    use HasApiTokens;

    protected bool $gravatarEnabled = false;

    public const PORTAL_PERMISSIONS = [
        'view_own' => 'View own files',
        'view_shared' => 'View shared files',
        'view_all' => 'View all team files',
        'upload' => 'Upload files',
        'preview' => 'Preview files',
        'download' => 'Download files',
        'request_delete' => 'Request file deletion',
        'delete' => 'Delete accessible files directly',
    ];

    public const PORTAL_DEFAULTS = ['view_own', 'view_shared', 'upload', 'preview', 'download', 'request_delete'];

    public function portalPermissions(): array
    {
        return $this->portal_permissions === null ? self::PORTAL_DEFAULTS : (json_decode($this->portal_permissions, true) ?: []);
    }

    public function portalCan(string $permission): bool
    {
        if ($this->portal_deleted_at || $this->banned_at) return false;
        return $this->hasPermission('admin') || in_array($permission, $this->portalPermissions(), true);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function routeNotificationForFcm(): string|array|null
    {
        return $this->fcmTokens()->get()->pluck('token')->toArray();
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function loadFcmToken(): ?string
    {
        if ($this->currentAccessToken()) {
            $token =
                $this->fcmTokens()
                    ->where('device_id', $this->currentAccessToken()->name)
                    ->first()->token ?? null;
            $this['fcm_token'] = $token;
            return $token;
        }
        return null;
    }
}
