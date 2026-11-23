<?php

namespace App\Filament\Resources\OutgoingLetters;

use App\Filament\Resources\OutgoingLetters\Pages\CreateOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\EditOutgoingLetter;
use App\Filament\Resources\OutgoingLetters\Pages\ListOutgoingLetters;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Services\DocumentNumberService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

class OutgoingLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Outgoing Letters';

    protected static ?string $modelLabel = 'Outgoing Letters';

    protected static ?string $pluralModelLabel = 'Outgoing Letters';

    protected static ?string $recordTitleAttribute = 'document_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1. Choose a profile and identify the letter')
                ->description('Select profiles according to mail groups. Entity and department are filled from the author\'s profile or account, and complete the mail type and date.')
                ->columns(3)
                ->schema([
                    Select::make('letter_profile_id')->label('Mail profile')->relationship('profile', 'name')->searchable()->preload()->required()->live()
                        ->helperText('Example: APCA General Letter, H.R. APCA, HR KPMOG, or Team Project/BD KPMOG.')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $profile = LetterProfile::find($state);

                            if ($profile === null) {
                                return;
                            }

                            $set('permit_company_id', $profile->permit_company_id);
                            $set('department_id', static::usesApcaGeneralNumbering($profile->id, $profile->permit_company_id)
                                ? (auth()->user()?->is_admin ? $profile->department_id : static::creatorDepartmentId())
                                : $profile->department_id);
                        })
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Select::make('permit_company_id')->label('Issuer entity')->relationship('company', 'name')->searchable()->preload()->required()->live()
                        ->helperText('Select the entity that publishes the mail.')
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Select::make('department_id')->label('Publisher Department')->relationship('department', 'name')->searchable()->preload()->live()
                        ->helperText(fn (Get $get): string => static::usesApcaGeneralNumbering($get('letter_profile_id'), $get('permit_company_id'))
                            ? (auth()->user()?->is_admin
                                ? 'Select the department that publishes the mail.'
                                : 'Department code was taken from the Mailman\'s Department account.')
                            : 'Select the sender department if mail uses department code.')
                        ->disabled(fn (Get $get, ?OutgoingLetter $record): bool => static::isNumberLocked($record)
                            || (static::usesApcaGeneralNumbering($get('letter_profile_id'), $get('permit_company_id'))
                                && auth()->user()?->is_admin !== true))
                        ->dehydrated(fn (Get $get): bool => static::usesApcaGeneralNumbering($get('letter_profile_id'), $get('permit_company_id'))),
                    Select::make('document_type_id')->label('Mail type')->relationship('documentType', 'name')->searchable()->preload()->live()
                        ->helperText('The type of mail determines the code in the mail number, if the template uses it.')
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    DatePicker::make('document_date')->label('Mail date')->default(today())->required()->live()
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                ]),
            Section::make('2. Add the recipient and letter content')
                ->description('Project, PIN, PIC name, location code, and description are only filled if needed by this letter.')
                ->columns(3)
                ->schema([
                    Select::make('work_project_id')->label('Project / Job Order')->relationship('project', 'code')->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->code.' - '.$record->name))->searchable()->preload(),
                    TextInput::make('pin')->label('PIN')->maxLength(100),
                    Select::make('pic_user_id')->label('PIC user')->relationship('picUser', 'name')->searchable()->preload(),
                    TextInput::make('pic_name')->label('PIC Name')->maxLength(255),
                    TextInput::make('recipient')->label('Mail destination')->helperText('The name of the agency, the company, or the recipient of the mail.')->maxLength(255),
                    TextInput::make('subject')->label('Subject')->helperText('Summarize what the letter is about.')->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('location_code')->label('Location / reference code')->maxLength(100)->live()
                        ->helperText('Fill only when template mail numbers using location code.'),
                    Textarea::make('description')->label('Additional description')->helperText('Optional. Contents if necessary internal notes for this message.')->maxLength(4000)->columnSpanFull(),
                ]),
            Section::make('3. Review the number and save the draft')
                ->description('The number below is preview. Save it as a draft first. Number becomes final and reserved only when mail is published.')
                ->schema([
                    Toggle::make('is_legacy_number')->label('Input executing / legacy number')->live()
                        ->helperText('Use it only to record old letters that already have numbers.')
                        ->visible(fn (): bool => static::canUseLegacyNumbers())
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    TextInput::make('document_number')->label('Existing message number')->maxLength(500)->unique(ignoreRecord: true)
                        ->visible(fn (Get $get): bool => (bool) $get('is_legacy_number'))
                        ->required(fn (Get $get): bool => (bool) $get('is_legacy_number'))
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Placeholder::make('number_preview')->label('Preview mail numbers')->content(fn (Get $get): string => static::preview($get)),
                    Placeholder::make('number_status')->label('Status')->content(fn (?OutgoingLetter $record): string => $record?->status ? static::statusLabel($record->status) : 'Draft'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('document_number')->label('Mail number')->searchable()->sortable()->placeholder('Draft'),
            TextColumn::make('document_date')->label('Date')->date('d M Y')->sortable(),
            TextColumn::make('profile.name')->label('Profiles')->placeholder('-')->toggleable(),
            TextColumn::make('company.code')->label('Company')->sortable(),
            TextColumn::make('department.code')->label('Department')->placeholder('-'),
            TextColumn::make('documentType.name')->label('Mail type')->placeholder('-')->toggleable(),
            TextColumn::make('project.code')->label('Project / Job Order')->placeholder('-')->searchable()->toggleable(),
            TextColumn::make('pic_name')->label('PIC')->placeholder('-')->searchable()->toggleable(),
            TextColumn::make('recipient')->label('Destination')->limit(35)->searchable()->toggleable(),
            TextColumn::make('subject')->label('Subject')->limit(55)->searchable(),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => static::statusLabel($state))
                ->color(fn (string $state): string => match ($state) {
                    'issued' => 'success', 'archived' => 'gray', 'cancelled' => 'danger', default => 'warning'
                }),
        ])->filters([
            SelectFilter::make('letter_profile_id')->label('Mail profile')->relationship('profile', 'name'),
            SelectFilter::make('permit_company_id')->label('Issuer entity')->relationship('company', 'name'),
            SelectFilter::make('department_id')->label('Publisher Department')->relationship('department', 'name'),
            SelectFilter::make('document_type_id')->label('Mail type')->relationship('documentType', 'name'),
            SelectFilter::make('work_project_id')->label('Project')->relationship('project', 'code'),
            SelectFilter::make('status')->label('Status')->options(static::statusOptions()),
            Filter::make('document_date')->label('Year / month')->form([
                Select::make('year')->label('Year')->options(fn (): array => OutgoingLetter::query()
                    ->orderByDesc('document_date')
                    ->pluck('document_date')
                    ->map(fn ($date): int => Carbon::parse($date)->year)
                    ->unique()
                    ->mapWithKeys(fn (int $year): array => [$year => $year])
                    ->all()),
                Select::make('month')->label('Month')->options(collect(range(1, 12))->mapWithKeys(
                    fn (int $month): array => [$month => Carbon::create()->month($month)->translatedFormat('F')]
                )->all()),
            ])->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['year'] ?? null, fn (Builder $builder, $year): Builder => $builder->whereYear('document_date', $year))
                    ->when($data['month'] ?? null, fn (Builder $builder, $month): Builder => $builder->whereMonth('document_date', $month));
            }),
        ])->defaultSort('document_date', 'desc')->headerActions([
            CreateAction::make()->label('Create a mail draft')->visible(fn (): bool => static::canCreate()),
        ])->recordActions([
            Action::make('issue')
                ->label('Publish Mail')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Make sure the mail data is correct. The mail number will be final and cannot be used again.')
                ->visible(fn (OutgoingLetter $record): bool => static::canIssue($record))
                ->action(function (OutgoingLetter $record): void {
                    app(DocumentNumberService::class)->issue($record, auth()->user());
                    Notification::make()->title('Mail successfully published.')->success()->send();
                }),
            Action::make('archive')
                ->label('Archive')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (OutgoingLetter $record): bool => $record->status === 'issued' && static::canManageLifecycle())
                ->action(function (OutgoingLetter $record): void {
                    $record->update(['status' => 'archived']);
                    Notification::make()->title('The letter has been filed.')->success()->send();
                }),
            Action::make('cancel')
                ->label('Cancel draft')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (OutgoingLetter $record): bool => $record->status === 'draft' && static::canManageLifecycle())
                ->action(function (OutgoingLetter $record): void {
                    $record->update(['status' => 'cancelled']);
                    Notification::make()->title('Draft mail canceled.')->success()->send();
                }),
            EditAction::make()->label('Change draft')->visible(fn (OutgoingLetter $record): bool => static::canEdit($record)),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['profile', 'company', 'department', 'documentType', 'project', 'picUser']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.outgoing') === true && auth()->user()?->hasPermission('letters.view') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && auth()->user()?->hasPermission('letters.create') === true;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof OutgoingLetter
            && $record->status === 'draft'
            && (auth()->user()?->hasPermission('letters.manage') === true || (int) $record->created_by === (int) auth()->id());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canIssue(OutgoingLetter $record): bool
    {
        return $record->status === 'draft' && static::canViewAny() && auth()->user()?->hasPermission('letters.issue') === true;
    }

    public static function canManageLifecycle(): bool
    {
        return auth()->user()?->hasPermission('letters.manage') === true;
    }

    public static function canUseLegacyNumbers(): bool
    {
        return auth()->user()?->hasPermission('letters.legacy-import') === true;
    }

    public static function usesApcaGeneralNumbering(mixed $profileId, mixed $companyId): bool
    {
        if (blank($profileId) || blank($companyId)) {
            return false;
        }

        return LetterProfile::query()
            ->whereKey($profileId)
            ->where('code', 'ATE-UMUM')
            ->where('permit_company_id', $companyId)
            ->whereHas('company', fn (Builder $query): Builder => $query->where('code', 'APCA'))
            ->exists();
    }

    public static function creatorDepartmentId(): ?int
    {
        $user = auth()->user();
        $user?->loadMissing('employee.department');
        $department = $user?->employee?->department;

        return $department?->is_active ? (int) $department->id : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Surat';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutgoingLetters::route('/'),
            'create' => CreateOutgoingLetter::route('/create'),
            'edit' => EditOutgoingLetter::route('/{record}/edit'),
        ];
    }

    private static function preview(Get $get): string
    {
        if ($get('is_legacy_number')) {
            return filled($get('document_number')) ? (string) $get('document_number') : 'Enter Existing mail number.';
        }

        if (blank($get('permit_company_id')) || blank($get('document_date'))) {
            return 'Select the publisher\'s entity and the mail date to view.';
        }

        $departmentId = $get('department_id');

        if (static::usesApcaGeneralNumbering($get('letter_profile_id'), $get('permit_company_id'))) {
            $departmentId = auth()->user()?->is_admin
                ? $get('department_id')
                : static::creatorDepartmentId();

            if ($departmentId === null) {
                return auth()->user()?->is_admin
                    ? 'Select the publisher\'s department to view APCA\'s number preview.'
                    : 'The mailmaker\'s account must have an active department to make an APCA number.';
            }
        }

        try {
            $letter = new OutgoingLetter([
                'letter_profile_id' => $get('letter_profile_id'),
                'permit_company_id' => $get('permit_company_id'), 'department_id' => $departmentId,
                'document_type_id' => $get('document_type_id'), 'work_project_id' => $get('work_project_id'),
                'location_code' => $get('location_code'), 'document_date' => $get('document_date'),
            ]);

            return app(DocumentNumberService::class)->preview($letter);
        } catch (Throwable $exception) {
            return 'Preview is not available. Complete the data used by the mail template or request that the mail manager examine the template.';
        }
    }

    private static function isNumberLocked(?OutgoingLetter $record): bool
    {
        return $record?->status === 'issued' || $record?->status === 'archived';
    }

    private static function statusOptions(): array
    {
        return ['draft' => 'Draft', 'issued' => 'Issue', 'archived' => 'Archived', 'cancelled' => 'Cancelled'];
    }

    private static function statusLabel(string $status): string
    {
        return static::statusOptions()[$status] ?? $status;
    }
}
