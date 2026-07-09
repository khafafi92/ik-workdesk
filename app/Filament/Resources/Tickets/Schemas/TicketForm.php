<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Department;
use App\Models\TicketCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ticket_no')
                    ->label('Request No')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto generate saat disimpan'),

                Hidden::make('employee_id')
                    ->default(fn () => auth()->user()?->employee?->id)
                    ->dehydrated(),

                Hidden::make('requester_department_id')
                    ->default(fn () => auth()->user()?->employee?->department_id)
                    ->dehydrated(),

                TextInput::make('requester_display')
                    ->label('Requester')
                    ->default(
                        fn () => auth()->user()?->employee?->name
                            ?? 'Employee belum di-link ke user login'
                    )
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('requester_department_display')
                    ->label('Requester Department')
                    ->default(
                        fn () => auth()->user()?->employee?->department?->name
                            ?? 'Department belum diisi'
                    )
                    ->disabled()
                    ->dehydrated(false),

                Select::make('handler_department_id')
                    ->label('Lead / Destination Department')
                    ->relationship('handlerDepartment', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('ticket_category_id', null);
                        $set('reviewer_department_ids', []);
                    })
                    ->required(),

                Select::make('ticket_category_id')
                    ->label('Request Category')
                    ->options(function (Get $get): array {
                        $handlerDepartmentId = $get('handler_department_id');

                        if (blank($handlerDepartmentId)) {
                            return [];
                        }

                        return TicketCategory::query()
                            ->where(
                                'handler_department_id',
                                $handlerDepartmentId
                            )
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->disabled(
                        fn (Get $get): bool =>
                            blank($get('handler_department_id'))
                    )
                    ->afterStateUpdated(
                        function (mixed $state, Set $set): void {
                            if (blank($state)) {
                                $set('reviewer_department_ids', []);

                                return;
                            }

                            $category = TicketCategory::query()
                                ->with('defaultReviewerDepartments')
                                ->find($state);

                            if (
                                ! $category
                                || $category->workflow_type !== 'collaborative'
                            ) {
                                $set('reviewer_department_ids', []);

                                return;
                            }

                            $reviewerDepartmentIds = $category
                                ->defaultReviewerDepartments
                                ->pluck('id')
                                ->map(fn ($id): int => (int) $id)
                                ->values()
                                ->all();

                            $set(
                                'reviewer_department_ids',
                                $reviewerDepartmentIds
                            );
                        }
                    )
                    ->required(),

                Select::make('reviewer_department_ids')
                    ->label('Reviewer Departments')
                    ->options(function (Get $get): array {
                        $leadDepartmentId = $get('handler_department_id');

                        return Department::query()
                            ->when(
                                filled($leadDepartmentId),
                                fn ($query) => $query->where(
                                    'id',
                                    '!=',
                                    $leadDepartmentId
                                )
                            )
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(function (Get $get): bool {
                        $categoryId = $get('ticket_category_id');

                        if (blank($categoryId)) {
                            return false;
                        }

                        return TicketCategory::query()
                            ->whereKey($categoryId)
                            ->where('workflow_type', 'collaborative')
                            ->exists();
                    })
                    ->required(function (Get $get): bool {
                        $categoryId = $get('ticket_category_id');

                        if (blank($categoryId)) {
                            return false;
                        }

                        return TicketCategory::query()
                            ->whereKey($categoryId)
                            ->where('workflow_type', 'collaborative')
                            ->exists();
                    })
                    ->helperText(
                        'Lead Department otomatis ikut mengerjakan. Pilih department tambahan yang akan melakukan review.'
                    )
                    ->columnSpanFull(),

                TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label('Attachments')
                    ->multiple()
                    ->maxFiles(10)
                    ->disk('public')
                    ->directory('service-request-attachments')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->previewable(false)
                    ->deletable()
                    ->getUploadedFileNameForStorageUsing(
                        function (TemporaryUploadedFile $file): string {
                            $originalName = pathinfo(
                                $file->getClientOriginalName(),
                                PATHINFO_FILENAME
                            );

                            $extension = strtolower(
                                $file->getClientOriginalExtension()
                            );

                            return now()->format('YmdHis')
                                . '-'
                                . Str::random(6)
                                . '-'
                                . Str::slug($originalName)
                                . '.'
                                . $extension;
                        }
                    )
                    ->deleteUploadedFileUsing(
                        function (string $file): void {
                            Storage::disk('public')->delete($file);
                        }
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

                        return collect($state)
                            ->filter()
                            ->values()
                            ->all();
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
                    ->maxSize(10240)
                    ->default([])
                    ->afterStateHydrated(
                        function (
                            FileUpload $component,
                            mixed $state
                        ): void {
                            if (blank($state)) {
                                $component->state([]);

                                return;
                            }

                            if (is_string($state)) {
                                $decoded = json_decode($state, true);

                                $state = is_array($decoded)
                                    ? $decoded
                                    : [$state];
                            }

                            $component->state(array_values($state));
                        }
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
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'waiting_user' => 'Waiting User',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                        'cancel' => 'Cancel',
                    ])
                    ->default('open')
                    ->required(),

                DateTimePicker::make('reported_at')
                    ->label('Reported At')
                    ->default(now()),

                DateTimePicker::make('due_at')
                    ->label('Due At'),
            ]);
    }
}