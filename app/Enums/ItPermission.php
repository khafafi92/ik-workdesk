<?php

namespace App\Enums;

enum ItPermission: string
{
    case DevicesView = 'it.devices.view';
    case DevicesManage = 'it.devices.manage';
    case InspectionsView = 'it.inspections.view';
    case InspectionsCreate = 'it.inspections.create';
    case InspectionsManage = 'it.inspections.manage';
    case FollowUpsManage = 'it.follow-ups.manage';
    case ReportsView = 'it.reports.view';
    case ReportsExport = 'it.reports.export';

    public function label(): string
    {
        return match ($this) {
            self::DevicesView => 'Lihat Master Perangkat IT',
            self::DevicesManage => 'Kelola Master Perangkat IT',
            self::InspectionsView => 'Lihat Pengecekan Infrastruktur',
            self::InspectionsCreate => 'Isi Pengecekan Infrastruktur',
            self::InspectionsManage => 'Kelola Pengecekan Infrastruktur',
            self::FollowUpsManage => 'Kelola Tindak Lanjut Infrastruktur',
            self::ReportsView => 'Lihat Laporan Infrastruktur',
            self::ReportsExport => 'Ekspor Laporan Infrastruktur',
        };
    }
}
