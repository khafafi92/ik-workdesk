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

    private const ACCESS_BY_LEVEL = [
        'system-admin' => [
            'atk-request', 'atk-management', 'daily-report', 'ltro',
            'meeting-room', 'meeting-room-management', 'vehicle-booking',
            'vehicle-booking-management', 'attendance-report',
            'attendance-management', 'reports', 'master-data',
        ],
        'administrator' => [
            'atk-request', 'atk-management', 'daily-report', 'ltro',
            'meeting-room', 'meeting-room-management', 'vehicle-booking',
            'vehicle-booking-management', 'attendance-report',
            'attendance-management', 'reports', 'master-data',
        ],
        'admin' => [
            'atk-request', 'atk-management', 'daily-report', 'ltro',
            'meeting-room', 'meeting-room-management', 'vehicle-booking',
            'vehicle-booking-management', 'attendance-report',
            'attendance-management', 'reports', 'master-data',
        ],
        'department-manager' => [
            'atk-request', 'daily-report', 'ltro', 'meeting-room',
            'vehicle-booking', 'attendance-report', 'reports', 'master-data',
        ],
        'supervisor' => [
            'atk-request', 'daily-report', 'meeting-room', 'vehicle-booking',
            'attendance-report', 'master-data',
        ],
        'general-affairs' => [
            'atk-request', 'atk-management', 'meeting-room', 'vehicle-booking',
            'attendance-report', 'reports', 'master-data',
        ],
        'attendance-operator' => [
            'meeting-room', 'vehicle-booking', 'attendance-management', 'reports',
        ],
        'cbo' => [
            'daily-report', 'reports',
        ],
        'requester' => [
            'atk-request', 'meeting-room', 'vehicle-booking',
            'attendance-report', 'master-data',
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

    public function optionsForLevel(string $level): array
    {
        $allowedGroups = self::ACCESS_BY_LEVEL[$level] ?? [];

        return array_intersect_key($this->options(), array_flip($allowedGroups));
    }

    public function validateForLevel(string $level, array $accessGroups): array
    {
        $selectedGroups = collect($accessGroups)
            ->filter(fn ($group): bool => is_string($group))
            ->unique()
            ->values()
            ->all();
        $allowedGroups = array_keys($this->optionsForLevel($level));

        if (array_diff($selectedGroups, $allowedGroups) !== []) {
            abort(422, 'Akses tambahan tidak sesuai dengan level user yang dipilih.');
        }

        return $selectedGroups;
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
