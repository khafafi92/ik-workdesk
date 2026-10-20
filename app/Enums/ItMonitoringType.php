<?php

namespace App\Enums;

enum ItMonitoringType: string
{
    case Firewall = 'firewall';
    case Synology = 'synology';
    case Vsphere = 'vsphere';

    public function label(): string
    {
        return match ($this) {
            self::Firewall => 'Firewall & koneksi internet',
            self::Synology => 'Backup Synology',
            self::Vsphere => 'VM vSphere',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }

    public function statuses(): array
    {
        return ['unchecked' => 'Belum diperiksa', ...match ($this) {
            self::Firewall => ['up' => 'Terhubung', 'unstable' => 'Putus nyambung', 'down' => 'Terputus'],
            self::Synology => ['success' => 'Berhasil', 'running' => 'Masih berjalan', 'failed' => 'Gagal', 'not_run' => 'Tidak berjalan'],
            self::Vsphere => ['powered_on' => 'Menyala', 'powered_off' => 'Mati', 'suspended' => 'Suspended', 'unknown' => 'Tidak dapat diperiksa'],
        }];
    }

    public function needsAttention(array $check): bool
    {
        if (($check['status'] ?? 'unchecked') === 'unchecked') {
            return false;
        }

        return match ($this) {
            self::Firewall => ($check['status'] ?? null) !== 'up',
            self::Synology => ($check['status'] ?? null) !== 'success' || ($check['data_present'] ?? null) !== 'yes',
            self::Vsphere => ($check['status'] ?? null) !== ($check['expected_power_state'] ?? 'powered_on') || ($check['health'] ?? null) !== 'normal',
        };
    }

    public function emptyCheck(): array
    {
        return ['name' => null, 'status' => 'unchecked', 'notes' => null, ...match ($this) {
            self::Firewall => ['test_target' => null, 'latency_ms' => null, 'packet_loss_percent' => null],
            self::Synology => ['data_present' => null, 'started_at' => null, 'completed_at' => null, 'data_path' => null, 'data_size' => null],
            self::Vsphere => ['expected_power_state' => 'powered_on', 'health' => null, 'cpu_percent' => null, 'ram_percent' => null, 'disk_free_percent' => null],
        }];
    }
}
