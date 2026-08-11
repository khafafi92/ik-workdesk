<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $generalUserRoleId = DB::table('roles')
            ->where('code', 'general-user')
            ->value('id');

        if (! $generalUserRoleId) {
            return;
        }

        $requesterRoleId = DB::table('roles')
            ->where('code', 'requester')
            ->value('id');

        if ($requesterRoleId) {
            $userIds = DB::table('role_user')
                ->where('role_id', $generalUserRoleId)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $requesterRoleId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('roles')
            ->where('id', $generalUserRoleId)
            ->delete();
    }

    public function down(): void
    {
        if (DB::table('roles')->where('code', 'general-user')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'name' => 'General User',
            'code' => 'general-user',
            'description' => 'Standard application user.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $generalUserRoleId = DB::table('roles')
            ->where('code', 'general-user')
            ->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'tickets.create',
                'tickets.view',
                'worklogs.view',
                'findings.respond',
                'comments.create',
                'reminders.view',
                'meeting-bookings.view',
                'meeting-bookings.create',
                'meeting-bookings.cancel-own',
                'vehicle-bookings.view',
                'vehicle-bookings.create',
                'vehicle-bookings.cancel-own',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $generalUserRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
