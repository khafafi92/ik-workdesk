<?php

namespace App\Filament\Resources\Reminders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReminderForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        $isSuperadmin = $user?->hasRole('superadmin') ?? false;

        $user?->loadMissing('employee');

        $employeeId = $user?->employee?->id;
        $departmentId = $user?->employee?->department_id;

        return $schema
            ->components([
                Select::make('reminder_type')
                    ->label('Reminder Type')
                    ->options([
                        'meeting' => 'Meeting',
                        'task' => 'Task',
                        'service_request' => 'Service Desk',
                        'report' => 'Report',
                        'general' => 'General',
                    ])
                    ->default('general')
                    ->required(),

                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->columnSpanFull(),

                Select::make('employee_id')
                    ->label('Reminder For Employee')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible($isSuperadmin),

                Hidden::make('employee_id')
                    ->default($employeeId)
                    ->dehydrated(! $isSuperadmin),

                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible($isSuperadmin),

                Hidden::make('department_id')
                    ->default($departmentId)
                    ->dehydrated(! $isSuperadmin),

                DateTimePicker::make('reminder_at')
                    ->label('Reminder At')
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'done' => 'Done',
                        'cancel' => 'Cancel',
                    ])
                    ->default('pending')
                    ->required(),
            ]);
    }
}