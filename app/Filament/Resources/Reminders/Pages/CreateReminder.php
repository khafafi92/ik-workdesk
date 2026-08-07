<?php

namespace App\Filament\Resources\Reminders\Pages;

use App\Filament\Resources\Reminders\ReminderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReminder extends CreateRecord
{
    protected static string $resource = ReminderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ReminderResource::ensureWorkTaskSourceIsAccessible($data['work_task_id'] ?? null);

        $user = auth()->user();

        if (! $user || $user->hasRole('system-admin')) {
            return $data;
        }

        $user->loadMissing('employee');

        $data['employee_id'] = $user->employee?->id;
        $data['department_id'] = $user->employee?->department_id;

        return $data;
    }
}
