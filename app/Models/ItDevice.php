<?php

namespace App\Models;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceType;
use App\Enums\ItObjectKind;
use App\Enums\ItPermission;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ItDevice extends Model
{
    protected $fillable = ['code', 'category', 'device_type', 'object_kind', 'name', 'asset_number', 'serial_number', 'manufacturer', 'model_name', 'specifications', 'work_location_id', 'location_detail', 'managing_department_id', 'assigned_employee_id', 'assigned_user_name', 'parent_device_id', 'lifecycle_status', 'disposed_at', 'disposal_reason', 'is_active', 'notes', 'created_by', 'updated_by'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['category' => ItDeviceCategory::class, 'device_type' => ItDeviceType::class, 'object_kind' => ItObjectKind::class, 'lifecycle_status' => ItAssetLifecycle::class, 'disposed_at' => 'date', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $device): void {
            if (! $device->device_type || blank($device->code) || blank($device->name) || ! $device->managing_department_id) {
                throw ValidationException::withMessages(['device_type' => 'Jenis, kode, nama, dan department pengelola wajib diisi.']);
            }

            $device->category ??= $device->device_type->category();
            $device->object_kind ??= $device->device_type->kind();
            if ($device->category !== $device->device_type->category() || $device->object_kind !== $device->device_type->kind()) {
                throw ValidationException::withMessages(['device_type' => 'Kategori dan bentuk objek tidak cocok dengan jenis perangkat.']);
            }

            foreach (['asset_number', 'serial_number'] as $field) {
                $device->{$field} = filled($device->{$field}) ? trim($device->{$field}) : null;
            }

            if ($device->object_kind === ItObjectKind::Room) {
                if (! $device->work_location_id || $device->asset_number || $device->serial_number || $device->lifecycle_status || $device->assigned_employee_id || filled($device->assigned_user_name) || $device->disposed_at || filled($device->disposal_reason)) {
                    throw ValidationException::withMessages(['work_location_id' => 'Ruang wajib merujuk lokasi dan tidak memiliki identitas, pemakai, atau siklus hidup aset.']);
                }
            } else {
                $device->lifecycle_status ??= ItAssetLifecycle::Unused;
            }

            if ($device->lifecycle_status === ItAssetLifecycle::Disposed) {
                if ($device->is_active || ! $device->disposed_at || $device->disposed_at->isFuture() || blank($device->disposal_reason)) {
                    throw ValidationException::withMessages(['lifecycle_status' => 'Aset dihapuskan harus nonaktif, dengan tanggal dan alasan penghapusan.']);
                }
            } elseif ($device->disposed_at || filled($device->disposal_reason)) {
                throw ValidationException::withMessages(['disposed_at' => 'Informasi penghapusan hanya berlaku untuk aset Dihapuskan.']);
            }

            if ($device->exists && $device->isDirty(['device_type', 'category', 'object_kind']) && ($device->inspections()->exists() || $device->computerDetail()->exists())) {
                throw ValidationException::withMessages(['device_type' => 'Jenis objek yang sudah memiliki detail atau pemeriksaan tidak dapat diubah.']);
            }

            $seen = $device->exists ? [(int) $device->id] : [];
            $parentId = $device->parent_device_id;
            while ($parentId !== null) {
                if (in_array((int) $parentId, $seen, true)) {
                    throw ValidationException::withMessages(['parent_device_id' => 'Relasi induk perangkat tidak boleh membentuk siklus.']);
                }
                $seen[] = (int) $parentId;
                $parentId = self::query()->findOrFail($parentId)->parent_device_id;
            }
        });

        static::deleting(fn () => throw ValidationException::withMessages(['device' => 'Nonaktifkan objek untuk mempertahankan riwayat.']));
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        return ItInfrastructureAccess::scope($query, $user, 'it_devices.managing_department_id', ItPermission::DevicesView, ItPermission::DevicesManage);
    }

    public function scopeAssets(Builder $query): Builder
    {
        return $query->where('object_kind', '!=', ItObjectKind::Room->value);
    }

    public function managingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'managing_department_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function parentDevice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_device_id');
    }

    public function childDevices(): HasMany
    {
        return $this->hasMany(self::class, 'parent_device_id');
    }

    public function computerDetail(): HasOne
    {
        return $this->hasOne(ItDeviceComputerDetail::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ItInspection::class);
    }

    public function latestFinalizedInspection(): HasOne
    {
        return $this->hasOne(ItInspection::class)->ofMany(['checked_at' => 'max', 'id' => 'max'], fn (Builder $query) => $query->where('status', 'finalized'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function inspectionSnapshot(): array
    {
        $this->loadMissing(['assignedEmployee.department', 'computerDetail', 'managingDepartment']);

        return [
            ...$this->only(['id', 'code', 'category', 'device_type', 'object_kind', 'name', 'asset_number', 'serial_number', 'manufacturer', 'model_name', 'specifications', 'lifecycle_status', 'notes']),
            'managing_department' => $this->managingDepartment?->only(['id', 'code', 'name']),
            'assigned_employee' => $this->assignedEmployee?->only(['id', 'employee_no', 'name', 'department_id']),
            'assigned_user_name' => $this->assigned_user_name,
            'computer_detail' => $this->computerDetail?->toArray(),
        ];
    }
}
