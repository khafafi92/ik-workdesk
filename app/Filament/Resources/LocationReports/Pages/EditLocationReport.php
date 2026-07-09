<?php

namespace App\Filament\Resources\LocationReports\Pages;

use App\Filament\Resources\LocationReports\LocationReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocationReport extends EditRecord
{
    protected static string $resource = LocationReportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['attendance_file_path'] = $this->normalizeFilePath($data['attendance_file_path'] ?? null);
        $data['attendance_file_name'] = $data['attendance_file_path'] ? basename($data['attendance_file_path']) : null;
        $data['work_hour_file_path'] = null;
        $data['work_hour_file_name'] = null;

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
