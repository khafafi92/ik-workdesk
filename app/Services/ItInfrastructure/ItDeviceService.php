<?php

namespace App\Services\ItInfrastructure;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItDeviceType;
use App\Enums\ItLicenseStatus;
use App\Enums\ItSwitchFloor;
use App\Models\Employee;
use App\Models\ItDevice;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItDeviceService
{
    public function save(User $actor, array $data, ?ItDevice $device = null): ItDevice
    {
        try {
            return DB::transaction(function () use ($actor, $data, $device): ItDevice {
                $device = $device ? ItDevice::query()->lockForUpdate()->findOrFail($device->id) : new ItDevice;
                Gate::forUser($actor)->authorize($device->exists ? 'update' : 'create', $device->exists ? $device : ItDevice::class);
                $type = is_string($data['device_type'] ?? null) ? ItDeviceType::tryFrom($data['device_type']) : null;
                $room = $type === ItDeviceType::ServerRoom;
                $computer = in_array($type, [ItDeviceType::Laptop, ItDeviceType::Pc], true);
                $rules = [
                    'code' => ['required', 'string', 'max:80', Rule::unique('it_devices', 'code')->ignore($device->id)],
                    'name' => ['required', 'string', 'max:255'],
                    'device_type' => ['required', Rule::enum(ItDeviceType::class)],
                    'managing_department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
                    'work_location_id' => [$room ? 'required' : 'nullable', 'integer', Rule::exists('work_locations', 'id')],
                    'asset_number' => ['nullable', 'string', 'max:255', Rule::unique('it_devices', 'asset_number')->ignore($device->id)],
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
                        $rules[$product.'_verification_notes'] = [($data[$product.'_license_status'] ?? 'unverified') === 'unverified' ? 'nullable' : 'required', 'string', 'max:3000'];
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
                $device->fill(Arr::only($validated, ['code', 'name', 'device_type', 'managing_department_id', 'work_location_id', 'location_detail', 'floor', 'asset_number', 'serial_number', 'specifications', 'lifecycle_status', 'assigned_employee_id', 'assigned_user_name', 'is_active', 'notes', 'disposed_at', 'disposal_reason']));
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
                        $notes = $status === 'unverified' ? null : $validated[$product.'_verification_notes'];
                        $changed = ! $detail->exists || $detail->{$product.'_license_status'}?->value !== $status || $detail->{$product.'_verification_notes'} !== $notes;
                        $detail->{$product.'_license_status'} = $status;
                        $detail->{$product.'_verification_notes'} = $notes;
                        if ($changed || $status === 'unverified') {
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
