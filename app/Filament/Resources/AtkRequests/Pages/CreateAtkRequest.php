<?php

namespace App\Filament\Resources\AtkRequests\Pages;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkRequest;
use App\Models\Department;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAtkRequest extends CreateRecord
{
    protected static string $resource = AtkRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $user?->loadMissing('employee');
        $departmentId = $user?->employee?->department_id;

        if (! $user || ! $departmentId) {
            throw ValidationException::withMessages([
                'purpose' => 'Akun Anda belum terhubung ke departemen employee.',
            ]);
        }

        if (blank($data['permit_company_id'] ?? null)) {
            throw ValidationException::withMessages([
                'permit_company_id' => 'Entitas peminta wajib dipilih.',
            ]);
        }

        $department = Department::query()->findOrFail($departmentId);

        return [
            ...$data,
            'request_number' => AtkRequest::generateRequestNumber($department),
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'department_id' => $department->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
