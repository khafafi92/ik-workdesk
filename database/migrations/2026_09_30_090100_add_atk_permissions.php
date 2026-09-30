<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'Request ATK', 'code' => 'atk.request', 'description' => 'Create requests, confirm received items, and record department ATK usage.'],
            ['name' => 'Manage ATK', 'code' => 'atk.manage', 'description' => 'Manage ATK items, warehouse stock, requests, and department stock.'],
            ['name' => 'View ATK Reports', 'code' => 'atk.report', 'description' => 'View ATK requirement, stock, and usage monitoring reports.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    ...$permission,
                    'module' => 'ATK',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $rolePermissions = [
            'requester' => ['atk.request'],
            'general-affairs' => ['atk.request', 'atk.manage', 'atk.report'],
            'system-admin' => ['atk.request', 'atk.manage', 'atk.report'],
        ];

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

            if (! $roleId) {
                continue;
            }

            foreach (DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', [
            'atk.request',
            'atk.manage',
            'atk.report',
        ])->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
