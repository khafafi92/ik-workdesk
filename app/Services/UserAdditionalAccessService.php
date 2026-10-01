<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class UserAdditionalAccessService
{
    private const MENU_ITEMS = [
        'subject-categories' => ['label' => 'Subject Categories', 'access' => 'master-subject-categories'],
        'permit-kbli' => ['label' => 'Permit & KBLI', 'access' => 'master-permit-kbli'],
        'projects' => ['label' => 'Projects', 'access' => 'master-projects'],
        'activity-categories' => ['label' => 'Activity Categories', 'access' => 'master-activity-categories'],
        'attendance-report-center' => ['label' => 'Attendance Report', 'access' => 'attendance-report'],
        'attendance-upload-period' => ['label' => 'Upload Period', 'access' => 'attendance-management'],
        'attendance-report-results' => ['label' => 'Report Results', 'access' => 'attendance-report'],
        'report-overview' => ['label' => 'Overview', 'access' => 'reports'],
        'report-service-desk' => ['label' => 'Service Desk', 'access' => 'reports'],
        'report-daily-activities' => ['label' => 'Daily Activities', 'access' => 'reports'],
        'report-work-logs' => ['label' => 'Work Logs', 'access' => 'reports'],
        'report-sla-ticket-aging' => ['label' => 'SLA & Ticket Aging', 'access' => 'reports'],
        'report-workload' => ['label' => 'Workload', 'access' => 'reports'],
        'report-department-activity' => ['label' => 'Department Activity', 'access' => 'reports'],
        'ltro-mttr-records' => ['label' => 'MTTR Records', 'access' => 'ltro'],
        'ltro-daily-reports' => ['label' => 'Daily Reports', 'access' => 'ltro'],
        'ltro-availability' => ['label' => 'Availability LTRO 1B', 'access' => 'ltro'],
        'atk-dashboard' => ['label' => 'Dashboard ATK', 'access' => 'atk-management'],
        'atk-requests' => ['label' => 'Permintaan ATK', 'access' => 'atk-request'],
        'atk-summary' => ['label' => 'Rekap Kebutuhan', 'access' => 'atk-management'],
        'atk-items' => ['label' => 'Master Barang dan Gudang', 'access' => 'atk-management'],
        'atk-categories' => ['label' => 'Kategori Barang', 'access' => 'atk-management'],
        'atk-department-balance' => ['label' => 'Stok Departemen', 'access' => 'atk-request'],
        'atk-units' => ['label' => 'Satuan Barang', 'access' => 'atk-management'],
        'atk-usage' => ['label' => 'Pemakaian ATK', 'access' => 'atk-request'],
        'atk-department-movement' => ['label' => 'Mutasi Departemen', 'access' => 'atk-management'],
        'atk-stock-movement' => ['label' => 'Mutasi Gudang', 'access' => 'atk-management'],
        'atk-request-history' => ['label' => 'Audit Permintaan', 'access' => 'atk-management'],
        'atk-reports' => ['label' => 'Laporan & Export', 'access' => 'atk-management'],
        'meeting-calendar' => ['label' => 'Calendar', 'access' => 'meeting-room'],
        'meeting-bookings' => ['label' => 'Bookings', 'access' => 'meeting-room'],
        'meeting-rooms' => ['label' => 'Meeting Rooms', 'access' => 'meeting-room-management'],
        'vehicle-calendar' => ['label' => 'Calendar', 'access' => 'vehicle-booking'],
        'vehicle-bookings' => ['label' => 'Bookings', 'access' => 'vehicle-booking'],
        'vehicles' => ['label' => 'Vehicles', 'access' => 'vehicle-booking-management'],
        'reminders' => ['label' => 'Reminders', 'access' => 'notifications'],
        'service-desk' => ['label' => 'Service Desk', 'access' => 'service-desk'],
        'request-categories' => ['label' => 'Request Categories', 'access' => 'request-categories'],
        'work-logs' => ['label' => 'Work Logs', 'access' => 'daily-report'],
        'daily-activities' => ['label' => 'Daily Activities', 'access' => 'daily-activities'],
        'task-categories' => ['label' => 'Task Categories', 'access' => 'task-categories'],
    ];

    private const ACCESS_GROUPS = [
        'master-subject-categories' => ['master.subject-categories.manage'],
        'master-permit-kbli' => ['master.permit-kbli.manage'],
        'master-projects' => ['master.projects.manage'],
        'master-activity-categories' => ['master.activity-categories.manage'],
        'notifications' => [
            'reminders.view',
        ],
        'service-desk' => [
            'tickets.create',
            'tickets.view',
        ],
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
        'request-categories' => [
            'ticket-categories.manage',
        ],
        'daily-activities' => [
            'daily-activities.manage',
        ],
        'task-categories' => [
            'task-categories.manage',
        ],
    ];

    public function options(): array
    {
        return [
            'master-data' => 'Kelola seluruh Master Data',
            'attendance-report' => 'Lihat Attendance Report dan Report Results',
            'attendance-management' => 'Attendance Reports — Upload dan Kelola',
            'reports' => 'Akses seluruh Reports dan Export',
            'ltro' => 'Akses seluruh menu LTRO',
            'atk-request' => 'ATK — Permintaan dan Stok Departemen',
            'atk-management' => 'ATK — Kelola Gudang, Master, dan Laporan',
            'daily-report' => 'Work Logs',
            'meeting-room' => 'Meeting Room — Booking Saya',
            'meeting-room-management' => 'Meeting Room — Kelola Booking dan Ruangan',
            'vehicle-booking' => 'Vehicle Booking — Booking Saya',
            'vehicle-booking-management' => 'Vehicle Booking — Kelola Booking dan Kendaraan',
            'notifications' => 'Reminders',
            'service-desk' => 'Buat dan lihat permintaan',
            'request-categories' => 'Request Categories',
            'daily-activities' => 'Daily Activities',
            'task-categories' => 'Task Categories',
        ];
    }

    public function menuDescriptions(): array
    {
        return [
            'notifications' => 'Reminders.',
            'service-desk' => 'Service Desk.',
            'master-data' => 'Subject Categories, Permit & KBLI, Projects, Activity Categories.',
            'attendance-report' => 'Attendance Report dan Report Results.',
            'attendance-management' => 'Upload Period dan pengelolaan data attendance.',
            'reports' => 'Overview, Service Desk, Daily Activities, Work Logs, SLA & Ticket Aging, Workload, Department Activity.',
            'ltro' => 'MTTR Records, Daily Reports, Availability LTRO 1B.',
            'atk-request' => 'Permintaan ATK, Stok Departemen sesuai department, dan Pemakaian ATK.',
            'atk-management' => 'Dashboard, Rekap, Master Barang dan Gudang, Kategori, Satuan, Mutasi, Audit, dan Laporan.',
            'meeting-room' => 'Calendar dan Bookings.',
            'meeting-room-management' => 'Meeting Rooms serta pengelolaan seluruh booking.',
            'vehicle-booking' => 'Calendar dan Bookings.',
            'vehicle-booking-management' => 'Vehicles serta pengelolaan seluruh booking.',
            'daily-report' => 'Kemampuan mengelola Work Logs.',
            'request-categories' => 'Request Categories pada Service Desk.',
            'daily-activities' => 'Daily Activities pada Daily Reports.',
            'task-categories' => 'Task Categories pada Daily Reports.',
        ];
    }

    public function menuGroups(): array
    {
        return [
            'master_data' => [
                'label' => 'Master Data',
                'description' => 'Pilih menu Master Data yang diperlukan.',
                'items' => ['subject-categories', 'permit-kbli', 'projects', 'activity-categories'],
            ],
            'attendance_report' => [
                'label' => 'Attendance Report',
                'description' => 'Pilih akses melihat laporan atau mengelola data attendance.',
                'items' => ['attendance-report-center', 'attendance-upload-period', 'attendance-report-results'],
            ],
            'reports' => [
                'label' => 'Reports',
                'description' => 'Laporan operasional dan ekspor data.',
                'items' => ['report-overview', 'report-service-desk', 'report-daily-activities', 'report-work-logs', 'report-sla-ticket-aging', 'report-workload', 'report-department-activity'],
            ],
            'ltro' => [
                'label' => 'LTRO Management',
                'description' => 'MTTR Records, Daily Reports, dan Availability LTRO 1B.',
                'items' => ['ltro-mttr-records', 'ltro-daily-reports', 'ltro-availability'],
            ],
            'atk' => [
                'label' => 'ATK',
                'description' => 'Pilih akses permintaan atau pengelolaan stok ATK.',
                'items' => ['atk-dashboard', 'atk-requests', 'atk-summary', 'atk-items', 'atk-categories', 'atk-department-balance', 'atk-units', 'atk-usage', 'atk-department-movement', 'atk-stock-movement', 'atk-request-history', 'atk-reports'],
            ],
            'meeting_room' => [
                'label' => 'Meeting Room',
                'description' => 'Pilih booking pribadi atau akses pengelolaan ruangan.',
                'items' => ['meeting-calendar', 'meeting-bookings', 'meeting-rooms'],
            ],
            'vehicle_booking' => [
                'label' => 'Vehicle Booking',
                'description' => 'Pilih booking pribadi atau akses pengelolaan kendaraan.',
                'items' => ['vehicle-calendar', 'vehicle-bookings', 'vehicles'],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Notifikasi dan reminders.',
                'items' => ['reminders'],
            ],
            'service_desk' => [
                'label' => 'Service Desk',
                'description' => 'Permintaan Service Desk dan kategori permintaan.',
                'items' => ['service-desk', 'request-categories'],
            ],
            'daily_reports' => [
                'label' => 'Daily Reports',
                'description' => 'Work Logs, Daily Activities, dan Task Categories.',
                'items' => ['work-logs', 'daily-activities', 'task-categories'],
            ],
        ];
    }

    public function optionsForGroups(array $groups): array
    {
        return array_intersect_key($this->options(), array_flip($groups));
    }

    public function menuOptionsForModule(array $module): array
    {
        return collect($module['items'])
            ->mapWithKeys(fn (string $item): array => [$item => self::MENU_ITEMS[$item]['label']])
            ->all();
    }

    public function optionsForLevel(string $level): array
    {
        return $this->options();
    }

    public function validateForLevel(string $level, array $accessGroups): array
    {
        $selectedGroups = collect($accessGroups)
            ->filter(fn ($group): bool => is_string($group))
            ->unique()
            ->values()
            ->all();
        $allowedGroups = array_keys($this->options());

        if (array_diff($selectedGroups, $allowedGroups) !== []) {
            abort(422, 'Pilihan akses menu tidak valid.');
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

    public function menuStateFor(User $user): array
    {
        $selectedGroups = $this->stateFor($user);

        if (in_array('master-data', $selectedGroups, true)) {
            $selectedGroups = [
                ...$selectedGroups,
                'master-subject-categories',
                'master-permit-kbli',
                'master-projects',
                'master-activity-categories',
            ];
        }

        return collect($this->menuGroups())
            ->mapWithKeys(fn (array $module, string $key): array => [
                $key => collect($module['items'])
                    ->filter(fn (string $item): bool => in_array(self::MENU_ITEMS[$item]['access'], $selectedGroups, true))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    public function accessGroupsFromMenuState(array $menuAccess): array
    {
        return collect($menuAccess)
            ->flatten()
            ->filter(fn ($item): bool => is_string($item) && array_key_exists($item, self::MENU_ITEMS))
            ->map(fn (string $item): string => self::MENU_ITEMS[$item]['access'])
            ->unique()
            ->values()
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
