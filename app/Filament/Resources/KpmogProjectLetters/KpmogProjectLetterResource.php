<?php

namespace App\Filament\Resources\KpmogProjectLetters;

use App\Exports\KpmogProjectLettersExport;
use App\Filament\LocalizedResource as Resource;
use App\Filament\Resources\KpmogProjectLetters\Pages\EditKpmogProjectLetter;
use App\Filament\Resources\KpmogProjectLetters\Pages\ListKpmogProjectLetters;
use App\Models\OutgoingLetter;
use App\Services\DocumentNumberService;
use App\Services\KpmogProjectLetterImportService;
use App\Services\KpmogProjectLetterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Maatwebsite\Excel\Facades\Excel;

class KpmogProjectLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'KPMOG Project / BD';

    protected static ?string $modelLabel = 'KPMOG Project / BD Letter';

    protected static ?string $pluralModelLabel = 'KPMOG Project / BD Letters';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('KPMOG mail number'))
                ->description(__('Sort numbers and departments were selected when drafts were created. Mail dates could be updated before they were published.'))
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label(__('Mail number'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('department_label')
                        ->label(__('Department'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->department?->code ?? '-'),
                    DatePicker::make('document_date')
                        ->label(__('Mail date'))
                        ->required(),
                ]),
            Section::make(__('Mail content'))
                ->description(__('Complete data according to Team Project or BD mail registers, then save the draft.'))
                ->columns(2)
                ->schema([
                    TextInput::make('pin')->label('PIN')->maxLength(255),
                    TextInput::make('pic_name')->label('PIC')->maxLength(255),
                    TextInput::make('recipient')->label(__('Mail destination'))->maxLength(255)->columnSpanFull(),
                    TextInput::make('subject')
                        ->label(__('Subject'))
                        ->helperText(__('Example: Request for first installment payment.'))
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
                TextColumn::make('document_number')->label(__('Mail number'))->searchable()->sortable()->wrap(),
                TextColumn::make('department.code')->label('Dept')->sortable(),
                TextColumn::make('document_date')->label(__('Date'))->date('d M Y')->sortable(),
                TextColumn::make('pin')->label('PIN')->placeholder('-')->searchable(),
                TextColumn::make('pic_name')->label('PIC')->placeholder('-')->searchable(),
                TextColumn::make('recipient')->label(__('Mail destination'))->placeholder('-')->searchable()->wrap(),
                TextColumn::make('subject')->label(__('Subject'))->placeholder(__('Not entered'))->searchable()->wrap(),
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
            ->emptyStateHeading(__('No KPMOG Project / BD mail yet'))
            ->emptyStateDescription(__('Use the next number to create the first mail draft.'))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('export_excel')
                    ->label(__('Export Excel'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->action(fn () => Excel::download(
                        new KpmogProjectLettersExport(static::getEloquentQuery()->orderBy('document_date')->orderBy('running_number')),
                        'register-surat-kpmog-project-bd.xlsx',
                    )),
                Action::make('import_excel')
                    ->label('Import Excel')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('primary')
                    ->modalHeading('Import register KPMOG Project / BD')
                    ->modalDescription(__('Use columns from the Excel master. The existing mail number will not be overwritten and will be reported after import.'))
                    ->modalSubmitActionLabel(__('Import and check data'))
                    ->form([
                        FileUpload::make('file')
                            ->label('File Excel')
                            ->disk('local')
                            ->directory('kpmog-project-letter-imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->preserveFilenames()
                            ->required(),
                    ])
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        try {
                            $result = app(KpmogProjectLetterImportService::class)->import($data['file'], auth()->user());
                            $details = collect($result['messages'])->take(5)->implode(' ');

                            Notification::make()
                                ->title(__('Import register complete'))
                                ->body("Login: {$result['created']}; already exists: {$result['skippedExisting']}; duplicate in file: {$result['skippedDuplicate']}; invalid: {$result['skippedInvalid']}. {$details}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('Import register failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('use_next_number')
                    ->label(fn (): string => __('Use number ').app(KpmogProjectLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label(__('Mail date'))->default(today())->required(),
                        Select::make('department_id')
                            ->label(__('Department'))
                            ->options(fn (): array => app(KpmogProjectLetterService::class)->departmentOptions())
                            ->required(),
                    ])
                    ->modalHeading(fn (): string => __('Use number ').app(KpmogProjectLetterService::class)->nextNumber())
                    ->modalDescription(__('Select date and department. The system assigns sequence numbers and forms KPMOG mail numbers.'))
                    ->modalSubmitActionLabel(__('Use number and draft'))
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(KpmogProjectLetterService::class)->createDraft(
                            auth()->user(),
                            $data['document_date'],
                            (int) $data['department_id'],
                        );

                        Notification::make()
                            ->title('Draft '.$letter->document_number.__(' it\'s been made.'))
                            ->body(__('Complete PIN, PIC, destinations, and letter matters before publishing them.'))
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
                        app(DocumentNumberService::class)->issue($record, auth()->user());
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
            'numberingTemplate',
            fn (Builder $query): Builder => $query->where('name', 'Nomor surat KPMOG Project dan BD'),
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.kpmog-project-bd') === true
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
            'index' => ListKpmogProjectLetters::route('/'),
            'edit' => EditKpmogProjectLetter::route('/{record}/edit'),
        ];
    }
}
