<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['Manage Subject Categories', 'master.subject-categories.manage'],
            ['Manage Permit & KBLI', 'master.permit-kbli.manage'],
            ['Manage Projects', 'master.projects.manage'],
            ['Manage Activity Categories', 'master.activity-categories.manage'],
        ] as [$name, $code]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => 'Master Data',
                    'description' => "Access the {$name} menu.",
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', [
                'master.subject-categories.manage',
                'master.permit-kbli.manage',
                'master.projects.manage',
                'master.activity-categories.manage',
            ])
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
