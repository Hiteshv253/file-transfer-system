<?php

use Common\Core\Values\PermissionConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $workspaceRoles = DB::table('roles')->where('type', 'workspace')->get();
        $workspacePermissions = DB::table('permissions')
            ->where('type', 'workspace')
            ->get();
        $viewFilesPermission = $workspacePermissions->first(
            fn($p) => $p->name === 'files.view',
        );

        if (!$viewFilesPermission) {
            $allPermissions = (new PermissionConfig())->get();
            $viewFilesPermission = collect($allPermissions)->first(
                fn($p) => $p['name'] === 'files.view',
            );
            DB::table('permissions')->insert([
                'name' => $viewFilesPermission['name'],
                'type' => $viewFilesPermission['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $viewFilesPermission = DB::table('permissions')
                ->where('name', $viewFilesPermission['name'])
                ->where('type', $viewFilesPermission['type'])
                ->first();
        }

        foreach ($workspaceRoles as $workspaceRole) {
            $currentPermissions = DB::table('permissionables')
                ->where('permissionable_id', $workspaceRole->id)
                ->where('permissionable_type', 'role')
                ->pluck('permission_id')
                ->toArray();

            if (!in_array($viewFilesPermission->id, $currentPermissions)) {
                DB::table('permissionables')->insert([
                    'permission_id' => $viewFilesPermission->id,
                    'permissionable_id' => $workspaceRole->id,
                    'permissionable_type' => 'role',
                ]);
            }
        }
    }
};
