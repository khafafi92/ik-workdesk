<?php

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Models\Department;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateOutgoingLetter extends CreateRecord
{
    protected static string $resource = OutgoingLetterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (OutgoingLetterResource::usesApcaGeneralNumbering($data['letter_profile_id'] ?? null, $data['permit_company_id'] ?? null)) {
            if (auth()->user()?->is_admin === true) {
                $departmentId = Department::query()
                    ->whereKey($data['department_id'] ?? null)
                    ->where('is_active', true)
                    ->value('id');

                if ($departmentId === null) {
                    throw ValidationException::withMessages([
                        'department_id' => 'Select active department for APCA mail.',
                    ]);
                }

                $data['department_id'] = $departmentId;

                return $data;
            }

            $departmentId = OutgoingLetterResource::creatorDepartmentId();

            if ($departmentId === null) {
                throw ValidationException::withMessages([
                    'department_id' => 'The mailmaker\'s account must have an active department to make an APCA number.',
                ]);
            }

            $data['department_id'] = $departmentId;
        }

        return $data;
    }

    public function getSubheading(): ?string
    {
        return __('File mail in order, save as a draft, and then publish after data and preview numbers are checked.');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Draft mail saved. Check back before publishing it.');
    }
}
