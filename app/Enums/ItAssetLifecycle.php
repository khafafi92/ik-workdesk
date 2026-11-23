<?php

namespace App\Enums;

enum ItAssetLifecycle: string
{
    case InUse = 'in_use';
    case Spare = 'spare';
    case UnderRepair = 'under_repair';
    case Unused = 'unused';
    case Damaged = 'damaged';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::InUse => 'In Use',
            self::Spare => 'Spare',
            self::UnderRepair => 'Under Repair',
            self::Unused => 'In Stock / Unassigned',
            self::Damaged => 'Damaged',
            self::Disposed => 'Disposed',
        };
    }
}
