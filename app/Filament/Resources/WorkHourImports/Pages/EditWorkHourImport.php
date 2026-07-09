<?php

namespace App\Filament\Resources\WorkHourImports\Pages;

use App\Filament\Resources\WorkHourImports\WorkHourImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkHourImport extends EditRecord
{
    protected static string $resource = WorkHourImportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['attendance_file_path'] = null;
        $data['attendance_file_name'] = null;
        $data['work_hour_file_path'] = $this->normalizeFilePath($data['work_hour_file_path'] ?? null);
        $data['work_hour_file_name'] = $data['work_hour_file_path'] ? basename($data['work_hour_file_path']) : null;

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
