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

    protected static ?string $modelLabel = 'Surat HR KPMOG';

    protected static ?string $pluralModelLabel = 'Surat HR KPMOG';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nomor surat HR KPMOG')
                ->description('Nomor sudah dicadangkan saat draft dibuat. Nomor tidak berubah ketika data surat dilengkapi.')
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label('Nomor surat')
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('status_label')
                        ->label('Status')
                        ->content(fn (?OutgoingLetter $record): string => $record?->status === 'draft' ? 'Draft' : 'Terbit'),
                    DatePicker::make('document_date')
                        ->label('Tanggal surat')
                        ->required(),
                ]),
            Section::make('Isi surat')
                ->description('Lengkapi isi ini, lalu klik simpan draft. Surat dapat diterbitkan setelah data sudah benar.')
                ->columns(2)
                ->schema([
                    TextInput::make('subject')
                        ->label('Keterangan surat')
                        ->helperText('Contoh: Surat Pengantar MCU.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('recipient')
                        ->label('Nama & Project/HO')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Kebutuhan')
                        ->maxLength(4000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('document_number')->label('Nomor surat')->searchable()->sortable(),
                TextColumn::make('subject')->label('Keterangan surat')->placeholder('Perlu dilengkapi')->searchable()->wrap(),
                TextColumn::make('recipient')->label('Nama & Project/HO')->placeholder('-')->searchable()->wrap(),
                TextColumn::make('description')->label('Kebutuhan')->placeholder('-')->limit(40)->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'issued' ? 'Terbit' : ($state === 'cancelled' ? 'Dibatalkan' : 'Draft'))
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    'draft' => 'Draft',
                    'issued' => 'Terbit',
                    'cancelled' => 'Dibatalkan',
                ]),
            ])
            ->emptyStateHeading('Belum ada surat HR KPMOG')
            ->emptyStateDescription('Gunakan nomor berikutnya untuk membuat draft surat pertama.')
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_next_number')
                    ->label(fn (): string => 'Gunakan nomor '.app(HrKpmogLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label('Tanggal surat')->default(today())->required(),
                    ])
                    ->modalHeading(fn (): string => 'Gunakan nomor '.app(HrKpmogLetterService::class)->nextNumber())
                    ->modalDescription('Pilih tanggal surat. Sistem membuat draft kosong dan mencadangkan nomor berikutnya.')
                    ->modalSubmitActionLabel('Gunakan nomor dan buat draft')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(HrKpmogLetterService::class)->createDraft(auth()->user(), $data['document_date']);

                        Notification::make()
                            ->title('Draft dengan nomor '.$letter->document_number.' sudah dibuat.')
                            ->body('Lengkapi data surat, lalu simpan draft sebelum menerbitkannya.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('Terbitkan surat')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Pastikan data surat benar. Nomor surat akan tetap tercatat sebagai nomor terbit.')
                    ->visible(fn (OutgoingLetter $record): bool => static::canIssue($record))
                    ->action(function (OutgoingLetter $record): void {
                        app(DocumentNumberService::class)->issue($record, auth()->user());
                        Notification::make()->title('Surat '.$record->document_number.' berhasil diterbitkan.')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Batalkan draft')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Nomor surat tetap tercatat sebagai dibatalkan dan tidak akan dipakai kembali.')
                    ->visible(fn (OutgoingLetter $record): bool => $record->status === 'draft' && static::canEdit($record))
                    ->action(function (OutgoingLetter $record): void {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Draft '.$record->document_number.' dibatalkan.')->success()->send();
                    }),
                EditAction::make()->label('Lengkapi draft')->visible(fn (OutgoingLetter $record): bool => static::canEdit($record)),
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
