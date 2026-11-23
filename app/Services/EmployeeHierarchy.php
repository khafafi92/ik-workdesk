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
            'director' => 'Director',
            'cbo' => 'CBO',
            'coo-1' => 'COO I',
            'coo-2' => 'COO II',
            'coo-3' => 'COO III',
            'specialist' => 'Specialist',
        ];
    }

    public static function validate(Employee $employee): void
    {
        if ($employee->isDirty('position_level') && $employee->position_level !== null
            && ! array_key_exists($employee->position_level, self::levels())) {
            throw ValidationException::withMessages(['position_level' => 'Select available rank.']);
        }

        if (! $employee->isDirty('supervisor_employee_id') || $employee->supervisor_employee_id === null) {
            return;
        }

        $supervisor = Employee::find($employee->supervisor_employee_id);
        if (! $supervisor || ! $supervisor->is_active) {
            throw ValidationException::withMessages(['supervisor_employee_id' => 'Choose an active direct superior.']);
        }

        $visited = $employee->exists ? [(int) $employee->id => true] : [];
        while ($supervisor) {
            if (isset($visited[$supervisor->id])) {
                throw ValidationException::withMessages(['supervisor_employee_id' => 'The relationship of the superior should not be self - appointed or in shape.']);
            }
            $visited[$supervisor->id] = true;
            $supervisor = $supervisor->supervisor;
        }
    }
}
