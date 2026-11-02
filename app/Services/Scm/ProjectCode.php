<?php

namespace App\Services\Scm;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectCode
{
    public const WORK_CODES = ['SP' => 'SP - Supply', 'SC' => 'SC - Services', 'SPC' => 'SPC - Supply & Services'];

    public static function defaults(): array
    {
        return ['format' => 'standard', 'project_prefix' => '', 'awarded_year' => today()->year, 'work_code' => '', 'running_number' => '',
            'code' => '', 'name' => '', 'is_active' => true, 'version' => 0];
    }

    public static function parse(string $code): ?array
    {
        if (! preg_match('~^([A-Z]{4}(?:-[A-Z0-9]+)*)/(\d{2})/(SP|SC|SPC)/(\d{3})$~', $code, $parts) || (int) $parts[4] === 0) {
            return null;
        }

        return ['format' => 'standard', 'project_prefix' => $parts[1], 'awarded_year' => 2000 + (int) $parts[2],
            'work_code' => $parts[3], 'running_number' => $parts[4]];
    }

    public static function make(array $data): string
    {
        $format = $data['format'] ?? 'legacy';
        if ($format === 'legacy') {
            $data['code'] = strtoupper(trim((string) ($data['code'] ?? '')));
            $validated = Validation::make($data, ['code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/']], [], ['code' => 'kode lama / khusus'])->validate();

            return $validated['code'];
        }

        $data['project_prefix'] = strtoupper(trim((string) ($data['project_prefix'] ?? '')));
        if (is_string($data['running_number'] ?? null) && preg_match('/^\d{1,3}$/', trim($data['running_number']))) {
            $data['running_number'] = (int) trim($data['running_number']);
        }
        $validated = Validation::make([...$data, 'format' => $format], [
            'format' => ['required', Rule::in(['standard'])],
            'project_prefix' => ['required', 'string', 'max:60', 'regex:/^[A-Z]{4}(?:-[A-Z0-9]+)*$/'],
            'awarded_year' => 'required|integer|between:2000,2099',
            'work_code' => ['required', Rule::in(array_keys(self::WORK_CODES))],
            'running_number' => 'required|integer|between:1,999',
        ], [], ['project_prefix' => 'singkatan Project', 'awarded_year' => 'tahun awarded', 'work_code' => 'jenis pekerjaan', 'running_number' => 'nomor urut Project'])->validate();

        return $validated['project_prefix'].'/'.substr((string) $validated['awarded_year'], -2).'/'.$validated['work_code'].'/'.str_pad((string) (int) $validated['running_number'], 3, '0', STR_PAD_LEFT);
    }

    public static function preview(array $data): ?string
    {
        try {
            return self::make($data);
        } catch (ValidationException) {
            return null;
        }
    }
}
