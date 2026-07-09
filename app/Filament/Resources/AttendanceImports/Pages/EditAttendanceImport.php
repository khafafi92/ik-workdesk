<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceImport extends EditRecord
{
    protected static string $resource = AttendanceImportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['attendance_file_path'] = $this->normalizeFilePath($data['attendance_file_path'] ?? null);
        $data['work_hour_file_path'] = $this->normalizeFilePath($data['work_hour_file_path'] ?? null);

        if (! empty($data['attendance_file_path'])) {
            $data['attendance_file_name'] = basename($data['attendance_file_path']);
        }

        if (! empty($data['work_hour_file_path'])) {
            $data['work_hour_file_name'] = basename($data['work_hour_file_path']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    private function normalizeFilePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return filled($path) ? (string) $path : null;
    }
}
