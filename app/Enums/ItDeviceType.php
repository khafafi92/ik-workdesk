<?php

namespace App\Enums;

enum ItDeviceType: string
{
    case Server = 'server';
    case ServerRoom = 'server_room';
    case Storage = 'storage';
    case Ups = 'ups';
    case AirConditioner = 'air_conditioner';
    case Hypervisor = 'hypervisor';
    case VirtualMachine = 'virtual_machine';
    case Fortigate = 'fortigate';
    case AccessPoint = 'access_point';
    case Switch = 'switch';
    case Laptop = 'laptop';
    case Pc = 'pc';

    public function category(): ItDeviceCategory
    {
        return match ($this) {
            self::Fortigate, self::AccessPoint, self::Switch => ItDeviceCategory::Network,
            self::Laptop, self::Pc => ItDeviceCategory::Computer,
            default => ItDeviceCategory::ServerDataCenter,
        };
    }

    public function kind(): ItObjectKind
    {
        return match ($this) {
            self::ServerRoom => ItObjectKind::Room,
            self::Hypervisor, self::VirtualMachine => ItObjectKind::Virtual,
            default => ItObjectKind::Physical,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Server => 'Server Fisik',
            self::ServerRoom => 'Ruang Server',
            self::Storage => 'Storage / NAS',
            self::Ups => 'UPS',
            self::AirConditioner => 'AC',
            self::Hypervisor => 'Hypervisor',
            self::VirtualMachine => 'Virtual Machine',
            self::Fortigate => 'FortiGate Firewall',
            self::AccessPoint => 'Access Point',
            self::Switch => 'Switch',
            self::Laptop => 'Laptop',
            self::Pc => 'PC',
        };
    }
}
