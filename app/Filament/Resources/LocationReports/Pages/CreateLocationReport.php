<?php

namespace App\Filament\Resources\LocationReports\Pages;

use App\Filament\Resources\LocationReports\LocationReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocationReport extends CreateRecord
{
    protected static string $resource = LocationReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = auth()->id();
        $data['attendance_file_path'] = $this->normalizeFilePath($data['attendance_file_path'] ?? null);
        $data['attendance_file_name'] = $data['attendance_file_path'] ? basename($data['attendance_file_path']) : null;
        $data['work_hour_file_path'] = null;
        $data['work_hour_file_name'] = null;
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
