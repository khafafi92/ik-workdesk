<?php

namespace App\Enums;

enum ItDeviceCategory: string
{
    case ServerDataCenter = 'server_data_center';
    case Network = 'network';
    case Computer = 'computer';

    public function label(): string
    {
        return match ($this) {
            self::ServerDataCenter => 'Server & Data Center',
            self::Network => 'Network Infrastructure',
            self::Computer => 'Laptop/PC',
        };
    }
}
