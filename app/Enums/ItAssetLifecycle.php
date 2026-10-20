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
            self::InUse => 'Digunakan',
            self::Spare => 'Cadangan',
            self::UnderRepair => 'Dalam Perbaikan',
            self::Unused => 'Stok / Belum Digunakan',
            self::Damaged => 'Rusak',
            self::Disposed => 'Dihapuskan',
        };
    }
}
