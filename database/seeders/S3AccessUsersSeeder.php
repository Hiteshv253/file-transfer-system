<?php

namespace Database\Seeders;

use App\Models\User;
use Common\Auth\Permissions\Permission;
use Common\Auth\Permissions\Traits\SyncsPermissions;
use Common\Database\Seeders\PermissionTableSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class S3AccessUsersSeeder extends Seeder
{
    use SyncsPermissions;

    private const SUPER_ADMIN_EMAIL = 'tmsoftwareinformation@gmail.com';
    private const LIMITED_USER_EMAIL = 'info@massiveorder.com';
    private const MANAGED_PASSWORD = 'Password@1234';

    public function run(): void
    {
        app(PermissionTableSeeder::class)->run();

        $superAdmin = $this->upsertManagedUser(
            self::SUPER_ADMIN_EMAIL,
            'TM Software Information',
            'admin',
        );
        $limitedUser = $this->upsertManagedUser(
            self::LIMITED_USER_EMAIL,
            'Massive Order User',
            'user',
        );

        // Keep these managed accounts focused on S3/Drive access only.
        $superAdmin->roles()->sync([]);
        $limitedUser->roles()->sync([]);

        $this->syncPermissions(
            $superAdmin,
            $this->resolveUserPermissions(['admin', 'admin.access']),
        );
        $this->syncPermissions(
            $limitedUser,
            $this->resolveUserPermissions(['files.create']),
        );

        $this->syncBedriveAccess($superAdmin, $limitedUser);
    }

    private function upsertManagedUser(
        string $email,
        string $name,
        string $type,
    ): User {
        $user = User::firstOrNew(['email' => $email]);

        $user->name = $name;
        $user->type = $type;
        $user->email_verified_at ??= now();
        $user->username ??= Str::before($email, '@');
        $user->language ??= $this->resolveLanguage();
        $user->timezone ??= 'UTC';
        $user->password = Hash::make(self::MANAGED_PASSWORD);

        $user->save();

        return $user->fresh(['roles', 'permissions']);
    }

    private function syncBedriveAccess(User $superAdmin, User $limitedUser): void
    {
        DB::table('file_entries')
            ->where('upload_type', 'bedrive')
            ->where('public', false)
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($entries) use ($superAdmin, $limitedUser) {
                foreach ($entries as $entry) {
                    $this->upsertAccessRecord(
                        $superAdmin->id,
                        (int) $entry->id,
                        [
                            'view' => true,
                            'download' => true,
                            'edit' => true,
                            'delete' => true,
                        ],
                    );
                    $this->upsertAccessRecord(
                        $limitedUser->id,
                        (int) $entry->id,
                        [
                            'view' => true,
                            'download' => true,
                        ],
                    );
                }
            });
    }

    private function upsertAccessRecord(
        int $userId,
        int $entryId,
        array $permissions,
    ): void {
        DB::table('file_entry_models')->updateOrInsert(
            [
                'model_id' => $userId,
                'model_type' => User::MODEL_TYPE,
                'file_entry_id' => $entryId,
                'relation_type' => 'access',
            ],
            [
                'owner' => false,
                'permissions' => json_encode($permissions),
                'origin' => 's3-access-seeder',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function resolveLanguage(): string
    {
        $locale = (string) config('app.locale', 'en');
        $normalized = strtolower(trim($locale));

        return match ($normalized) {
            'english' => 'en',
            'hindi' => 'hi',
            default => Str::substr($normalized ?: 'en', 0, 6),
        };
    }

    private function resolveUserPermissions(array $names): Collection
    {
        return Permission::query()
            ->where('type', 'users')
            ->whereIn('name', $names)
            ->get()
            ->values();
    }
}
