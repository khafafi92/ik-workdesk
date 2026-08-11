<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OPTIONAL_PERMISSION_CODES = [
        'meeting-bookings.view',
        'meeting-bookings.create',
        'meeting-bookings.cancel-own',
        'vehicle-bookings.view',
        'vehicle-bookings.create',
        'vehicle-bookings.cancel-own',
    ];

    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table): void {
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['permission_id', 'user_id']);
        });

        $systemAdminRoleId = DB::table('roles')
            ->where('code', 'system-admin')
            ->value('id');
        $optionalPermissionIds = DB::table('permissions')
            ->whereIn('code', self::OPTIONAL_PERMISSION_CODES)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $optionalPermissionIds)
            ->when(
                $systemAdminRoleId,
                fn ($query) => $query->where('role_id', '!=', $systemAdminRoleId)
            )
            ->delete();

        $attendanceViewerRoleId = DB::table('roles')
            ->where('code', 'attendance-viewer')
            ->value('id');

        if (! $attendanceViewerRoleId) {
            return;
        }

        $attendanceViewPermissionId = DB::table('permissions')
            ->where('code', 'attendance.view')
            ->value('id');
        $requesterRoleId = DB::table('roles')
            ->where('code', 'requester')
            ->value('id');
        $userIds = DB::table('role_user')
            ->where('role_id', $attendanceViewerRoleId)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            if ($attendanceViewPermissionId) {
                DB::table('permission_user')->insertOrIgnore([
                    'permission_id' => $attendanceViewPermissionId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $otherRoleExists = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', '!=', $attendanceViewerRoleId)
                ->exists();

            if (! $otherRoleExists && $requesterRoleId) {
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $requesterRoleId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('roles')
            ->where('id', $attendanceViewerRoleId)
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
