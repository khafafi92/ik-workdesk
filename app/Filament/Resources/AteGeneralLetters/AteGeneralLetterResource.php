<?php

namespace App\Filament\Resources\AteGeneralLetters;

use App\Filament\Resources\AteGeneralLetters\Pages\EditAteGeneralLetter;
use App\Filament\Resources\AteGeneralLetters\Pages\ListAteGeneralLetters;
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
use Filament\Resources\Resource;
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

    protected static ?string $navigationLabel = 'Surat Umum APCA';

    protected static ?string $modelLabel = 'Surat Umum APCA';

    protected static ?string $pluralModelLabel = 'Surat Umum APCA';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nomor dan jenis surat')
                ->description('Jenis surat dipilih saat nomor digunakan. Bila jenis salah, batalkan draft lalu gunakan nomor baru agar riwayat nomor tetap jelas.')
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label('Nomor surat')
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('document_type_label')
                        ->label('Jenis surat')
                        ->content(fn (?OutgoingLetter $record): string => $record?->documentType?->name ?? '-'),
                    DatePicker::make('document_date')
                        ->label('Tanggal surat')
                        ->required(),
                ]),
            Section::make('Isi surat')
                ->description('Lengkapi keterangan dan tujuan surat sebelum diterbitkan.')
                ->columns(2)
                ->schema([
                    TextInput::make('recipient')->label('Tujuan surat')->maxLength(255),
                    TextInput::make('pin')->label('PIN')->maxLength(255),
                    TextInput::make('pic_name')->label('PIC')->maxLength(255),
                    TextInput::make('subject')
                        ->label('Keterangan surat')
                        ->helperText('Contoh: Permohonan informasi harga dan spesifikasi.')
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
                TextColumn::make('documentType.name')->label('Jenis surat')->searchable()->wrap(),
                TextColumn::make('recipient')->label('Tujuan surat')->placeholder('-')->searchable()->wrap(),
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
            ->emptyStateHeading('Belum ada surat umum APCA')
            ->emptyStateDescription('Pilih jenis surat, lalu gunakan nomor untuk membuat draft pertama.')
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('use_ate_number')
                    ->label('Pilih jenis dan gunakan nomor')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        Select::make('letter_kind')
                            ->label('Jenis surat')
                            ->options(app(AteGeneralLetterService::class)->kinds())
                            ->required(),
                        DatePicker::make('document_date')->label('Tanggal surat')->default(today())->required(),
                    ])
                    ->modalHeading('Pilih jenis surat APCA')
                    ->modalDescription('Setiap jenis surat memiliki urutan nomor sendiri. Sistem langsung membentuk nomor sesuai pola yang telah ditetapkan.')
                    ->modalSubmitActionLabel('Gunakan nomor dan buat draft')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(AteGeneralLetterService::class)->createDraft(
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
            'profile',
            fn (Builder $query): Builder => $query->where('code', 'ATE-UMUM'),
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
}
