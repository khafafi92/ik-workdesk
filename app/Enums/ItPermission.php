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
            self::DevicesView => 'View IT Device Catalog',
            self::DevicesManage => 'Manage IT Device Catalog',
            self::InspectionsView => 'View Infrastructure Inspections',
            self::InspectionsCreate => 'Record Infrastructure Inspections',
            self::InspectionsManage => 'Manage Infrastructure Inspections',
            self::FollowUpsManage => 'Manage Infrastructure Follow-ups',
            self::ReportsView => 'View Infrastructure Reports',
            self::ReportsExport => 'Export Infrastructure Reports',
        };
    }
}
