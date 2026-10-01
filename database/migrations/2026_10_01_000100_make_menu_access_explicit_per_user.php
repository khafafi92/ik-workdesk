<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuPermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'master-data.manage', 'report.view', 'report.export', 'ltro.view', 'ltro.manage',
                'atk.request', 'atk.manage', 'atk.report', 'attendance.view', 'attendance.upload', 'attendance.manage',
                'tickets.create', 'tickets.view', 'tickets.manage', 'worklogs.view', 'worklogs.manage',
                'reminders.view', 'reminders.manage', 'meeting-bookings.view', 'meeting-bookings.create',
                'meeting-bookings.cancel-own', 'meeting-bookings.manage', 'meeting-rooms.manage',
                'vehicle-bookings.view', 'vehicle-bookings.create', 'vehicle-bookings.cancel-own',
                'vehicle-bookings.manage', 'vehicles.manage', 'ticket-categories.manage',
                'daily-activities.manage', 'task-categories.manage',
            ])
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->where('code', '!=', 'system-admin')
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $menuPermissionIds)
            ->delete();
    }

    public function down(): void
    {
        // Menu selections are stored as direct user permissions.
    }
};
