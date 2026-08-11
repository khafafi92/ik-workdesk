<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accountAdministratorRoleId = DB::table('roles')
            ->where('code', 'user-manager')
            ->value('id');

        if (! $accountAdministratorRoleId) {
            return;
        }

        $requesterRoleId = DB::table('roles')
            ->where('code', 'requester')
            ->value('id');
        $userIds = DB::table('role_user')
            ->where('role_id', $accountAdministratorRoleId)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();
            $hasAnotherRole = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', '!=', $accountAdministratorRoleId)
                ->exists();

            if (
                $user
                && ! $user->is_admin
                && ! $hasAnotherRole
                && $requesterRoleId
            ) {
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $requesterRoleId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('roles')
            ->where('id', $accountAdministratorRoleId)
            ->delete();
    }

    public function down(): void
    {
        if (DB::table('roles')->where('code', 'user-manager')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'name' => 'Account Administrator',
            'code' => 'user-manager',
            'description' => 'Manage user accounts and assign preset roles.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')
            ->where('code', 'user-manager')
            ->value('id');
        $permissionId = DB::table('permissions')
            ->where('code', 'users.manage')
            ->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
