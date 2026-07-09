<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\WorkTask;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkTask extends CreateRecord
{
    protected static string $resource = WorkTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['task_no'] = WorkTask::generateTaskNo();

        return $data;
    }
}
