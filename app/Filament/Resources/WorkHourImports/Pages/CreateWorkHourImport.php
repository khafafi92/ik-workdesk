<?php

namespace App\Filament\Resources\WorkHourImports\Pages;

use App\Filament\Resources\WorkHourImports\WorkHourImportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkHourImport extends CreateRecord
{
    protected static string $resource = WorkHourImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = auth()->id();
        $data['attendance_file_path'] = null;
        $data['attendance_file_name'] = null;
        $data['work_hour_file_path'] = $this->normalizeFilePath($data['work_hour_file_path'] ?? null);
        $data['work_hour_file_name'] = $data['work_hour_file_path'] ? basename($data['work_hour_file_path']) : null;
        $data['status'] = $data['status'] ?? 'uploaded';

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
