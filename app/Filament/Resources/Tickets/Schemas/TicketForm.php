<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Filament\Resources\Tickets\Tables\PermitKblisPickerTable;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\LegalSubjectCategory;
use App\Models\PermitCompany;
use App\Models\PermitKbli;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\LegalRequestSchedule;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                    ->label(__('Request No'))
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder(__('Auto generation on saved')),

                Hidden::make('employee_id')
                    ->default(fn () => auth()->user()?->employee?->id)
                    ->dehydrated(),

                Hidden::make('requester_department_id')
                    ->default(fn () => auth()->user()?->employee?->department_id)
                    ->dehydrated(),

                TextInput::make('requester_display')
                    ->label(__('Requester'))
                    ->default(
                        fn () => auth()->user()?->employee?->name
                            ?? 'Employee has not been linked to login user'
                    )
                    ->afterStateHydrated(function (TextInput $component, ?Ticket $record): void {
                        if ($record && $record->exists) {
                            $component->state(
                                $record->employee?->name
                                    ?? 'Employee has not been linked'
                            );
                        }
                    })
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('requester_department_display')
                    ->label('Requester / Lead Department')
                    ->default(
                        fn () => auth()->user()?->employee?->department?->name
                            ?? 'Department is not charged'
                    )
                    ->afterStateHydrated(function (TextInput $component, ?Ticket $record): void {
                        if ($record && $record->exists) {
                            $component->state(
                                $record->requesterDepartment?->name
                                    ?? 'Department is not charged'
                            );
                        }
                    })
                    ->disabled()
                    ->dehydrated(false),

                Select::make('handler_department_id')
                    ->label('Primary Destination Department')
                    ->relationship('handlerDepartment', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                        $set('ticket_category_id', null);
                        $set('legal_subject_category_id', null);
                        $set('subject', null);
                        $set('reviewer_department_ids', []);

                        if (Department::query()->find($state)?->isLegal()) {
                            $set(
                                'due_at',
                                LegalRequestSchedule::minimumDueAt(
                                    $get('reported_at')
                                )
                            );
                        }
                    })
                    ->disabled(
                        fn (?Ticket $record): bool => $record !== null
                            && TicketResource::canReviseRejectedLegalRequest($record)
                    )
                    ->required(),

                Select::make('ticket_category_id')
                    ->label(__('Request Category'))
                    ->options(function (Get $get, ?Ticket $record): array {
                        $handlerDepartmentId = $get('handler_department_id');

                        if (blank($handlerDepartmentId)) {
                            return [];
                        }

                        return TicketCategory::query()
                            ->where(
                                'handler_department_id',
                                $handlerDepartmentId
                            )
                            ->where(function (Builder $query) use ($record, $handlerDepartmentId): void {
                                $query->where('is_active', true);
                                if ($record && filled($record->manpower_details) && (int) $record->handler_department_id === (int) $handlerDepartmentId) {
                                    $query->orWhereKey($record->ticket_category_id);
                                }
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->disabled(
                        fn (Get $get, ?Ticket $record): bool => blank($get('handler_department_id'))
                            || ($record !== null
                                && TicketResource::canReviseRejectedLegalRequest($record))
                    )
                    ->afterStateUpdated(
                        function (mixed $state, Set $set): void {
                            if (blank($state)) {
                                $set('reviewer_department_ids', []);
                                $set('permit_company_id', null);
                                $set('permit_kbli_id', null);
                                $set('permit_kbli_unavailable', false);

                                return;
                            }

                            $category = TicketCategory::query()
                                ->with('defaultReviewerDepartments')
                                ->find($state);

                            if (! $category?->requires_permit) {
                                $set('permit_company_id', null);
                                $set('permit_kbli_id', null);
                                $set('permit_kbli_unavailable', false);
                            }

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
                                ->reject(
                                    fn (int $id): bool => $id
                                        === (int) auth()->user()?->employee?->department_id
                                )
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
                    ->label('Additional Destination Departments')
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
                            ->when(
                                auth()->user()?->employee?->department_id,
                                fn ($query, $requesterDepartmentId) => $query->where(
                                    'id',
                                    '!=',
                                    $requesterDepartmentId
                                )
                            )
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->multiple()
                    ->disabled(
                        fn (?Ticket $record): bool => $record !== null
                            && TicketResource::canReviseRejectedLegalRequest($record)
                    )
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Ticket $record): void {
                        if ($record && $record->exists) {
                            $ids = $record->reviewerDepartments
                                ->pluck('id')
                                ->reject(fn ($id) => (int) $id === (int) $record->handler_department_id)
                                ->values()
                                ->all();

                            $component->state($ids);
                        }
                    })
                    ->visible(function (Get $get, ?Ticket $record): bool {
                        // Saat View/Edit: tampilkan jika ticket workflow collaborative
                        if ($record && $record->exists) {
                            return $record->workflow_type === 'collaborative';
                        }

                        // Saat Create: cek dari kategori yang dipilih
                        $categoryId = $get('ticket_category_id');

                        if (blank($categoryId)) {
                            return false;
                        }

                        return TicketCategory::query()
                            ->whereKey($categoryId)
                            ->where('workflow_type', 'collaborative')
                            ->exists();
                    })
                    ->required(function (Get $get, ?Ticket $record): bool {
                        if ($record && $record->exists) {
                            return $record->workflow_type === 'collaborative';
                        }

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
                        __('Requester is the lead request. Select the additional department that comes along or reviews.')
                    )
                    ->columnSpanFull(),

                Section::make('Permit')
                    ->description(__('Obligatory for the Category Request that requires Permit and KBLI data.'))
                    ->visible(function (Get $get, ?Ticket $record): bool {
                        $categoryId = $get('ticket_category_id')
                            ?? $record?->ticket_category_id;

                        return filled($categoryId)
                            && TicketCategory::query()
                                ->whereKey($categoryId)
                                ->where('requires_permit', true)
                                ->exists();
                    })
                    ->columns(2)
                    ->schema([
                        Select::make('permit_company_id')
                            ->label('Permit Company')
                            ->options(
                                fn (): array => PermitCompany::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('permit_kbli_id', null);
                                $set('permit_kbli_unavailable', false);
                            })
                            ->required(),

                        ModalTableSelect::make('permit_kbli_id')
                            ->label('KBLI')
                            ->placeholder(__('Click to select KBLI'))
                            ->relationship(
                                name: 'permitKbli',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('permit_company_id', (int) $get('permit_company_id'))
                                    ->where('is_active', true),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (PermitKbli $record): string => "{$record->code} — {$record->name}"
                            )
                            ->tableConfiguration(PermitKblisPickerTable::class)
                            ->tableArguments(
                                fn (Get $get): array => [
                                    'permit_company_id' => (int) $get('permit_company_id'),
                                ]
                            )
                            ->selectAction(
                                fn (Action $action): Action => $action
                                    ->label(__('Select KBLI'))
                                    ->modalHeading(__('Select KBLI'))
                                    ->modalDescription(__('Search using a KBLI number or name, then select a suitable line.'))
                                    ->modalSubmitActionLabel(__('Use KBLI'))
                                    ->modalWidth(Width::SevenExtraLarge)
                                    ->slideOver(false)
                            )
                            ->disabled(
                                fn (Get $get): bool => blank($get('permit_company_id'))
                                    || (bool) $get('permit_kbli_unavailable')
                            )
                            ->required(
                                fn (Get $get): bool => filled($get('permit_company_id'))
                                    && ! (bool) $get('permit_kbli_unavailable')
                            )
                            ->helperText(__('The list only shows the active KBLI of the Selected Simit Company.')),

                        Toggle::make('permit_kbli_unavailable')
                            ->label(__('No / not registered on KBLI'))
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                if ($state) {
                                    $set('permit_kbli_id', null);
                                    $set('status', 'discussion');
                                } else {
                                    $set('status', 'open');
                                }
                            })
                            ->visible(fn (Get $get): bool => filled($get('permit_company_id')))
                            ->helperText(__('If active, the status of Service Desk automatically becomes Discussion.'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                TextInput::make('subject')
                    ->label(__('Subject'))
                    ->required(
                        fn (Get $get, ?Ticket $record): bool => ! ManpowerRequestFields::isVisible($get, $record)
                            && (! self::isLegalDestination($get, $record)
                                || ($record !== null && blank($record->legal_subject_category_id)))
                    )
                    ->maxLength(255)
                    ->visible(
                        fn (Get $get, ?Ticket $record): bool => ! ManpowerRequestFields::isVisible($get, $record)
                            && (! self::isLegalDestination($get, $record)
                                || ($record !== null && blank($record->legal_subject_category_id)))
                    )
                    ->columnSpanFull(),

                Select::make('legal_subject_category_id')
                    ->label(__('Subject Category'))
                    ->options(fn (): array => LegalSubjectCategory::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $set('subject', LegalSubjectCategory::query()->find($state)?->name);
                    })
                    ->required(
                        fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record)
                            && ($record === null || filled($get('legal_subject_category_id')))
                    )
                    ->visible(fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record))
                    ->helperText(__('Select subject from master subject Categories.'))
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->visible(fn (Get $get, ?Ticket $record): bool => ! ManpowerRequestFields::isVisible($get, $record))
                    ->label(
                        fn (Get $get, ?Ticket $record): string => self::isLegalDestination($get, $record)
                            ? __('Project / Activity Name')
                            : __('Description')
                    )
                    ->rows(4)
                    ->required(
                        fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record)
                    )
                    ->columnSpanFull(),

                ManpowerRequestFields::section(),

                Section::make(__('Legal Request Details'))
                    ->description(__('This information is mandatory for requests directed to Legal.'))
                    ->visible(
                        fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record)
                    )
                    ->schema([
                        Textarea::make('legal_background')
                            ->label(__('Background'))
                            ->helperText(__('Describe the conditions, problems, or business needs underlying the request to Legal Division.'))
                            ->rows(4)
                            ->required(),

                        Textarea::make('legal_objective')
                            ->label(__('Purpose of Request'))
                            ->helperText(__('Explain the expected results or output from Legal Division.'))
                            ->rows(4)
                            ->required(),

                        Textarea::make('legal_desired_scheme')
                            ->label(__('Preferred arrangement'))
                            ->helperText(__('Explain the scheme of cooperation, transactions, mechanisms of execution, structure, and party involved when needed.'))
                            ->rows(4)
                            ->required(),

                        CheckboxList::make('legal_document_types')
                            ->label(__('Supporter Documents'))
                            ->options([
                                'draft_agreement' => __('Draft Agreement'),
                                'proposal' => 'Proposal',
                                'term_sheet_loi' => 'Term Sheet / LOI',
                                'correspondence' => 'Korespondensi',
                                'company_profile' => 'Company Profile',
                                'permit_document' => __('Permission Document'),
                                'articles_of_association_related_party' => __('ART associated party'),
                                'other' => __('Other Documents'),
                            ])
                            ->columns(2)
                            ->helperText(__('Optional. Check the type of document attached.'))
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label(
                        fn (Get $get, ?Ticket $record): string => self::isLegalDestination($get, $record)
                            ? __('Upload Supportor Documents (Optional)')
                            : __('Attachments')
                    )
                    ->multiple()
                    ->disk('local')
                    ->directory('service-request-attachments')
                    ->visibility('private')
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
                                .'-'
                                .Str::random(6)
                                .'-'
                                .Str::slug($originalName)
                                .'.'
                                .$extension;
                        }
                    )
                    ->deleteUploadedFileUsing(
                        function (string $file): void {
                            Storage::disk('local')->delete($file);
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
                    ->label(__('Priority'))
                    ->options([
                        'low' => __('Low'),
                        'medium' => __('Medium'),
                        'high' => __('High'),
                        'urgent' => __('Urgent'),
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('status')
                    ->label(__('Status'))
                    ->options([
                        'open' => __('Open'),
                        'in_progress' => __('In Progress'),
                        'waiting_user' => 'Waiting User',
                        'discussion' => 'Discussion',
                        'resolved' => __('Resolved'),
                        'closed' => __('Closed'),
                        'cancel' => __('Cancel'),
                        'rejected' => __('Rejected'),
                    ])
                    ->default('open')
                    ->disabled(fn (?Ticket $record): bool => $record !== null)
                    ->required(),

                DateTimePicker::make('reported_at')
                    ->label('Reported At')
                    ->default(now('Asia/Jakarta'))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set, ?Ticket $record): void {
                        if (! self::isLegalDestination($get, $record)) {
                            return;
                        }

                        $minimumDueAt = LegalRequestSchedule::minimumDueAt($state);
                        $dueAt = $get('due_at');

                        if (blank($dueAt) || Carbon::parse($dueAt)->lt($minimumDueAt)) {
                            $set('due_at', $minimumDueAt);
                        }
                    }),

                DateTimePicker::make('due_at')
                    ->label(__('Due At'))
                    ->minDate(fn (Get $get, ?Ticket $record): ?Carbon => self::isLegalDestination($get, $record)
                        ? LegalRequestSchedule::minimumDueAt($get('reported_at'))
                        : null)
                    ->helperText(fn (Get $get, ?Ticket $record): ?string => self::isLegalDestination($get, $record)
                        ? __('Legal requests are available before 3:00 p.m. and Due At minimum 3 days after Reported At.')
                        : null),
            ]);
    }

    private static function isLegalDestination(Get $get, ?Ticket $record): bool
    {
        $departmentId = $get('handler_department_id')
            ?? $record?->handler_department_id;

        if (blank($departmentId)) {
            return false;
        }

        return Department::query()->find($departmentId)?->isLegal() === true;
    }
}
