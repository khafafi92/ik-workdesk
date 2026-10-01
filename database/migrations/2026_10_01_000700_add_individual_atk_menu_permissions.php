<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $codes = [
            'atk.dashboard', 'atk.requests', 'atk.summary', 'atk.items',
            'atk.categories', 'atk.department-balance', 'atk.units', 'atk.usage',
            'atk.department-movement', 'atk.stock-movement', 'atk.request-history',
            'atk.reports',
        ];

        foreach ($codes as $code) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => str($code)->after('atk.')->replace('-', ' ')->title(),
                'module' => 'ATK',
                'description' => "Access {$code}.",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->copyLegacyAccess('atk.request', [
            'atk.requests', 'atk.department-balance', 'atk.usage',
        ], $now);
        $this->copyLegacyAccess('atk.manage', [
            'atk.dashboard', 'atk.requests', 'atk.summary', 'atk.items',
            'atk.categories', 'atk.department-balance', 'atk.units', 'atk.usage',
            'atk.department-movement', 'atk.stock-movement', 'atk.request-history',
            'atk.reports',
        ], $now);
        $this->copyLegacyAccess('atk.report', [
            'atk.dashboard', 'atk.summary', 'atk.department-balance', 'atk.usage',
            'atk.department-movement', 'atk.stock-movement', 'atk.request-history',
            'atk.reports',
        ], $now);
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
