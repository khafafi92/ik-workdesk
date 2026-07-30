<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'View Vehicle Bookings', 'code' => 'vehicle-bookings.view', 'description' => 'View vehicle calendar and own bookings.'],
            ['name' => 'Create Vehicle Bookings', 'code' => 'vehicle-bookings.create', 'description' => 'Create vehicle bookings.'],
            ['name' => 'Cancel Own Vehicle Bookings', 'code' => 'vehicle-bookings.cancel-own', 'description' => 'Cancel own future vehicle bookings.'],
            ['name' => 'Manage Vehicle Bookings', 'code' => 'vehicle-bookings.manage', 'description' => 'View, edit, and cancel every vehicle booking.'],
            ['name' => 'Manage Vehicles', 'code' => 'vehicles.manage', 'description' => 'Create, edit, activate, and deactivate vehicles.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    ...$permission,
                    'module' => 'Vehicle Booking',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $basicPermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'vehicle-bookings.view',
                'vehicle-bookings.create',
                'vehicle-bookings.cancel-own',
            ])
            ->pluck('id');

        foreach (DB::table('roles')->pluck('id') as $roleId) {
            foreach ($basicPermissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('module', 'Vehicle Booking')
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
