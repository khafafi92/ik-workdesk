<?php

namespace App\Filament\Resources\KpmogProjectLetters;

use App\Exports\KpmogProjectLettersExport;
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
use Filament\Resources\Resource;
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

    protected static ?string $modelLabel = 'Surat KPMOG Project / BD';

    protected static ?string $pluralModelLabel = 'Surat KPMOG Project / BD';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nomor surat KPMOG')
                ->description('Nomor urut dan departemen sudah dipilih saat draft dibuat. Tanggal surat dapat diperbarui sebelum diterbitkan.')
                ->columns(3)
                ->schema([
                    Placeholder::make('document_number')
                        ->label('Nomor surat')
                        ->content(fn (?OutgoingLetter $record): string => $record?->document_number ?? '-'),
                    Placeholder::make('department_label')
                        ->label('Departemen')
                        ->content(fn (?OutgoingLetter $record): string => $record?->department?->code ?? '-'),
                    DatePicker::make('document_date')
                        ->label('Tanggal surat')
                        ->required(),
                ]),
            Section::make('Isi surat')
                ->description('Lengkapi data sesuai register surat Team Project atau BD, lalu simpan draft.')
                ->columns(2)
                ->schema([
                    TextInput::make('pin')->label('PIN')->maxLength(255),
                    TextInput::make('pic_name')->label('PIC')->maxLength(255),
                    TextInput::make('recipient')->label('Tujuan surat')->maxLength(255)->columnSpanFull(),
                    TextInput::make('subject')
                        ->label('Perihal surat')
                        ->helperText('Contoh: Permohonan Pembayaran Termin Pertama.')
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
                TextColumn::make('document_number')->label('Nomor surat')->searchable()->sortable()->wrap(),
                TextColumn::make('department.code')->label('Dept')->sortable(),
                TextColumn::make('document_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('pin')->label('PIN')->placeholder('-')->searchable(),
                TextColumn::make('pic_name')->label('PIC')->placeholder('-')->searchable(),
                TextColumn::make('recipient')->label('Tujuan surat')->placeholder('-')->searchable()->wrap(),
                TextColumn::make('subject')->label('Perihal surat')->placeholder('Perlu dilengkapi')->searchable()->wrap(),
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
            ->emptyStateHeading('Belum ada surat KPMOG Project / BD')
            ->emptyStateDescription('Gunakan nomor berikutnya untuk membuat draft surat pertama.')
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
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
                    ->modalDescription('Gunakan kolom dari master Excel. Nomor surat yang sudah ada tidak akan ditimpa dan akan dilaporkan setelah impor.')
                    ->modalSubmitActionLabel('Import dan periksa data')
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
                                ->title('Import register selesai')
                                ->body("Masuk: {$result['created']}; sudah ada: {$result['skippedExisting']}; duplikat dalam file: {$result['skippedDuplicate']}; tidak valid: {$result['skippedInvalid']}. {$details}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Import register gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('use_next_number')
                    ->label(fn (): string => 'Gunakan nomor '.app(KpmogProjectLetterService::class)->nextNumber())
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->form([
                        DatePicker::make('document_date')->label('Tanggal surat')->default(today())->required(),
                        Select::make('department_id')
                            ->label('Departemen')
                            ->options(fn (): array => app(KpmogProjectLetterService::class)->departmentOptions())
                            ->required(),
                    ])
                    ->modalHeading(fn (): string => 'Gunakan nomor '.app(KpmogProjectLetterService::class)->nextNumber())
                    ->modalDescription('Pilih tanggal dan departemen. Sistem mencadangkan nomor urut serta membentuk nomor surat KPMOG.')
                    ->modalSubmitActionLabel('Gunakan nomor dan buat draft')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (array $data): void {
                        $letter = app(KpmogProjectLetterService::class)->createDraft(
                            auth()->user(),
                            $data['document_date'],
                            (int) $data['department_id'],
                        );

                        Notification::make()
                            ->title('Draft '.$letter->document_number.' sudah dibuat.')
                            ->body('Lengkapi PIN, PIC, tujuan, dan perihal surat sebelum menerbitkannya.')
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
