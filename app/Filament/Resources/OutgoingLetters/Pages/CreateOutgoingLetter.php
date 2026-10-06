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
                        'department_id' => 'Pilih departemen aktif untuk surat APCA.',
                    ]);
                }

                $data['department_id'] = $departmentId;

                return $data;
            }

            $departmentId = OutgoingLetterResource::creatorDepartmentId();

            if ($departmentId === null) {
                throw ValidationException::withMessages([
                    'department_id' => 'Akun pembuat surat harus memiliki departemen aktif untuk membuat nomor APCA.',
                ]);
            }

            $data['department_id'] = $departmentId;
        }

        return $data;
    }

    public function getSubheading(): ?string
    {
        return 'Isi data surat secara berurutan, simpan sebagai draft, lalu terbitkan setelah data dan preview nomor diperiksa.';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft surat tersimpan. Periksa kembali sebelum menerbitkannya.';
    }
}
