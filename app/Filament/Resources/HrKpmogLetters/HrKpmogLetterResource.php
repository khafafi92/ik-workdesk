<?php

namespace App\Filament\Resources\HrKpmogLetters;

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
use Filament\Resources\Resource;
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
            Section::make('KPMOG HR mail number')
                ->extraAttributes(['class' => 'hr-letter-form'])
                ->description('The number was already reserved when the draft was created. The number did not change when the mail data was completed.')
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label('Mail number')
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('status_label')
                        ->label('Status')
                        ->content(fn (?OutgoingLetter $record): string => $record?->status === 'draft' ? 'Draft' : 'Issue'),
                    DatePicker::make('document_date')
                        ->label('Mail date')
                        ->required(),
                ]),
            Section::make('Mail content')
                ->extraAttributes(['class' => 'hr-letter-form'])
                ->description('Complete this content, then click to save the draft. Message can be published after the data is correct.')
                ->columns(2)
                ->schema([
                    TextInput::make('subject')
                        ->label('Mail description')
                        ->helperText('Example: Introduction MCU.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('recipient')
                        ->label('& Project/HO Name')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Needs')
                        ->maxLength(4000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('document_number')->label('Mail number')->searchable()->sortable(),
                TextColumn::make('subject')->label('Mail description')->placeholder('Need to be equipped')->searchable()->wrap(),
                TextColumn::make('recipient')->label('& Project/HO Name')->placeholder('-')->searchable()->wrap(),
                TextColumn::make('description')->label('Needs')->placeholder('-')->limit(40)->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'issued' ? 'Issue' : ($state === 'cancelled' ? 'Cancelled' : 'Draft'))
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    'draft' => 'Draft',
                    'issued' => 'Issue',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->emptyStateHeading('No HR KPMOG letter yet')
            ->emptyStateDescription('Use the next number to create the first mail draft.')
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_next_number')
                    ->label(fn (): string => 'Use number '.app(HrKpmogLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label('Mail date')->default(today())->required()
                            ->extraInputAttributes(['class' => 'hr-letter-input']),
                    ])
                    ->modalHeading(fn (): string => 'Use number '.app(HrKpmogLetterService::class)->nextNumber())
                    ->modalDescription('Select mail date. The system creates empty drafts and attaches the next number.')
                    ->modalSubmitActionLabel('Use number and draft')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(HrKpmogLetterService::class)->createDraft(auth()->user(), $data['document_date']);

                        Notification::make()
                            ->title('Draft with number '.$letter->document_number.' it\'s been made.')
                            ->body('Complete the mail data, then save the draft before publishing it.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('Publish mail')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Make sure the mail data is correct. The mail number will remain listed as the print number.')
                    ->visible(fn (OutgoingLetter $record): bool => static::canIssue($record))
                    ->action(function (OutgoingLetter $record): void {
                        app(DocumentNumberService::class)->issue($record, auth()->user());
                        Notification::make()->title('Correspondence '.$record->document_number.' successfully published.')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel draft')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The mail number remains recorded as canceled and will not be reused.')
                    ->visible(fn (OutgoingLetter $record): bool => $record->status === 'draft' && static::canEdit($record))
                    ->action(function (OutgoingLetter $record): void {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Draft '.$record->document_number.' cancelled.')->success()->send();
                    }),
                EditAction::make()->label('Complete draft')->visible(fn (OutgoingLetter $record): bool => static::canEdit($record)),
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
