<?php

namespace App\Filament\Resources\Reminders\Schemas;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\WorkTask;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                Select::make('work_task_id')
                    ->label('Source Task (Optional)')
                    ->relationship(
                        name: 'workTask',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereIn(
                                'work_tasks.id',
                                WorkTaskResource::getEloquentQuery()
                                    ->select('work_tasks.id')
                            )
                            ->latest('work_tasks.created_at')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (WorkTask $task): string => "{$task->task_no} · {$task->title}"
                    )
                    ->searchable(['task_no', 'title'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $task = WorkTaskResource::getEloquentQuery()->find($state);

                        if (! $task) {
                            return;
                        }

                        $set('reminder_type', 'task');
                        $set('title', "[{$task->task_no}] {$task->title}");
                        $set('description', $task->description);

                        if (filled($task->due_at)) {
                            $set('reminder_at', $task->due_at);
                        }
                    })
                    ->helperText('Pilih task yang ingin diingatkan, atau kosongkan untuk reminder manual.')
                    ->columnSpanFull(),

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
                    ->label('Deadline / Reminder At')
                    ->helperText('Email alarm akan dikirim sebelum tanggal ini selama status masih Pending.')
                    ->required(),

                CheckboxList::make('email_alarm_days')
                    ->label('Email Alarm')
                    ->options([
                        3 => '3 hari sebelum deadline (H-3)',
                        1 => '1 hari sebelum deadline (H-1)',
                    ])
                    ->default([3, 1])
                    ->columns(2)
                    ->helperText('Pilih satu atau beberapa waktu pengingat. Kosongkan jika tidak ingin menerima email.')
                    ->columnSpanFull(),

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
