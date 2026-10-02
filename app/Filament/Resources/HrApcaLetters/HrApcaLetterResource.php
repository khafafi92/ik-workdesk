<?php

namespace App\Filament\Resources\HrApcaLetters;

use App\Filament\Resources\HrApcaLetters\Pages\EditHrApcaLetter;
use App\Filament\Resources\HrApcaLetters\Pages\ListHrApcaLetters;
use App\Models\OutgoingLetter;
use App\Services\DocumentNumberService;
use App\Services\HrApcaLetterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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

class HrApcaLetterResource extends Resource
{
    protected static ?string $model = OutgoingLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'HR APCA';

    protected static ?string $modelLabel = 'Surat HR APCA';

    protected static ?string $pluralModelLabel = 'Surat HR APCA';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nomor surat HR APCA')
                ->description('Nomor urut sudah dicadangkan saat draft dibuat. Format mengikuti jenis dan tanggal surat.')
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
                    Select::make('document_type_id')
                        ->label('Jenis surat')
                        ->options(fn (): array => app(HrApcaLetterService::class)->typeOptions())
                        ->required(),
                    TextInput::make('subject')
                        ->label('Keterangan surat')
                        ->helperText('Contoh: Surat Keterangan Kerja - Rizaldy.')
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
                TextColumn::make('document_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('document_number')->label('Nomor surat')->searchable()->sortable()->wrap(),
                TextColumn::make('subject')->label('Keterangan surat')->placeholder('Perlu dilengkapi')->searchable()->wrap(),
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
            ->emptyStateHeading('Belum ada surat HR APCA')
            ->emptyStateDescription('Gunakan nomor berikutnya untuk membuat draft surat pertama.')
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_next_number')
                    ->label(fn (): string => 'Gunakan nomor '.app(HrApcaLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label('Tanggal surat')->default(today())->required(),
                        Select::make('letter_kind')
                            ->label('Jenis surat')
                            ->options(app(HrApcaLetterService::class)->kinds())
                            ->required(),
                    ])
                    ->modalHeading(fn (): string => 'Gunakan nomor '.app(HrApcaLetterService::class)->nextNumber())
                    ->modalDescription('Pilih tanggal surat. Sistem membuat draft kosong dan mencadangkan nomor berikutnya.')
                    ->modalSubmitActionLabel('Gunakan nomor dan buat draft')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(HrApcaLetterService::class)->createDraft(
                            auth()->user(),
                            $data['document_date'],
                            $data['letter_kind'],
                        );

                        Notification::make()
                            ->title('Draft '.$letter->document_number.' sudah dibuat.')
                            ->body('Lengkapi keterangan surat, lalu simpan draft sebelum menerbitkannya.')
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
        return parent::getEloquentQuery()->whereHas(
            'numberingTemplate',
            fn (Builder $query): Builder => $query->where('name', 'Nomor urut HR APCA'),
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.outgoing') === true
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
            && $record->document_type_id !== null
            && auth()->user()?->hasPermission('letters.issue') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Surat';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrApcaLetters::route('/'),
            'edit' => EditHrApcaLetter::route('/{record}/edit'),
        ];
    }
}
