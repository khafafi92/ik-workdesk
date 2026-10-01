<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $codes = [
            'attendance.report-center',
            'attendance.upload-period',
            'attendance.report-results',
        ];

        foreach ($codes as $code) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => str($code)->after('attendance.')->replace('-', ' ')->title(),
                'module' => 'Attendance',
                'description' => "Access {$code}.",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->copyLegacyAccess('attendance.view', [
            'attendance.report-center', 'attendance.report-results',
        ], $now);
        $this->copyLegacyAccess('attendance.upload', ['attendance.upload-period'], $now);
        $this->copyLegacyAccess('attendance.manage', ['attendance.upload-period'], $now);
    }

    private function copyLegacyAccess(string $legacyCode, array $newCodes, mixed $now): void
    {
        $legacyId = DB::table('permissions')->where('code', $legacyCode)->value('id');

        if (! $legacyId) {
            return;
        }

        $userIds = DB::table('permission_user')->where('permission_id', $legacyId)->pluck('user_id');
        $permissionIds = DB::table('permissions')->whereIn('code', $newCodes)->pluck('id');

        foreach ($userIds as $userId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_user')->updateOrInsert(
                    ['user_id' => $userId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
    }
};
