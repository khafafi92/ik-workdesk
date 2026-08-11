<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('code', 'department-reviewer')
            ->update([
                'name' => 'Supervisor',
                'code' => 'supervisor',
                'description' => 'Supervise work logs and findings for selected departments.',
                'updated_at' => now(),
            ]);

        $this->syncRolePermissions(
            'department-manager',
            [
                'tickets.create',
                'tickets.view',
                'tickets.manage',
                'worklogs.view',
                'worklogs.manage',
                'findings.view',
                'findings.manage',
                'comments.create',
                'reminders.view',
            ]
        );

        $this->syncRolePermissions(
            'supervisor',
            [
                'tickets.view',
                'worklogs.view',
                'worklogs.manage',
                'findings.view',
                'findings.manage',
                'comments.create',
                'reminders.view',
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('code', 'supervisor')
            ->update([
                'name' => 'Department Reviewer',
                'code' => 'department-reviewer',
                'description' => 'Manage work logs and findings for selected departments.',
                'updated_at' => now(),
            ]);

        $this->syncRolePermissions(
            'department-manager',
            [
                'tickets.create',
                'tickets.view',
                'worklogs.view',
                'findings.view',
                'comments.create',
                'reminders.view',
            ]
        );

        $this->syncRolePermissions(
            'department-reviewer',
            [
                'tickets.view',
                'tickets.manage',
                'worklogs.view',
                'worklogs.manage',
                'findings.view',
                'findings.manage',
                'comments.create',
                'reminders.view',
            ]
        );
    }

    private function syncRolePermissions(string $roleCode, array $permissionCodes): void
    {
        $roleId = DB::table('roles')
            ->where('code', $roleCode)
            ->value('id');

        if (! $roleId) {
            return;
        }

        $managedPermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'tickets.create',
                'tickets.view',
                'tickets.manage',
                'worklogs.view',
                'worklogs.manage',
                'findings.view',
                'findings.manage',
                'comments.create',
                'reminders.view',
            ])
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $managedPermissionIds)
            ->delete();

        $permissionIds = DB::table('permissions')
            ->whereIn('code', $permissionCodes)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
