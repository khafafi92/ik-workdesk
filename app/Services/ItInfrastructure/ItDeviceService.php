<?php

namespace App\Services\ItInfrastructure;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceLocation;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Enums\ItSwitchFloor;
use App\Models\Employee;
use App\Models\ItDevice;
use App\Models\User;
use App\Rules\ItAssetNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItDeviceService
{
    public function delete(User $actor, ItDevice $device): bool
    {
        return DB::transaction(function () use ($actor, $device): bool {
            $device = ItDevice::query()->lockForUpdate()->findOrFail($device->id);
            Gate::forUser($actor)->authorize('delete', $device);
            $device->computerDetail()->delete();

            return (bool) $device->delete();
        });
    }

    public function save(User $actor, array $data, ?ItDevice $device = null): ItDevice
    {
        try {
            return DB::transaction(function () use ($actor, $data, $device): ItDevice {
                $device = $device ? ItDevice::query()->lockForUpdate()->findOrFail($device->id) : new ItDevice;
                Gate::forUser($actor)->authorize($device->exists ? 'update' : 'create', $device->exists ? $device : ItDevice::class);
                $data['code'] ??= $device->code ?? 'IT-'.Str::ulid();
                if (array_key_exists('asset_number', $data)) {
                    $data['asset_number'] = ItAssetNumber::normalize($data['asset_number'], $device->getRawOriginal('asset_number'));
                }
                if (! array_key_exists('location_name', $data)) {
                    $data['location_name'] = $device->location_name?->value;
                }
                if (blank($data['location_name'])) {
                    $data['location_name'] = null;
                }
                $data['work_location_id'] = filled($data['location_name']) ? null : $device->work_location_id;
                $type = is_string($data['device_type'] ?? null) ? ItDeviceType::tryFrom($data['device_type']) : null;
                $room = $type === ItDeviceType::ServerRoom;
                $computer = in_array($type, [ItDeviceType::Laptop, ItDeviceType::Pc], true);
                $data['asset_number'] ??= $device->asset_number;
                $generateAssetNumber = ! $room && blank($data['asset_number']) && (! $device->exists || filled($data['asset_company'] ?? null));
                $rules = [
                    'code' => ['required', 'string', 'max:80', Rule::unique('it_devices', 'code')->ignore($device->id)],
                    'name' => ['required', 'string', 'max:255'],
                    'device_type' => ['required', Rule::enum(ItDeviceType::class)],
                    'managing_department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
                    'work_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')],
                    'location_name' => [$room && ! $device->work_location_id ? 'required' : 'nullable', Rule::enum(ItDeviceLocation::class)],
                    'asset_number' => ['nullable', 'string', 'max:255', new ItAssetNumber($device->getRawOriginal('asset_number')), Rule::unique('it_devices', 'asset_number')->ignore($device->id), function (string $attribute, mixed $value, \Closure $fail) use ($device): void {
                        if (! is_string($value) || $value === $device->getRawOriginal('asset_number') || ! preg_match('~/IT/~', $value)) {
                            return;
                        }
                        $legacy = str_replace('/IT/', '//IT/', $value);
                        if (ItDevice::query()->where('asset_number', $legacy)->where('id', '!=', $device->id ?? 0)->exists()) {
                            $fail('Nomor Aset sudah digunakan oleh perangkat lain.');
                        }
                    }],
                    'asset_company' => [$generateAssetNumber ? 'required' : 'nullable', 'in:APCA,KPMOG'],
                    'asset_year' => [$generateAssetNumber ? 'required' : 'nullable', 'integer', 'between:1000,9999'],
                    'serial_number' => ['nullable', 'string', 'max:255'],
                    'lifecycle_status' => [$room ? 'nullable' : 'required', Rule::enum(ItAssetLifecycle::class)],
                    'is_active' => ['required', 'boolean'],
                    'specifications' => ['nullable', 'string', 'max:6000'],
                    'notes' => ['nullable', 'string', 'max:3000'],
                    'location_detail' => ['nullable', 'string', 'max:255'],
                    'floor' => ['nullable', Rule::enum(ItSwitchFloor::class)],
                    'assigned_user_name' => ['nullable', 'string', 'max:255'],
                    'assigned_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
                    'disposed_at' => ['nullable', 'date', 'before_or_equal:today'],
                    'disposal_reason' => ['nullable', 'string', 'max:3000'],
                ];
                if ($computer) {
                    foreach (['windows', 'office'] as $product) {
                        $rules[$product.'_license_status'] = ['required', Rule::enum(ItLicenseStatus::class)];
                    }
                    foreach (['operating_system', 'windows_version', 'office_version'] as $field) {
                        $rules[$field] = ['nullable', 'string', 'max:255'];
                    }
                    $rules += [
                        'ram_gb' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
                        'storage_devices' => ['nullable', 'array', 'max:8'],
                        'storage_devices.*.type' => ['required', 'in:hdd,ssd,nvme'],
                        'storage_devices.*.capacity_gb' => ['required', 'numeric', 'gt:0'],
                    ];
                }
                $validated = Validator::make($data, $rules)->validate();
                Gate::forUser($actor)->authorize('assignDepartment', [ItDevice::class, (int) $validated['managing_department_id']]);
                if ($generateAssetNumber) {
                    $validated['asset_number'] = app(ItAssetNumberGenerator::class)->next($validated['asset_company'], (int) $validated['asset_year']);
                } elseif (! $room && filled($validated['asset_number']) && $validated['asset_number'] !== $device->getRawOriginal('asset_number')) {
                    app(ItAssetNumberGenerator::class)->reserve($validated['asset_number']);
                }
                if ($type !== ItDeviceType::Switch) {
                    $validated['floor'] = null;
                } elseif (array_key_exists('floor', $validated) && blank($validated['floor'])) {
                    $validated['floor'] = null;
                }
                if ($room) {
                    foreach (['asset_number', 'serial_number', 'lifecycle_status', 'assigned_employee_id', 'assigned_user_name', 'disposed_at', 'disposal_reason'] as $field) {
                        $validated[$field] = null;
                    }
                } elseif (filled($validated['assigned_employee_id'] ?? null)) {
                    $employee = Employee::query()->with('user')->findOrFail($validated['assigned_employee_id']);
                    if ((! $employee->is_active || ! $employee->user) && (int) $device->assigned_employee_id !== $employee->id) {
                        throw ValidationException::withMessages(['assigned_employee_id' => 'Pilih user terdaftar dengan data karyawan aktif.']);
                    }
                    $validated['assigned_user_name'] = $employee->user?->name ?? $employee->name;
                }
                $device->fill(Arr::only($validated, ['code', 'name', 'device_type', 'managing_department_id', 'work_location_id', 'location_name', 'location_detail', 'floor', 'asset_number', 'serial_number', 'specifications', 'lifecycle_status', 'assigned_employee_id', 'assigned_user_name', 'is_active', 'notes', 'disposed_at', 'disposal_reason']));
                $device->category = $type->category();
                $device->object_kind = $type->kind();
                $device->created_by ??= $actor->id;
                $device->updated_by = $actor->id;
                $device->save();
                if ($computer) {
                    $detail = $device->computerDetail()->firstOrNew();
                    $detail->fill(Arr::only($validated, ['operating_system', 'windows_version', 'office_version', 'ram_gb', 'storage_devices']));
                    foreach (['windows', 'office'] as $product) {
                        $status = $validated[$product.'_license_status'];
                        $changed = ! $detail->exists || $detail->{$product.'_license_status'}?->value !== $status;
                        $detail->{$product.'_license_status'} = $status;
                        if ($changed || $status === 'unverified') {
                            $detail->{$product.'_verification_notes'} = null;
                            $detail->{$product.'_verified_by'} = $status === 'unverified' ? null : $actor->id;
                            $detail->{$product.'_verified_at'} = $status === 'unverified' ? null : now();
                        }
                    }
                    $detail->save();
                }

                return $device->refresh();
            });
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }
}
