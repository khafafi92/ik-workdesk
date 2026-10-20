<?php

namespace App\Services\ItInfrastructure;

use App\Enums\ItInspectionStatus;
use App\Enums\ItMonitoringType;
use App\Models\Department;
use App\Models\ItDailyMonitoring;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItDailyMonitoringService
{
    public static function departmentId(): ?int
    {
        return Department::query()->whereRaw('lower(trim(code)) = ?', ['it'])->value('id');
    }

    public static function validationMessages(string $prefix = ''): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_unless' => ':attribute wajib diisi setelah hasil pemeriksaan dipilih.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => 'Pilihan :attribute tidak valid.',
            'string' => ':attribute harus berupa teks.',
            'array' => 'Daftar :attribute tidak valid.',
            'in' => 'Pilih :attribute yang tersedia.',
            'enum' => 'Pilih jenis monitoring yang tersedia.',
            'exists' => ':attribute tidak ditemukan.',
            'min' => ['numeric' => ':attribute minimal :min.', 'array' => 'Tambahkan setidaknya satu pemeriksaan.'],
            'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.', 'array' => 'Maksimal :max pemeriksaan dalam satu catatan.'],
            'between' => ':attribute harus antara :min dan :max.',
            'date' => ':attribute harus berupa tanggal dan waktu yang valid.',
            'date_format' => 'Format :attribute tidak sesuai.',
            $prefix.'checked_at.before_or_equal' => 'Waktu pengecekan tidak boleh melebihi waktu sekarang.',
            $prefix.'backup_date.before_or_equal' => 'Tanggal backup tidak boleh melewati waktu pengecekan.',
            $prefix.'checks.*.started_at.after_or_equal' => 'Waktu mulai backup tidak boleh sebelum tanggal backup.',
            $prefix.'checks.*.started_at.before_or_equal' => 'Waktu mulai backup tidak boleh melewati waktu pengecekan.',
            $prefix.'checks.*.completed_at.after_or_equal' => 'Waktu selesai backup tidak boleh sebelum waktu mulai.',
            $prefix.'checks.*.completed_at.before_or_equal' => 'Waktu selesai backup tidak boleh melewati waktu pengecekan.',
            $prefix.'checks.*.name.distinct' => 'Nama link, job backup, atau VM tidak boleh berulang dalam satu catatan.',
        ];
    }

    public function save(User $actor, array $data, ?int $id = null): ItDailyMonitoring
    {
        return DB::transaction(function () use ($actor, $data, $id): ItDailyMonitoring {
            $record = $id ? ItDailyMonitoring::query()->lockForUpdate()->findOrFail($id) : new ItDailyMonitoring;
            Gate::forUser($actor)->authorize($id ? 'update' : 'create', $id ? $record : ItDailyMonitoring::class);
            $data['managing_department_id'] = self::departmentId();
            $validated = $this->validate($data);
            abort_unless($actor->canAccessDepartment((int) $validated['managing_department_id']), 403);
            if ($id && ((int) ($data['revision'] ?? 0) !== $record->revision)) {
                throw ValidationException::withMessages(['data.source_name' => 'Catatan sudah diperbarui. Muat ulang halaman sebelum menyimpan.']);
            }
            if ($id && ((int) $validated['managing_department_id'] !== (int) $record->managing_department_id || $validated['monitoring_type'] !== $record->monitoring_type->value)) {
                throw ValidationException::withMessages(['data.monitoring_type' => 'Jenis monitoring dan department catatan tersimpan tidak dapat diganti.']);
            }
            $record->fill(collect($validated)->except('finalize')->all());
            if (! $id) {
                $record->inspector_id = $actor->id;
                $record->inspector_name = $actor->name;
            } else {
                $record->revision++;
            }
            $record->updated_by = $actor->id;
            if ($validated['finalize'] ?? false) {
                $record->status = ItInspectionStatus::Finalized;
                $record->finalized_at = now();
                $record->finalized_by = $actor->id;
            }
            $record->save();

            return $record->refresh();
        });
    }

    private function validate(array $data): array
    {
        if (is_array($data['checks'] ?? null)) {
            foreach ($data['checks'] as &$check) {
                if (is_array($check) && is_string($check['name'] ?? null)) {
                    $check['name'] = trim($check['name']);
                }
            }
            unset($check);
        }
        $type = ItMonitoringType::tryFrom((string) ($data['monitoring_type'] ?? ''));
        $rules = [
            'managing_department_id' => ['required', 'integer', 'exists:departments,id'],
            'monitoring_type' => ['required', Rule::enum(ItMonitoringType::class)],
            'source_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'checked_at' => ['required', 'date', 'before_or_equal:now'],
            'backup_date' => $type === ItMonitoringType::Synology ? ['required', 'date_format:Y-m-d', 'before_or_equal:checked_at'] : ['exclude'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'finalize' => ['sometimes', 'boolean'],
            'checks' => ['required', 'array', 'min:1', 'max:500'],
            'checks.*' => ['required', 'array'],
            'checks.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'checks.*.status' => ['required', Rule::in(array_keys($type?->statuses() ?? []))],
            'checks.*.notes' => ['nullable', 'string', 'max:3000'],
        ];
        $fields = match ($type) {
            ItMonitoringType::Firewall => [
                'latency_ms' => ['nullable', 'numeric', 'min:0'],
                'packet_loss_percent' => ['nullable', 'numeric', 'between:0,100'],
                'test_target' => ['required_unless:checks.*.status,unchecked', 'nullable', 'string', 'max:255'],
            ],
            ItMonitoringType::Synology => [
                'data_present' => ['required_unless:checks.*.status,unchecked', 'nullable', Rule::in(['yes', 'no', 'unverified'])],
                'started_at' => ['nullable', 'date', 'after_or_equal:backup_date', 'before_or_equal:checked_at'],
                'completed_at' => ['nullable', 'date', 'after_or_equal:checks.*.started_at', 'before_or_equal:checked_at'],
                'data_path' => ['nullable', 'string', 'max:1000'],
                'data_size' => ['nullable', 'string', 'max:100'],
            ],
            ItMonitoringType::Vsphere => [
                'expected_power_state' => ['required', Rule::in(['powered_on', 'powered_off'])],
                'health' => ['required_unless:checks.*.status,unchecked', 'nullable', Rule::in(['normal', 'warning', 'critical'])],
                'cpu_percent' => ['nullable', 'numeric', 'between:0,100'],
                'ram_percent' => ['nullable', 'numeric', 'between:0,100'],
                'disk_free_percent' => ['nullable', 'numeric', 'between:0,100'],
            ],
            default => [],
        };
        foreach ($fields as $field => $definition) {
            $rules['checks.*.'.$field] = $definition;
        }
        $validator = Validator::make($data, $rules, static::validationMessages(), [
            'managing_department_id' => 'Department IT', 'monitoring_type' => 'Jenis monitoring',
            'source_name' => 'Nama sistem / perangkat', 'location' => 'Lokasi', 'checked_at' => 'Waktu pengecekan',
            'backup_date' => 'Tanggal backup', 'notes' => 'Catatan pemeriksaan', 'checks' => 'Pemeriksaan',
            'checks.*.name' => match ($type) {
                ItMonitoringType::Firewall => 'Nama ISP / link', ItMonitoringType::Synology => 'Nama job backup', default => 'Nama VM',
            },
            'checks.*.status' => 'Hasil pemeriksaan', 'checks.*.notes' => 'Masalah / tindak lanjut',
            'checks.*.test_target' => 'Target uji koneksi', 'checks.*.latency_ms' => 'Latency (ms)', 'checks.*.packet_loss_percent' => 'Packet loss (%)',
            'checks.*.data_present' => 'Verifikasi data backup', 'checks.*.started_at' => 'Waktu mulai backup', 'checks.*.completed_at' => 'Waktu selesai backup',
            'checks.*.data_path' => 'Folder / restore point yang diperiksa', 'checks.*.data_size' => 'Ukuran data backup',
            'checks.*.expected_power_state' => 'Status daya yang diharapkan', 'checks.*.health' => 'Kondisi VM / alarm',
            'checks.*.cpu_percent' => 'Penggunaan CPU (%)', 'checks.*.ram_percent' => 'Penggunaan RAM (%)', 'checks.*.disk_free_percent' => 'Ruang disk tersisa (%)',
        ]);
        $validator->after(function ($validator) use ($data, $type): void {
            if (! $type || ! is_array($data['checks'] ?? null)) {
                return;
            }
            foreach ($data['checks'] as $key => $check) {
                if (! is_array($check)) {
                    continue;
                }
                if (($data['finalize'] ?? false) && ($check['status'] ?? null) === 'unchecked') {
                    $validator->errors()->add('checks.'.$key.'.status', 'Periksa seluruh baris sebelum finalisasi.');
                }
                if ($type->needsAttention($check) && blank($check['notes'] ?? null)) {
                    $validator->errors()->add('checks.'.$key.'.notes', 'Jelaskan masalah atau tindak lanjut pada baris ini.');
                }
                if ($type === ItMonitoringType::Synology) {
                    foreach (['started_at', 'completed_at'] as $field) {
                        if (($check['status'] ?? null) === 'success' && blank($check[$field] ?? null)) {
                            $validator->errors()->add('checks.'.$key.'.'.$field, 'Waktu mulai dan selesai wajib dicatat untuk backup berhasil.');
                        }
                    }
                    if (($check['data_present'] ?? null) === 'yes' && blank($check['data_path'] ?? null)) {
                        $validator->errors()->add('checks.'.$key.'.data_path', 'Catat folder atau restore point yang diperiksa.');
                    }
                }
            }
        });
        try {
            $validated = $validator->validate();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key): array => ['data.'.$key => $messages])->all());
        }
        $allowed = ['name', 'status', 'notes', ...array_keys($fields)];
        $validated['checks'] = array_values(array_map(fn (array $check): array => collect($check)->only($allowed)->all(), $validated['checks']));
        $validated['backup_date'] = $type === ItMonitoringType::Synology ? $validated['backup_date'] : null;

        return $validated;
    }
}
