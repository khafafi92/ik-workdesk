<?php

namespace App\Filament\Resources\Reminders\Pages;

use App\Filament\Resources\Reminders\ReminderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReminder extends CreateRecord
{
    protected static string $resource = ReminderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('superadmin')) {
            return $data;
        }

        $user->loadMissing('employee');

        $data['employee_id'] = $user->employee?->id;
        $data['department_id'] = $user->employee?->department_id;

        return $data;
    }
}