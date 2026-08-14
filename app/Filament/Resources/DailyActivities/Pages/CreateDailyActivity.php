<?php

namespace App\Filament\Resources\DailyActivities\Pages;

use App\Filament\Resources\DailyActivities\DailyActivityResource;
use App\Services\DailyActivityDataService;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyActivity extends CreateRecord
{
    protected static string $resource = DailyActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        DailyActivityResource::ensureWorkTaskSourceIsAccessible($data['work_task_id'] ?? null);
        $data['user_id'] = auth()->id();

        return app(DailyActivityDataService::class)->normalize($data);
    }

    protected function getRedirectUrl(): string
    {
        return DailyActivityResource::getUrl('index');
    }
}
