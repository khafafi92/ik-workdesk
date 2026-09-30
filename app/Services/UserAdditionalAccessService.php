<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class UserAdditionalAccessService
{
    private const ACCESS_GROUPS = [
        'atk-request' => [
            'atk.request',
        ],
        'atk-management' => [
            'atk.request',
            'atk.manage',
            'atk.report',
        ],
        'daily-report' => [
            'worklogs.view',
            'worklogs.manage',
        ],
        'ltro' => [
            'ltro.view',
            'ltro.manage',
        ],
        'meeting-room' => [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
        ],
        'meeting-room-management' => [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'meeting-bookings.manage',
            'meeting-rooms.manage',
        ],
        'vehicle-booking' => [
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
        ],
        'vehicle-booking-management' => [
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
            'vehicle-bookings.manage',
            'vehicles.manage',
        ],
        'attendance-report' => [
            'attendance.view',
        ],
        'attendance-management' => [
            'attendance.view',
            'attendance.upload',
            'attendance.manage',
        ],
        'reports' => [
            'report.view',
            'report.export',
        ],
        'master-data' => [
            'master-data.manage',
        ],
    ];

    public function options(): array
    {
        return [
            'atk-request' => 'ATK — Permintaan dan Stok Departemen',
            'atk-management' => 'ATK — Kelola Gudang, Master, dan Laporan',
            'daily-report' => 'Daily Report dan Tasks',
            'ltro' => 'LTRO dan turunannya',
            'meeting-room' => 'Meeting Room — Booking Saya',
            'meeting-room-management' => 'Meeting Room — Kelola Booking dan Ruangan',
            'vehicle-booking' => 'Vehicle Booking — Booking Saya',
            'vehicle-booking-management' => 'Vehicle Booking — Kelola Booking dan Kendaraan',
            'reports' => 'Reports dan Export',
            'attendance-report' => 'Attendance Reports',
            'attendance-management' => 'Attendance Reports — Upload dan Kelola',
            'master-data' => 'Master Data dan turunannya',
        ];
    }

    public function stateFor(User $user): array
    {
        $directCodes = $user->directPermissions()
            ->pluck('code')
            ->all();

        return collect(self::ACCESS_GROUPS)
            ->filter(
                fn (array $permissionCodes): bool => collect($permissionCodes)
                    ->every(fn (string $code): bool => in_array($code, $directCodes, true))
            )
            ->keys()
            ->all();
    }

    public function sync(User $user, array $accessGroups): void
    {
        $selectedGroups = collect($accessGroups)
            ->filter(fn ($group): bool => array_key_exists($group, self::ACCESS_GROUPS))
            ->unique();
        $managedCodes = collect(self::ACCESS_GROUPS)->flatten()->unique();
        $selectedCodes = $selectedGroups
            ->flatMap(fn (string $group): array => self::ACCESS_GROUPS[$group])
            ->unique();
        $managedIds = Permission::query()
            ->whereIn('code', $managedCodes)
            ->pluck('id');
        $selectedIds = Permission::query()
            ->whereIn('code', $selectedCodes)
            ->pluck('id');

        $user->directPermissions()->detach($managedIds);
        $user->directPermissions()->attach($selectedIds);
        $user->unsetRelation('directPermissions');
    }
}
