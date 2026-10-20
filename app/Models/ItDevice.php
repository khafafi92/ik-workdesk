<?php

namespace App\Models;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceLocation;
use App\Enums\ItDeviceType;
use App\Enums\ItObjectKind;
use App\Enums\ItPermission;
use App\Enums\ItSwitchFloor;
use App\Rules\ItAssetNumber;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ItDevice extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'category', 'device_type', 'object_kind', 'name', 'asset_number', 'serial_number', 'manufacturer', 'model_name', 'specifications', 'work_location_id', 'location_name', 'location_detail', 'floor', 'managing_department_id', 'assigned_employee_id', 'assigned_user_name', 'parent_device_id', 'lifecycle_status', 'disposed_at', 'disposal_reason', 'is_active', 'notes', 'created_by', 'updated_by'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['category' => ItDeviceCategory::class, 'device_type' => ItDeviceType::class, 'object_kind' => ItObjectKind::class, 'location_name' => ItDeviceLocation::class, 'floor' => ItSwitchFloor::class, 'lifecycle_status' => ItAssetLifecycle::class, 'disposed_at' => 'date', 'is_active' => 'boolean'];
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

            if ($device->floor !== null && $device->device_type !== ItDeviceType::Switch) {
                throw ValidationException::withMessages(['floor' => 'LT hanya berlaku untuk perangkat Switch.']);
            }

            foreach (['asset_number', 'serial_number'] as $field) {
                $device->{$field} = filled($device->{$field}) ? trim($device->{$field}) : null;
            }
            $originalAssetNumber = $device->getRawOriginal('asset_number');
            $device->asset_number = ItAssetNumber::normalize($device->asset_number, $originalAssetNumber);
            Validator::make(['asset_number' => $device->asset_number], ['asset_number' => ['nullable', new ItAssetNumber($originalAssetNumber)]])->validate();

            if ($device->object_kind === ItObjectKind::Room) {
                if ($device->asset_number || $device->serial_number || $device->lifecycle_status || $device->assigned_employee_id || filled($device->assigned_user_name) || $device->disposed_at || filled($device->disposal_reason)) {
                    throw ValidationException::withMessages(['work_location_id' => 'Ruang tidak memiliki identitas, pemakai, atau siklus hidup aset.']);
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

            if ($device->exists && $device->isDirty(['device_type', 'category', 'object_kind']) && ! in_array($device->device_type, $device->editableDeviceTypes(), true)) {
                throw ValidationException::withMessages(['device_type' => 'Jenis objek yang sudah memiliki detail atau pemeriksaan tidak dapat diubah.']);
            }

            $seen = $device->exists ? [(int) $device->id] : [];
            $parentId = $device->parent_device_id;
            while ($parentId !== null) {
                if (in_array((int) $parentId, $seen, true)) {
                    throw ValidationException::withMessages(['parent_device_id' => 'Relasi induk perangkat tidak boleh membentuk siklus.']);
                }
                $seen[] = (int) $parentId;
                $parentId = self::withTrashed()->findOrFail($parentId)->parent_device_id;
            }
        });

        static::deleting(function (self $device): void {
            if ($device->isForceDeleting()) {
                throw ValidationException::withMessages(['device' => 'Penghapusan permanen tidak tersedia agar riwayat pemeriksaan tetap tersimpan.']);
            }
        });
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        return ItInfrastructureAccess::scope($query, $user, 'it_devices.managing_department_id', ItPermission::DevicesView, ItPermission::DevicesManage);
    }

    public function editableDeviceTypes(): array
    {
        if (! $this->exists || (! $this->inspections()->exists() && ! $this->computerDetail()->exists())) {
            return ItDeviceType::cases();
        }

        $original = ItDeviceType::from($this->getRawOriginal('device_type'));

        return in_array($original, [ItDeviceType::Laptop, ItDeviceType::Pc], true)
            ? [ItDeviceType::Laptop, ItDeviceType::Pc]
            : [$original];
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
        return $this->belongsTo(self::class, 'parent_device_id')->withTrashed();
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

    public function transfers(): HasMany
    {
        return $this->hasMany(ItDeviceTransfer::class);
    }

    public function assignmentSnapshot(): array
    {
        return [
            'employee_id' => $this->assigned_employee_id,
            'name' => $this->assigneeLabel(),
            'lifecycle_status' => $this->lifecycle_status?->value,
        ];
    }

    public function assignmentVersion(): string
    {
        return hash('sha256', json_encode([
            $this->assigned_employee_id, $this->assigned_user_name,
            $this->lifecycle_status?->value, $this->transfers()->max('id'),
        ], JSON_THROW_ON_ERROR));
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
            'location_name' => $this->location_name?->value,
            ...$this->only(['id', 'code', 'category', 'device_type', 'object_kind', 'name', 'asset_number', 'serial_number', 'manufacturer', 'model_name', 'specifications', 'floor', 'lifecycle_status', 'is_active', 'disposed_at', 'disposal_reason', 'notes']),
            'managing_department' => $this->managingDepartment?->only(['id', 'code', 'name']),
            'assigned_employee' => $this->assignedEmployee ? [...$this->assignedEmployee->only(['id', 'employee_no', 'name', 'department_id', 'position']), 'division' => $this->assignedEmployee->department?->name] : null,
            'assigned_user_name' => $this->assigned_user_name,
            'computer_detail' => $this->computerDetail?->toArray(),
        ];
    }

    public function assigneeLabel(): string
    {
        return self::assigneeLabelFor([
            'assigned_user_name' => filled($this->assigned_user_name) ? $this->assigned_user_name : $this->assignedEmployee?->name,
            'object_kind' => $this->object_kind->value,
            'lifecycle_status' => $this->lifecycle_status?->value,
        ]);
    }

    public static function assigneeLabelFor(array $snapshot): string
    {
        foreach ([$snapshot['assigned_user_name'] ?? null, $snapshot['assigned_employee']['name'] ?? null] as $name) {
            if (filled($name)) {
                return $name;
            }
        }

        return match (true) {
            ($snapshot['object_kind'] ?? null) === 'room' => 'Tidak berlaku',
            ($snapshot['lifecycle_status'] ?? null) === 'unused' => 'Stok (belum dialokasikan)',
            default => 'Belum ditetapkan',
        };
    }
}
