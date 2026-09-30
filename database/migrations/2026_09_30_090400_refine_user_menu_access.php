<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->where('code', 'system-admin')->update([
            'name' => 'Sys Administrator',
            'description' => 'Full access to every module.',
            'updated_at' => $now,
        ]);

        foreach ([
            [
                'name' => 'Administrator',
                'code' => 'administrator',
                'description' => 'Manage user accounts and assign menu access, without Sys Administrator authority.',
            ],
            [
                'name' => 'Admin',
                'code' => 'admin',
                'description' => 'Operational admin. Module access is granted from the user menu checklist.',
            ],
        ] as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [...$role, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $administratorId = DB::table('roles')->where('code', 'administrator')->value('id');
        $requesterId = DB::table('roles')->where('code', 'requester')->value('id');

        if ($administratorId) {
            foreach (DB::table('permissions')->whereIn('code', ['users.manage', 'roles.manage'])->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $administratorId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        if ($requesterId) {
            DB::table('permission_role')->where('role_id', $requesterId)->delete();

            foreach (DB::table('permissions')->whereIn('code', ['tickets.create', 'tickets.view'])->pluck('id') as $permissionId) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $requesterId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', ['administrator', 'admin'])->pluck('id');
        DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
        DB::table('role_user')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }
};
