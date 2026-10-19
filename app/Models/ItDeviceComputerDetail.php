<?php

namespace App\Models;

use App\Enums\ItDeviceCategory;
use App\Enums\ItLicenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ItDeviceComputerDetail extends Model
{
    protected $fillable = ['it_device_id', 'operating_system', 'windows_version', 'office_version', 'windows_license_status', 'windows_verified_by', 'windows_verified_at', 'windows_verification_notes', 'office_license_status', 'office_verified_by', 'office_verified_at', 'office_verification_notes', 'ram_gb', 'storage_devices'];

    protected $attributes = ['windows_license_status' => 'unverified', 'office_license_status' => 'unverified'];

    protected function casts(): array
    {
        return ['windows_license_status' => ItLicenseStatus::class, 'office_license_status' => ItLicenseStatus::class, 'windows_verified_at' => 'datetime', 'office_verified_at' => 'datetime', 'ram_gb' => 'decimal:2', 'storage_devices' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $detail): void {
            if (ItDevice::query()->findOrFail($detail->it_device_id)->category !== ItDeviceCategory::Computer) {
                throw ValidationException::withMessages(['it_device_id' => 'Detail komputer hanya untuk Laptop/PC.']);
            }

            if ($detail->exists && $detail->isDirty('it_device_id')) {
                throw ValidationException::withMessages(['it_device_id' => 'Detail komputer tidak dapat dipindahkan ke perangkat lain.']);
            }

            Validator::make($detail->only(['ram_gb', 'storage_devices']), [
                'ram_gb' => ['nullable', 'numeric', 'gt:0'],
                'storage_devices' => ['nullable', 'array'],
                'storage_devices.*.type' => ['required', 'in:hdd,ssd,nvme'],
                'storage_devices.*.capacity_gb' => ['required', 'numeric', 'gt:0'],
                'storage_devices.*.serial_number' => ['nullable', 'string', 'max:255'],
            ])->validate();

            foreach (['windows', 'office'] as $product) {
                $status = $detail->{$product.'_license_status'};
                $verifiedBy = $detail->{$product.'_verified_by'};
                $verifiedAt = $detail->{$product.'_verified_at'};
                $notes = $detail->{$product.'_verification_notes'};
                if ($status !== ItLicenseStatus::Unverified && (! $verifiedBy || ! $verifiedAt || $verifiedAt->isFuture() || blank($notes) || ! User::query()->findOrFail($verifiedBy)->isActiveForAccess())) {
                    throw ValidationException::withMessages([$product.'_license_status' => 'Status lisensi memerlukan petugas aktif, waktu, dan dasar verifikasi.']);
                }
                if ($status === ItLicenseStatus::Unverified && ($verifiedBy || $verifiedAt || filled($notes))) {
                    throw ValidationException::withMessages([$product.'_license_status' => 'Bersihkan data verifikasi saat status Belum Diverifikasi.']);
                }
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ItDevice::class, 'it_device_id');
    }

    public function windowsVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'windows_verified_by');
    }

    public function officeVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_verified_by');
    }
}
