<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'View LTRO Management', 'code' => 'ltro.view', 'description' => 'View LTRO monitoring and reports.'],
            ['name' => 'Manage LTRO Management', 'code' => 'ltro.manage', 'description' => 'Create, edit, import, and delete LTRO data.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [...$permission, 'module' => 'LTRO Management', 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $administratorRoleId = DB::table('roles')->where('code', 'system-admin')->value('id');

        if ($administratorRoleId) {
            foreach (DB::table('permissions')->whereIn('code', ['ltro.view', 'ltro.manage'])->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $administratorRoleId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', ['ltro.view', 'ltro.manage'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
