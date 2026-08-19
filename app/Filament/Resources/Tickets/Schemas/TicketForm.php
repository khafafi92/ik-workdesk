<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Filament\Resources\Tickets\Tables\PermitKblisPickerTable;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\PermitCompany;
use App\Models\PermitKbli;
use App\Models\Ticket;
use App\Models\TicketCategory;
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
                    ->afterStateHydrated(function (TextInput $component, ?Ticket $record): void {
                        if ($record && $record->exists) {
                            $component->state(
                                $record->employee?->name
                                    ?? 'Employee belum di-link'
                            );
                        }
                    })
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('requester_department_display')
                    ->label('Requester / Lead Department')
                    ->default(
                        fn () => auth()->user()?->employee?->department?->name
                            ?? 'Department belum diisi'
                    )
                    ->afterStateHydrated(function (TextInput $component, ?Ticket $record): void {
                        if ($record && $record->exists) {
                            $component->state(
                                $record->requesterDepartment?->name
                                    ?? 'Department belum diisi'
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
                    ->afterStateUpdated(function (Set $set): void {
                        $set('ticket_category_id', null);
                        $set('reviewer_department_ids', []);
                    })
                    ->disabled(
                        fn (?Ticket $record): bool => $record !== null
                            && TicketResource::canReviseRejectedLegalRequest($record)
                    )
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
                        'Requester adalah lead permintaan. Pilih department tambahan yang ikut mengerjakan atau melakukan review.'
                    )
                    ->columnSpanFull(),

                Section::make('Permit')
                    ->description('Wajib untuk Request Category yang membutuhkan data Permit dan KBLI.')
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
                            ->placeholder('Klik untuk memilih KBLI')
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
                                    ->label('Pilih KBLI')
                                    ->modalHeading('Pilih KBLI')
                                    ->modalDescription('Cari menggunakan nomor atau nama KBLI, lalu pilih satu baris yang sesuai.')
                                    ->modalSubmitActionLabel('Gunakan KBLI')
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
                            ->helperText('Daftar hanya menampilkan KBLI aktif milik Permit Company yang dipilih.'),

                        Toggle::make('permit_kbli_unavailable')
                            ->label('Tidak ada / belum terdaftar di KBLI')
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
                            ->helperText('Jika aktif, status Service Desk otomatis menjadi Discussion.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label(
                        fn (Get $get, ?Ticket $record): string => self::isLegalDestination($get, $record)
                            ? 'Nama Proyek / Kegiatan / Transaksi'
                            : 'Description'
                    )
                    ->rows(4)
                    ->required(
                        fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record)
                    )
                    ->columnSpanFull(),

                Section::make('Detail Permintaan Legal')
                    ->description('Informasi ini wajib dilengkapi untuk permintaan yang ditujukan ke Legal.')
                    ->visible(
                        fn (Get $get, ?Ticket $record): bool => self::isLegalDestination($get, $record)
                    )
                    ->schema([
                        Textarea::make('legal_background')
                            ->label('Latar Belakang')
                            ->helperText('Uraikan kondisi, permasalahan, atau kebutuhan bisnis yang mendasari permintaan kepada Divisi Legal.')
                            ->rows(4)
                            ->required(),

                        Textarea::make('legal_objective')
                            ->label('Tujuan Permintaan')
                            ->helperText('Jelaskan hasil atau output yang diharapkan dari Divisi Legal.')
                            ->rows(4)
                            ->required(),

                        Textarea::make('legal_desired_scheme')
                            ->label('Skema yang Diinginkan')
                            ->helperText('Jelaskan skema kerja sama, transaksi, mekanisme pelaksanaan, struktur, dan pihak yang terlibat bila diperlukan.')
                            ->rows(4)
                            ->required(),

                        CheckboxList::make('legal_document_types')
                            ->label('Dokumen Pendukung')
                            ->options([
                                'draft_agreement' => 'Draft Perjanjian',
                                'proposal' => 'Proposal',
                                'term_sheet_loi' => 'Term Sheet / LOI',
                                'correspondence' => 'Korespondensi',
                                'company_profile' => 'Company Profile',
                                'permit_document' => 'Dokumen Perizinan',
                                'other' => 'Dokumen Lainnya',
                            ])
                            ->columns(2)
                            ->helperText('Opsional. Centang jenis dokumen yang dilampirkan.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label(
                        fn (Get $get, ?Ticket $record): string => self::isLegalDestination($get, $record)
                            ? 'Upload Dokumen Pendukung (Opsional)'
                            : 'Attachments'
                    )
                    ->multiple()
                    ->maxFiles(10)
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
                        'discussion' => 'Discussion',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                        'cancel' => 'Cancel',
                        'rejected' => 'Rejected',
                    ])
                    ->default('open')
                    ->disabled(fn (?Ticket $record): bool => $record !== null)
                    ->required(),

                DateTimePicker::make('reported_at')
                    ->label('Reported At')
                    ->default(now()),

                DateTimePicker::make('due_at')
                    ->label('Due At'),
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
