<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $codes = [
            'report.overview', 'report.service-desk', 'report.daily-activities',
            'report.work-logs', 'report.sla-ticket-aging', 'report.workload',
            'report.department-activity',
        ];

        foreach ($codes as $code) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => str($code)->after('report.')->replace('-', ' ')->title(),
                'module' => 'Reports', 'description' => "Access {$code}.",
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $legacyId = DB::table('permissions')->where('code', 'report.view')->value('id');
        if ($legacyId) {
            $userIds = DB::table('permission_user')->where('permission_id', $legacyId)->pluck('user_id');
            $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            foreach ($userIds as $userId) foreach ($permissionIds as $permissionId) {
                DB::table('permission_user')->updateOrInsert(['user_id' => $userId, 'permission_id' => $permissionId], ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void {}
};
