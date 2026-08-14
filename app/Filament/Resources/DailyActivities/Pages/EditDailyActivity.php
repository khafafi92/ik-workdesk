<?php

namespace App\Filament\Resources\DailyActivities\Pages;

use App\Filament\Resources\DailyActivities\DailyActivityResource;
use App\Services\DailyActivityDataService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyActivity extends EditRecord
{
    protected static string $resource = DailyActivityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        DailyActivityResource::ensureWorkTaskSourceIsAccessible($data['work_task_id'] ?? null);

        $data['user_id'] = $this->record->user_id;

        return app(DailyActivityDataService::class)->normalize($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return DailyActivityResource::getUrl('index');
    }
}
