<?php

namespace App\Enums;

enum ItDeviceLocation: string
{
    case OfficeHo = 'Office HO';
    case OfficeAlamanda = 'Office Alamanda';
    case Site = 'Site';

    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_column(self::cases(), 'value'));
    }
}
