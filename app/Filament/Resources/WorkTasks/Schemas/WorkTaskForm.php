<?php

namespace App\Filament\Resources\WorkTasks\Schemas;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

use App\Models\Employee;
use App\Models\WorkTask;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class WorkTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                |--------------------------------------------------------------------------
                | SOURCE SERVICE DESK
                |--------------------------------------------------------------------------
                | Hanya muncul jika Work Log terhubung dengan Service Desk.
                */
                Section::make('Source Service Desk')
                    ->description(
                        'Informasi permintaan asli. Attachment hanya dapat dikelola dari menu Service Desk.'
                    )
                    ->visible(
                        fn (?WorkTask $record): bool => filled($record?->ticket_id)
                    )
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ticket.ticket_no')
                            ->label('Request No')
                            ->placeholder('-'),

                        TextEntry::make('ticket.employee.name')
                            ->label('Requester')
                            ->placeholder('-'),

                        TextEntry::make('ticket.requesterDepartment.name')
                            ->label('From Department')
                            ->placeholder('-'),

                        TextEntry::make('ticket.handlerDepartment.name')
                            ->label('To Department')
                            ->placeholder('-'),

                        TextEntry::make('ticket.category.name')
                            ->label('Request Category')
                            ->placeholder('-'),

                        TextEntry::make('ticket.priority')
                            ->label('Request Priority')
                            ->badge()
                            ->placeholder('-'),

                        TextEntry::make('ticket.subject')
                            ->label('Request Subject')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('ticket.description')
                            ->label('Request Description')
                            ->placeholder('No description')
                            ->columnSpanFull(),

                        TextEntry::make('ticket.attachments')
                            ->label('Request Attachments')
                            ->placeholder('No attachments')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->formatStateUsing(function (mixed $state): HtmlString|string {
                                if (! is_string($state) || blank($state)) {
                                    return '-';
                                }

                                $fileName = basename($state);
                                $fileUrl = url('/storage/' . ltrim($state, '/'));

                                return new HtmlString(
                                    '<a href="' . e($fileUrl) . '"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style="
                                            color: #2563eb;
                                            font-weight: 600;
                                            text-decoration: underline;
                                        ">'
                                        . e($fileName) .
                                    '</a>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | WORK EXECUTION
                |--------------------------------------------------------------------------
                | Bagian ini dipakai PIC untuk mengelola pekerjaan.
                */
                Section::make('Work Execution')
                    ->description(
                        'Tentukan PIC, status, progress, jadwal, dan catatan pekerjaan.'
                    )
                    ->columns(2)
                    ->schema([
                        TextInput::make('task_no')
                            ->label('Task No')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto generate saat disimpan'),

                        Select::make('ticket_id')
                            ->label('Related Service Desk')
                            ->disabled(
                                fn (?WorkTask $record): bool => filled($record?->ticket_id)
                            )
                            ->relationship('ticket', 'ticket_no')
                            ->searchable(),

                        Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(
                                fn (Set $set): mixed => $set('employee_id', null)
                            )
                            ->disabled(
                                fn (?WorkTask $record): bool => filled($record?->ticket_id)
                            )
                            ->required(),

                        Select::make('employee_id')
                            ->label('PIC / Assigned To')
                            ->options(function (Get $get): array {
                                $departmentId = $get('department_id');

                                if (blank($departmentId)) {
                                    return [];
                                }

                                return Employee::query()
                                    ->where('department_id', $departmentId)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(
                                fn (Get $get): bool => blank($get('department_id'))
                            )
                            ->helperText('PIC hanya menampilkan employee dari department yang dipilih.')
                            ->nullable(),

                        // Select::make('task_category_id')
                        //     ->label('Task Category')
                        //     ->relationship('category', 'name')
                        //     ->searchable(),

                        Select::make('work_scope')
                            ->label('Work Scope')
                            ->options([
                                'service_request' => 'Service Desk',
                                'project' => 'Project',
                                'office' => 'Office',
                                'department' => 'Department',
                                'maintenance' => 'Maintenance',
                                'meeting' => 'Meeting',
                                'other' => 'Other',
                            ])
                            ->default('office')
                            ->disabled(
                                fn (?WorkTask $record): bool => filled($record?->ticket_id)
                            )
                            ->required(),

                        TextInput::make('title')
                            ->label('Task Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Work Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('priority')
                            ->label('Priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->default('medium')
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'planned' => 'Planned',
                                'in_progress' => 'In Progress',
                                'done' => 'Done',
                                'hold' => 'Hold',
                                'cancel' => 'Cancel',
                            ])
                            ->default('planned')
                            ->required(),

                        TextInput::make('progress_percent')
                            ->label('Progress %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),

                        DateTimePicker::make('start_at')
                            ->label('Start At'),

                        DateTimePicker::make('due_at')
                            ->label('Due At'),

                        DateTimePicker::make('completed_at')
                            ->label('Completed At'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
