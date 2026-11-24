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
            self::InUse => __('In Use'),
            self::Spare => __('Spare'),
            self::UnderRepair => __('Under Repair'),
            self::Unused => __('In Stock / Unassigned'),
            self::Damaged => __('Damaged'),
            self::Disposed => __('Disposed'),
        };
    }
}
