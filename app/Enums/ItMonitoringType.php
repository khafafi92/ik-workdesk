<?php

namespace App\Enums;

enum ItMonitoringType: string
{
    case Internet = 'internet';
    case Firewall = 'firewall';
    case ServerRoom = 'server_room';
    case Synology = 'synology';
    case Vsphere = 'vsphere';

    public function label(int $version = 2): string
    {
        return match ($this) {
            self::Internet => 'Internet',
            self::Firewall => $version === 1 ? 'Firewall & Internet connection' : 'Firewall',
            self::ServerRoom => 'Server room',
            self::Synology => $version === 1 ? 'Backup Synology' : 'Backup',
            self::Vsphere => $version === 1 ? 'VM vSphere' : 'VM',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }

    public function statuses(int $version = 1): array
    {
        if ($version === 2 || in_array($this, [self::Internet, self::ServerRoom], true)) {
            return ['unchecked' => 'Not inspected', ...match ($this) {
                self::Internet, self::Firewall, self::Synology => ['normal' => 'Normal', 'issue' => 'Issue detected'],
                self::ServerRoom => ['recorded' => 'Temperature recorded'],
                self::Vsphere => ['up_to_date' => 'Up to date', 'update' => 'Update'],
            }];
        }

        return ['unchecked' => 'Not inspected', ...match ($this) {
            self::Firewall => ['up' => 'Connected', 'unstable' => 'Intermittent', 'down' => 'Disconnected'],
            self::Synology => ['success' => 'Success', 'running' => 'Still running', 'failed' => 'Failed', 'not_run' => 'Not running'],
            self::Vsphere => ['powered_on' => 'Powered on', 'powered_off' => 'Powered off', 'suspended' => 'Suspended', 'unknown' => 'Unable to check'],
        }];
    }

    public function needsAttention(array $check): bool
    {
        if (($check['status'] ?? 'unchecked') === 'unchecked') {
            return false;
        }

        if (($check['_version'] ?? 1) === 2) {
            return in_array($check['status'] ?? null, ['issue', 'update'], true);
        }

        return match ($this) {
            self::Firewall => ($check['status'] ?? null) !== 'up',
            self::Synology => ($check['status'] ?? null) !== 'success' || ($check['data_present'] ?? null) !== 'yes',
            self::Vsphere => ($check['status'] ?? null) !== ($check['expected_power_state'] ?? 'powered_on') || ($check['health'] ?? null) !== 'normal',
            default => false,
        };
    }

    public function emptyCheck(int $version = 1): array
    {
        if ($version === 2 || in_array($this, [self::Internet, self::ServerRoom], true)) {
            return ['_version' => 2, 'name' => $this->fixedCheckName(), 'status' => 'unchecked', 'notes' => null, 'checked_at' => null,
                ...match ($this) {
                    self::Firewall => ['quarantine_ips' => null],
                    self::ServerRoom => ['temperature_c' => null],
                    self::Vsphere => ['update_version' => null],
                    default => [],
                }];
        }

        return ['name' => null, 'status' => 'unchecked', 'notes' => null, ...match ($this) {
            self::Firewall => ['test_target' => null, 'latency_ms' => null, 'packet_loss_percent' => null],
            self::Synology => ['data_present' => null, 'started_at' => null, 'completed_at' => null, 'data_path' => null, 'data_size' => null],
            self::Vsphere => ['expected_power_state' => 'powered_on', 'health' => null, 'cpu_percent' => null, 'ram_percent' => null, 'disk_free_percent' => null],
            default => [],
        }];
    }

    public function fixedCheckName(): ?string
    {
        return match ($this) {
            self::Internet => 'Internet status',
            self::Firewall => 'FortiView status',
            self::ServerRoom => 'Server room temperature',
            default => null,
        };
    }
}
