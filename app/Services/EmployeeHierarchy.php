<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class EmployeeHierarchy
{
    public static function levels(): array
    {
        return [
            'chairman' => 'Chairman',
            'vice-chairman' => 'Vice Chairman',
            'staff' => 'Staff',
            'supervisor' => 'SPV',
            'manager' => 'Manager',
            'general-manager' => 'GM',
            'director' => 'Direktur',
            'cbo' => 'CBO',
            'coo-1' => 'COO I',
            'coo-2' => 'COO II',
            'coo-3' => 'COO III',
            'specialist' => 'Spesialis',
        ];
    }

    public static function validate(Employee $employee): void
    {
        if ($employee->isDirty('position_level') && $employee->position_level !== null
            && ! array_key_exists($employee->position_level, self::levels())) {
            throw ValidationException::withMessages(['position_level' => 'Pilih jenjang jabatan yang tersedia.']);
        }

        if (! $employee->isDirty('supervisor_employee_id') || $employee->supervisor_employee_id === null) {
            return;
        }

        $supervisor = Employee::find($employee->supervisor_employee_id);
        if (! $supervisor || ! $supervisor->is_active) {
            throw ValidationException::withMessages(['supervisor_employee_id' => 'Pilih atasan langsung yang aktif.']);
        }

        $visited = $employee->exists ? [(int) $employee->id => true] : [];
        while ($supervisor) {
            if (isset($visited[$supervisor->id])) {
                throw ValidationException::withMessages(['supervisor_employee_id' => 'Hubungan atasan tidak boleh menunjuk diri sendiri atau membentuk putaran.']);
            }
            $visited[$supervisor->id] = true;
            $supervisor = $supervisor->supervisor;
        }
    }
}
