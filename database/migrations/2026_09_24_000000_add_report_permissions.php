<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['View Reports', 'report.view', 'View operational reports within the user\'s permitted department scope.'],
            ['Export Reports', 'report.export', 'Export operational reports within the user\'s permitted department scope.'],
        ] as [$name, $code, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'module' => 'Reports', 'description' => $description, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $roleId = DB::table('roles')->where('code', 'system-admin')->value('id');
        if ($roleId) {
            $permissionIds = DB::table('permissions')->whereIn('code', ['report.view', 'report.export'])->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', ['report.view', 'report.export'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', ['report.view', 'report.export'])->delete();
    }
};
