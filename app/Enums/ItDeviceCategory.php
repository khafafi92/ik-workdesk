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
            self::ServerDataCenter => __('Server & Data Center'),
            self::Network => __('Network Infrastructure'),
            self::Computer => 'Laptop/PC',
        };
    }
}
