<?php

namespace App\Filament\Resources\HrKpmogLetters;

use App\Filament\LocalizedResource as Resource;
use App\Filament\Resources\HrKpmogLetters\Pages\EditHrKpmogLetter;
use App\Filament\Resources\HrKpmogLetters\Pages\ListHrKpmogLetters;
use App\Models\OutgoingLetter;
use App\Services\DocumentNumberService;
use App\Services\HrKpmogLetterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
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

class HrKpmogLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'HR KPMOG';

    protected static ?string $modelLabel = 'KPMOG HR Letter';

    protected static ?string $pluralModelLabel = 'KPMOG HR Letter';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('KPMOG HR mail number'))
                ->extraAttributes(['class' => 'hr-letter-form'])
                ->description(__('The number was already reserved when the draft was created. The number did not change when the mail data was completed.'))
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label(__('Mail number'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('status_label')
                        ->label(__('Status'))
                        ->content(fn (?OutgoingLetter $record): string => $record?->status === 'draft' ? __('Draft') : __('Issue')),
                    DatePicker::make('document_date')
                        ->label(__('Mail date'))
                        ->required(),
                ]),
            Section::make(__('Mail content'))
                ->extraAttributes(['class' => 'hr-letter-form'])
                ->description(__('Complete this content, then click to save the draft. Message can be published after the data is correct.'))
                ->columns(2)
                ->schema([
                    TextInput::make('subject')
                        ->label(__('Mail description'))
                        ->helperText(__('Example: Introduction MCU.'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('recipient')
                        ->label(__('& Project/HO Name'))
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('Needs'))
                        ->maxLength(4000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_date')->label(__('Date'))->date('d M Y')->sortable(),
                TextColumn::make('document_number')->label(__('Mail number'))->searchable()->sortable(),
                TextColumn::make('subject')->label(__('Mail description'))->placeholder(__('Need to be equipped'))->searchable()->wrap(),
                TextColumn::make('recipient')->label(__('& Project/HO Name'))->placeholder('-')->searchable()->wrap(),
                TextColumn::make('description')->label(__('Needs'))->placeholder('-')->limit(40)->wrap(),
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
            ->emptyStateHeading(__('No HR KPMOG letter yet'))
            ->emptyStateDescription(__('Use the next number to create the first mail draft.'))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_next_number')
                    ->label(fn (): string => __('Use number ').app(HrKpmogLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label(__('Mail date'))->default(today())->required()
                            ->extraInputAttributes(['class' => 'hr-letter-input']),
                    ])
                    ->modalHeading(fn (): string => __('Use number ').app(HrKpmogLetterService::class)->nextNumber())
                    ->modalDescription(__('Select mail date. The system creates empty drafts and attaches the next number.'))
                    ->modalSubmitActionLabel(__('Use number and draft'))
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(HrKpmogLetterService::class)->createDraft(auth()->user(), $data['document_date']);

                        Notification::make()
                            ->title(__('Draft with number ').$letter->document_number.__(' it\'s been made.'))
                            ->body(__('Complete the mail data, then save the draft before publishing it.'))
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
        return parent::getEloquentQuery()->whereHas('profile', fn (Builder $query): Builder => $query->where('code', 'HR-KPMOG'));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.hr-kpmog') === true
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
            'index' => ListHrKpmogLetters::route('/'),
            'edit' => EditHrKpmogLetter::route('/{record}/edit'),
        ];
    }
}
