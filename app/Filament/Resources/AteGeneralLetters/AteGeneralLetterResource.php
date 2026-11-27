<?php

namespace App\Filament\Resources\AteGeneralLetters;

use App\Filament\LocalizedResource as Resource;
use App\Filament\Resources\AteGeneralLetters\Pages\EditAteGeneralLetter;
use App\Filament\Resources\AteGeneralLetters\Pages\ListAteGeneralLetters;
use App\Models\Department;
use App\Models\OutgoingLetter;
use App\Services\AteGeneralLetterService;
use App\Services\DocumentNumberService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AteGeneralLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'APCA General Correspondence';

    protected static ?string $modelLabel = 'APCA General Correspondence';

    protected static ?string $pluralModelLabel = 'APCA General Correspondence';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Number and type of mail'))
                ->description(__('The type of mail selected when the number is used. The sequence is separated per type and year.'))
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label(__('Mail number'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('document_type_label')
                        ->label(__('Mail type'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->documentType?->name ?? '-'),
                    Select::make('department_id')
                        ->label(__('Department'))
                        ->options(static::departmentOptions())
                        ->searchable()
                        ->required()
                        ->helperText(__('Number uses department code on selected master.'))
                        ->disabled(fn (?OutgoingLetter $record): bool => $record?->status !== 'draft'),
                    DatePicker::make('document_date')
                        ->label(__('Mail date'))
                        ->required()
                        ->disabled(fn (?OutgoingLetter $record): bool => $record?->status !== 'draft'),
                ]),
            Section::make(__('Mail content'))
                ->description(__('Complete the information and purpose of the letter before it is published.'))
                ->columns(2)
                ->schema([
                    TextInput::make('recipient')->label(__('Mail destination'))->maxLength(255),
                    TextInput::make('pic_name')->label('PIC')->maxLength(255),
                    TextInput::make('subject')
                        ->label(__('Mail description'))
                        ->helperText(__('Example: Request price information and specs.'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_date')->label(__('Date'))->date('d M Y')->sortable(),
                TextColumn::make('document_number')->label(__('Mail number'))->searchable()->sortable()->wrap(),
                TextColumn::make('documentType.name')->label(__('Mail type'))->searchable()->wrap(),
                TextColumn::make('department.code')->label(__('Department'))->placeholder('-')->searchable(),
                TextColumn::make('recipient')->label(__('Mail destination'))->placeholder('-')->searchable()->wrap(),
                TextColumn::make('subject')->label(__('Mail description'))->placeholder(__('Need to be equipped'))->searchable()->wrap(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'issued' ? 'Issue' : ($state === 'cancelled' ? 'Cancelled' : 'Draft'))
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options([
                    'draft' => __('Draft'),
                    'issued' => __('Issue'),
                    'cancelled' => __('Cancelled'),
                ]),
            ])
            ->emptyStateHeading(__('No public APCA mail yet'))
            ->emptyStateDescription(__('Select mail type, then use number to create the first draft.'))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_ate_number')
                    ->label(__('Select type and use number'))
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        Select::make('letter_kind')
                            ->label(__('Mail type'))
                            ->options(app(AteGeneralLetterService::class)->kinds())
                            ->required(),
                        Select::make('department_id')
                            ->label(__('Department'))
                            ->options(static::departmentOptions())
                            ->searchable()
                            ->required()
                            ->helperText(__('Number uses department code on selected master.')),
                        DatePicker::make('document_date')->label(__('Mail date'))->default(today())->required(),
                    ])
                    ->modalHeading(__('Select APCA mail type'))
                    ->modalDescription(__('Sort separate numbers for each type of mail and year. Select the department to publish the mail.'))
                    ->modalSubmitActionLabel(__('Use number and draft'))
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(AteGeneralLetterService::class)->createDraft(
                            auth()->user(),
                            $data['document_date'],
                            $data['letter_kind'],
                            (int) $data['department_id'],
                        );

                        Notification::make()
                            ->title('Draft '.$letter->document_number.__(' it\'s been made.'))
                            ->body(__('Complete the mail information, then save the draft before publishing it.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label(__('Publish mail'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('Make sure the mail data is correct. The mail number will remain listed as the print number.'))
                    ->visible(fn (OutgoingLetter $record): bool => static::canIssue($record))
                    ->action(function (OutgoingLetter $record): void {
                        $letter = app(AteGeneralLetterService::class)->refreshNumber($record);
                        app(DocumentNumberService::class)->issue($letter, auth()->user());
                        Notification::make()->title(__('Correspondence ').$record->document_number.__(' successfully published.'))->success()->send();
                    }),
                Action::make('cancel')
                    ->label(__('Cancel draft'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The mail number remains recorded as canceled and will not be reused.'))
                    ->visible(fn (OutgoingLetter $record): bool => $record->status === 'draft' && static::canEdit($record))
                    ->action(function (OutgoingLetter $record): void {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Draft '.$record->document_number.__(' cancelled.'))->success()->send();
                    }),
                EditAction::make()->label(__('Complete draft'))->visible(fn (OutgoingLetter $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas(
            'profile',
            fn (Builder $query): Builder => $query->where('code', 'ATE-UMUM'),
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.ate-general') === true
            && auth()->user()?->hasPermission('letters.view') === true;
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

    public static function canIssue(OutgoingLetter $record): bool
    {
        return $record->status === 'draft'
            && $record->subject !== 'Belum diisi'
            && auth()->user()?->hasPermission('letters.issue') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Surat';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAteGeneralLetters::route('/'),
            'edit' => EditAteGeneralLetter::route('/{record}/edit'),
        ];
    }

    private static function departmentOptions(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Department $department): array => [
                $department->id => trim($department->code.' - '.$department->name),
            ])
            ->all();
    }
}
