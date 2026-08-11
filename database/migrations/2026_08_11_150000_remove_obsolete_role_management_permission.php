<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $userManagerRoleId = DB::table('roles')
            ->where('code', 'user-manager')
            ->value('id');
        $manageRolesPermissionId = DB::table('permissions')
            ->where('code', 'roles.manage')
            ->value('id');

        if ($userManagerRoleId && $manageRolesPermissionId) {
            DB::table('permission_role')
                ->where('role_id', $userManagerRoleId)
                ->where('permission_id', $manageRolesPermissionId)
                ->delete();
        }
    }

    public function down(): void
    {
        $userManagerRoleId = DB::table('roles')
            ->where('code', 'user-manager')
            ->value('id');
        $manageRolesPermissionId = DB::table('permissions')
            ->where('code', 'roles.manage')
            ->value('id');

        if ($userManagerRoleId && $manageRolesPermissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $userManagerRoleId,
                'permission_id' => $manageRolesPermissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
