<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyPermissionId = DB::table('permissions')
            ->where('code', 'master-data.manage')
            ->value('id');

        if (! $legacyPermissionId) {
            return;
        }

        $userIds = DB::table('permission_user')
            ->where('permission_id', $legacyPermissionId)
            ->pluck('user_id');
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'master.subject-categories.manage',
                'master.permit-kbli.manage',
                'master.projects.manage',
                'master.activity-categories.manage',
            ])
            ->pluck('id');
        $now = now();

        foreach ($userIds as $userId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_user')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
