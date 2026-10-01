<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $codes = [
            'ltro.mttr-records', 'ltro.daily-reports', 'ltro.availability',
            'meeting.calendar', 'meeting.bookings', 'meeting.rooms',
            'vehicle.calendar', 'vehicle.bookings', 'vehicle.vehicles',
        ];

        foreach ($codes as $code) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => str($code)->after('.')->replace(['.', '-'], ' ')->title(),
                'module' => str($code)->before('.')->title(),
                'description' => "Access {$code}.",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->copyLegacyAccess('ltro.view', [
            'ltro.mttr-records', 'ltro.daily-reports', 'ltro.availability',
        ], $now);
        $this->copyLegacyAccess('meeting-bookings.view', [
            'meeting.calendar', 'meeting.bookings',
        ], $now);
        $this->copyLegacyAccess('meeting-rooms.manage', ['meeting.rooms'], $now);
        $this->copyLegacyAccess('vehicle-bookings.view', [
            'vehicle.calendar', 'vehicle.bookings',
        ], $now);
        $this->copyLegacyAccess('vehicles.manage', ['vehicle.vehicles'], $now);
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
