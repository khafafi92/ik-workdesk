<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['Manage Request Categories', 'ticket-categories.manage', 'Service Desk', 'Manage the Request Categories menu.'],
            ['Manage Daily Activities', 'daily-activities.manage', 'Daily Reports', 'Access the Daily Activities menu.'],
            ['Manage Task Categories', 'task-categories.manage', 'Daily Reports', 'Manage the Task Categories menu.'],
        ] as [$name, $code, $module, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'module' => $module, 'description' => $description, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $defaultPermissionIds = DB::table('permissions')
            ->whereIn('code', ['tickets.create', 'tickets.view', 'reminders.view', 'worklogs.view'])
            ->pluck('id');

        foreach (DB::table('roles')->where('is_active', true)->pluck('id') as $roleId) {
            foreach ($defaultPermissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        $atkRequestId = DB::table('permissions')->where('code', 'atk.request')->value('id');

        if ($atkRequestId) {
            foreach (DB::table('roles')->whereIn('code', ['requester', 'supervisor', 'department-manager'])->pluck('id') as $roleId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $atkRequestId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', ['ticket-categories.manage', 'daily-activities.manage', 'task-categories.manage'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
