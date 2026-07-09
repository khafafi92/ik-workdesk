<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceImport extends CreateRecord
{
    protected static string $resource = AttendanceImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = auth()->id();
        $data['attendance_file_path'] = $this->normalizeFilePath($data['attendance_file_path'] ?? null);
        $data['work_hour_file_path'] = $this->normalizeFilePath($data['work_hour_file_path'] ?? null);

        if (! empty($data['attendance_file_path'])) {
            $data['attendance_file_name'] = basename($data['attendance_file_path']);
        }

        if (! empty($data['work_hour_file_path'])) {
            $data['work_hour_file_name'] = basename($data['work_hour_file_path']);
        }

        if (empty($data['status'])) {
            $data['status'] = 'uploaded';
        }

        return $data;
    }

    private function normalizeFilePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return filled($path) ? (string) $path : null;
    }
}
