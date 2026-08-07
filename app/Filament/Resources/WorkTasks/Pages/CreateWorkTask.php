<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\WorkTask;
use App\Services\WorkTaskMutationGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkTask extends CreateRecord
{
    protected static string $resource = WorkTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = app(WorkTaskMutationGuard::class)->validate(
            auth()->user(),
            $data
        );
        $data['task_no'] = WorkTask::generateTaskNo();

        return $data;
    }
}
