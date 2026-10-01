<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'master.subject-categories.manage',
                'master.permit-kbli.manage',
                'master.projects.manage',
                'master.activity-categories.manage',
            ])
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('code', '!=', 'system-admin')
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }

    public function down(): void
    {
    }
};
