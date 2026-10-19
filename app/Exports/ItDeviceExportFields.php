<?php

namespace App\Exports;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Enums\ItSwitchFloor;
use App\Models\ItDevice;
use App\Models\ItInspection;
use BackedEnum;
use DateTimeInterface;

class ItDeviceExportFields
{
    public static function headings(): array
    {
        return ['Nomor Aset', 'Nama Perangkat', 'Jenis perangkat / objek', 'Department pengelola', 'Lokasi', 'LT (Lantai)', 'Detail ruang / rack', 'Serial Number', 'Spesifikasi', 'OS', 'Versi', 'Lisensi', 'Versi Microsoft Office', 'Lisensi Microsoft Office', 'RAM (GB)', 'HDD / SSD', 'Nama User', 'Jabatan', 'Divisi', 'Status Pemakaian', 'Aktif untuk pemeriksaan baru', 'Tanggal penghapusan', 'Alasan penghapusan', 'Keterangan'];
    }

    public static function forDevice(ItDevice $device): array
    {
        $device->loadMissing('assignedEmployee.user');

        return self::values([
            ...$device->inspectionSnapshot(),
            'is_active' => ! $device->trashed() && $device->is_active,
            'assigned_user_name' => $device->assignedEmployee?->user?->name ?? $device->assigned_user_name ?? $device->assignedEmployee?->name,
        ], $device->location_name?->value, $device->location_detail);
    }

    public static function forInspection(ItInspection $inspection): array
    {
        return self::values($inspection->device_snapshot, $inspection->location_snapshot['location']['gps_name'] ?? null, $inspection->location_snapshot['detail'] ?? null);
    }

    private static function values(array $device, ?string $location, ?string $locationDetail): array
    {
        $computer = $device['computer_detail'] ?? [];
        $employee = $device['assigned_employee'] ?? [];
        $disposedAt = $device['disposed_at'] ?? null;

        return [
            $device['asset_number'] ?? null, $device['name'] ?? null,
            self::enumLabel($device['device_type'] ?? null, ItDeviceType::class),
            $device['managing_department']['name'] ?? null, $location,
            self::enumLabel($device['floor'] ?? null, ItSwitchFloor::class), $locationDetail,
            $device['serial_number'] ?? null, $device['specifications'] ?? null,
            $computer['operating_system'] ?? null, $computer['windows_version'] ?? null,
            self::enumLabel($computer['windows_license_status'] ?? null, ItLicenseStatus::class), $computer['office_version'] ?? null,
            self::enumLabel($computer['office_license_status'] ?? null, ItLicenseStatus::class),
            isset($computer['ram_gb']) ? (float) $computer['ram_gb'] : null,
            collect($computer['storage_devices'] ?? [])->map(fn (array $storage) => trim(match ($storage['type'] ?? null) {
                'hdd' => 'HDD', 'ssd' => 'SSD', 'nvme' => 'NVMe SSD', default => '',
            }.' '.($storage['capacity_gb'] ?? '').' GB'))->implode('; '),
            $device['assigned_user_name'] ?? $employee['name'] ?? null, $employee['position'] ?? null, $employee['division'] ?? null,
            self::enumLabel($device['lifecycle_status'] ?? null, ItAssetLifecycle::class),
            array_key_exists('is_active', $device) ? ($device['is_active'] ? 'Ya' : 'Tidak') : null,
            $disposedAt instanceof DateTimeInterface ? $disposedAt->format('Y-m-d') : ($disposedAt ? substr($disposedAt, 0, 10) : null),
            $device['disposal_reason'] ?? null, $device['notes'] ?? null,
        ];
    }

    private static function enumLabel(mixed $value, string $enum): ?string
    {
        return ($value instanceof BackedEnum ? $value : $enum::tryFrom((string) $value))?->label();
    }
}
