<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            [
                'name' => 'View Meeting Bookings',
                'code' => 'meeting-bookings.view',
                'description' => 'View the meeting room calendar and own bookings.',
            ],
            [
                'name' => 'Create Meeting Bookings',
                'code' => 'meeting-bookings.create',
                'description' => 'Create meeting room bookings.',
            ],
            [
                'name' => 'Cancel Own Meeting Bookings',
                'code' => 'meeting-bookings.cancel-own',
                'description' => 'Cancel own future meeting room bookings.',
            ],
            [
                'name' => 'Manage Meeting Bookings',
                'code' => 'meeting-bookings.manage',
                'description' => 'View, edit, and cancel every meeting room booking.',
            ],
            [
                'name' => 'Manage Meeting Rooms',
                'code' => 'meeting-rooms.manage',
                'description' => 'Create, edit, activate, and deactivate meeting rooms.',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    ...$permission,
                    'module' => 'Meeting Room',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $basicPermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'meeting-bookings.view',
                'meeting-bookings.create',
                'meeting-bookings.cancel-own',
            ])
            ->pluck('id');
        $roleIds = DB::table('roles')->pluck('id');

        foreach ($roleIds as $roleId) {
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
            ->whereIn('code', [
                'meeting-bookings.view',
                'meeting-bookings.create',
                'meeting-bookings.cancel-own',
                'meeting-bookings.manage',
                'meeting-rooms.manage',
            ])
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
