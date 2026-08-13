<?php

namespace App\Filament\Resources\WorkTasks\Schemas;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Employee;
use App\Models\WorkTask;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                            ->formatStateUsing(function (
                                mixed $state,
                                WorkTask $record
                            ): HtmlString|string {
                                if (! is_string($state) || blank($state)) {
                                    return '-';
                                }

                                $fileName = basename($state);
                                $attachmentIndex = array_search(
                                    $state,
                                    array_values((array) $record->ticket?->attachments),
                                    true
                                );
                                $fileUrl = route(
                                    'tickets.attachments.download',
                                    [$record->ticket, $attachmentIndex]
                                );

                                return new HtmlString(
                                    '<a href="'.e($fileUrl).'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style="
                                            color: #2563eb;
                                            font-weight: 600;
                                            text-decoration: underline;
                                        ">'
                                        .e($fileName).
                                    '</a>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Permit Request & Result')
                    ->description('KBLI yang diminta dari Service Desk dan hasil yang dikirim oleh Legal.')
                    ->visible(fn (?WorkTask $record): bool => $record?->isPermitLegalTask() === true)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ticket.permitCompany.code')
                            ->label('Permit Company Code')
                            ->placeholder('-'),

                        TextEntry::make('ticket.permitCompany.name')
                            ->label('Permit Company Name')
                            ->placeholder('-'),

                        TextEntry::make('ticket.permitKbli.code')
                            ->label('Requested KBLI No.')
                            ->placeholder(
                                fn (WorkTask $record): string => $record->ticket?->permit_kbli_unavailable
                                    ? 'Tidak ada / belum terdaftar'
                                    : '-'
                            ),

                        TextEntry::make('ticket.permitKbli.name')
                            ->label('Requested KBLI Name')
                            ->placeholder(
                                fn (WorkTask $record): string => $record->ticket?->permit_kbli_unavailable
                                    ? 'Perlu didiskusikan dengan Legal'
                                    : '-'
                            ),

                        Textarea::make('permit_result_notes')
                            ->label('Permit Result Notes')
                            ->helperText('Diisi oleh Legal untuk menjelaskan hasil pemrosesan KBLI.')
                            ->rows(4)
                            ->maxLength(5000)
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canUpdateExecutionBy(auth()->user())
                            )
                            ->columnSpanFull(),

                        FileUpload::make('permit_result_attachments')
                            ->label('Permit Result Attachments')
                            ->helperText('Unggah dokumen hasil Permit/KBLI yang akan dikirim kepada requester.')
                            ->multiple()
                            ->maxFiles(10)
                            ->disk('local')
                            ->directory('permit-results')
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->previewable(false)
                            ->deletable()
                            ->visible(
                                fn (?WorkTask $record): bool => $record === null
                                    || $record->canUpdateExecutionBy(auth()->user())
                            )
                            ->getUploadedFileNameForStorageUsing(
                                function (TemporaryUploadedFile $file): string {
                                    $originalName = pathinfo(
                                        $file->getClientOriginalName(),
                                        PATHINFO_FILENAME
                                    );
                                    $extension = strtolower($file->getClientOriginalExtension());

                                    return now()->format('YmdHis')
                                        .'-'.Str::random(6)
                                        .'-'.Str::slug($originalName)
                                        .'.'.$extension;
                                }
                            )
                            ->deleteUploadedFileUsing(
                                fn (string $file): bool => Storage::disk('local')->delete($file)
                            )
                            ->dehydrateStateUsing(function ($state): array {
                                if (blank($state)) {
                                    return [];
                                }

                                if (is_string($state)) {
                                    $decoded = json_decode($state, true);

                                    return is_array($decoded)
                                        ? array_values($decoded)
                                        : [$state];
                                }

                                return collect($state)->filter()->values()->all();
                            })
                            ->afterStateHydrated(function (FileUpload $component, mixed $state): void {
                                if (blank($state)) {
                                    $component->state([]);

                                    return;
                                }

                                if (is_string($state)) {
                                    $decoded = json_decode($state, true);
                                    $state = is_array($decoded) ? $decoded : [$state];
                                }

                                $component->state(array_values($state));
                            })
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->default([])
                            ->columnSpanFull(),

                        TextEntry::make('permit_result_downloads')
                            ->label('Permit Result Attachments')
                            ->state(
                                fn (WorkTask $record): array => array_values(
                                    (array) $record->permit_result_attachments
                                )
                            )
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->formatStateUsing(function (string $state, WorkTask $record): HtmlString {
                                $attachmentIndex = array_search(
                                    $state,
                                    array_values((array) $record->permit_result_attachments),
                                    true
                                );
                                $url = route('work-tasks.permit-results.download', [
                                    $record,
                                    $attachmentIndex,
                                ]);

                                return new HtmlString(
                                    '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" '
                                    .'style="color:#2563eb;font-weight:600;text-decoration:underline;">'
                                    .e(basename($state)).'</a>'
                                );
                            })
                            ->visible(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canUpdateExecutionBy(auth()->user())
                                    && filled($record->permit_result_attachments)
                            )
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
                            ->relationship(
                                name: 'ticket',
                                titleAttribute: 'ticket_no',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->whereIn(
                                        'tickets.id',
                                        TicketResource::getEloquentQuery()
                                            ->select('tickets.id')
                                    )
                            )
                            ->searchable(),

                        Select::make('department_id')
                            ->label('Department')
                            ->relationship(
                                name: 'department',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $user = auth()->user();

                                    if (! $user) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query
                                        ->where('is_active', true)
                                        ->whereIn(
                                            'departments.id',
                                            $user->accessibleDepartmentIds()
                                        );
                                }
                            )
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
                            ->label('PIC / Pelaksana')
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
                                fn (Get $get, ?WorkTask $record): bool => blank($get('department_id'))
                                    || ($record !== null
                                        && ! $record->canBeManagedBy(auth()->user()))
                            )
                            ->helperText(
                                'PIC dipilih oleh pengelola department tujuan dan wajib diisi sebelum pekerjaan diselesaikan.'
                            )
                            ->nullable(),

                        TextInput::make('title')
                            ->label('Task Title')
                            ->required()
                            ->maxLength(255)
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canBeManagedBy(auth()->user())
                            )
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Work Description')
                            ->rows(4)
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canBeManagedBy(auth()->user())
                            )
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
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canUpdateExecutionBy(auth()->user())
                            )
                            ->helperText('Nilai awal dari requester dapat disesuaikan oleh department penerima. Setiap perubahan tercatat di Activity History.')
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
                            ->live()
                            ->disableOptionWhen(
                                fn (string $value, ?WorkTask $record): bool => (
                                    $value === 'done'
                                    && ! ($record?->canBeCompletedBy(auth()->user())
                                        ?? (
                                            auth()->user()?->is_admin === true
                                            || auth()->user()?->hasRole('system-admin') === true
                                        ))
                                ) || (
                                    in_array($value, ['hold', 'cancel'], true)
                                    && ! ($record?->canBeCancelledBy(auth()->user()) ?? false)
                                ) || (
                                    $record?->isAssignedTo(auth()->user()) === true
                                    && ! $record->canBeManagedBy(auth()->user())
                                    && $value === 'done'
                                )
                            )
                            ->default('planned')
                            ->required(),

                        Textarea::make('status_reason')
                            ->label('Alasan Hold / Cancel')
                            ->rows(3)
                            ->visible(
                                fn (Get $get): bool => in_array(
                                    $get('status'),
                                    ['hold', 'cancel'],
                                    true
                                )
                            )
                            ->required(
                                fn (Get $get): bool => in_array(
                                    $get('status'),
                                    ['hold', 'cancel'],
                                    true
                                )
                            )
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canUpdateExecutionBy(auth()->user())
                            )
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        TextInput::make('progress_percent')
                            ->label('Progress %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),

                        DateTimePicker::make('start_at')
                            ->label('Start At'),

                        DateTimePicker::make('due_at')
                            ->label('Due At')
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canUpdateExecutionBy(auth()->user())
                            )
                            ->helperText('Target awal dari requester dapat disepakati ulang oleh department penerima. Setiap perubahan tercatat.'),

                        DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->disabled(
                                fn (?WorkTask $record): bool => $record !== null
                                    && ! $record->canBeManagedBy(auth()->user())
                            ),

                        TextEntry::make('completedBy.name')
                            ->label('Marked Done By')
                            ->placeholder('-')
                            ->visible(
                                fn (?WorkTask $record): bool => filled($record?->completed_at)
                            ),

                        TextEntry::make('approval_status')
                            ->label('Legal Approval')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'pending' => 'Menunggu CBO',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                default => 'Tidak diperlukan',
                            })
                            ->visible(fn (?WorkTask $record): bool => $record?->requiresLegalApproval() === true),

                        TextEntry::make('rejectedBy.name')
                            ->label('Rejected By')
                            ->placeholder('-')
                            ->visible(fn (?WorkTask $record): bool => $record?->isLegalApprovalRejected() === true),

                        TextEntry::make('rejection_reason')
                            ->label('Alasan Penolakan CBO')
                            ->placeholder('-')
                            ->visible(fn (?WorkTask $record): bool => $record?->isLegalApprovalRejected() === true)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
