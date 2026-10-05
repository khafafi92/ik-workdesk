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

    protected static ?string $navigationLabel = 'Surat Keluar';

    protected static ?string $modelLabel = 'Surat Keluar';

    protected static ?string $pluralModelLabel = 'Surat Keluar';

    protected static ?string $recordTitleAttribute = 'document_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1. Tentukan profil dan identitas surat')
                ->description('Pilih profil sesuai kelompok surat. Entitas dan departemen akan terisi dari profil, lalu lengkapi jenis serta tanggal surat.')
                ->columns(3)
                ->schema([
                    Select::make('letter_profile_id')->label('Profil surat')->relationship('profile', 'name')->searchable()->preload()->required()->live()
                        ->helperText('Contoh: Surat Umum APCA, HR APCA, HR KPMOG, atau Team Project/BD KPMOG.')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $profile = LetterProfile::find($state);

                            if ($profile === null) {
                                return;
                            }

                            $set('permit_company_id', $profile->permit_company_id);
                            $set('department_id', $profile->department_id);
                        })
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Select::make('permit_company_id')->label('Entitas penerbit')->relationship('company', 'name')->searchable()->preload()->required()->live()
                        ->helperText('Pilih entitas yang menerbitkan surat.')
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Select::make('department_id')->label('Departemen penerbit')->relationship('department', 'name')->searchable()->preload()->live()
                        ->helperText('Pilih departemen pengirim jika surat menggunakan kode departemen.')
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Select::make('document_type_id')->label('Jenis surat')->relationship('documentType', 'name')->searchable()->preload()->live()
                        ->helperText('Jenis surat menentukan kode pada nomor surat, bila template menggunakannya.')
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    DatePicker::make('document_date')->label('Tanggal surat')->default(today())->required()->live()
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                ]),
            Section::make('2. Lengkapi tujuan dan isi')
                ->description('Isi penerima dan perihal surat. Project, PIN, nama PIC, kode lokasi, serta keterangan hanya diisi bila diperlukan oleh surat ini.')
                ->columns(3)
                ->schema([
                    Select::make('work_project_id')->label('Project / Job Order')->relationship('project', 'code')->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->code.' - '.$record->name))->searchable()->preload(),
                    TextInput::make('pin')->label('PIN')->maxLength(100),
                    Select::make('pic_user_id')->label('PIC user')->relationship('picUser', 'name')->searchable()->preload(),
                    TextInput::make('pic_name')->label('Nama PIC')->maxLength(255),
                    TextInput::make('recipient')->label('Tujuan surat')->helperText('Nama instansi, perusahaan, atau pihak penerima surat.')->maxLength(255),
                    TextInput::make('subject')->label('Perihal surat')->helperText('Tulis ringkasan isi surat secara singkat.')->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('location_code')->label('Kode lokasi / referensi')->maxLength(100)->live()
                        ->helperText('Isi hanya jika template nomor surat menggunakan kode lokasi.'),
                    Textarea::make('description')->label('Keterangan tambahan')->helperText('Opsional. Isi jika perlu catatan internal untuk surat ini.')->maxLength(4000)->columnSpanFull(),
                ]),
            Section::make('3. Tinjau nomor dan simpan draft')
                ->description('Nomor di bawah adalah preview. Simpan sebagai draft terlebih dahulu. Nomor menjadi final dan dicadangkan hanya saat surat diterbitkan.')
                ->schema([
                    Toggle::make('is_legacy_number')->label('Input nomor existing / legacy')->live()
                        ->helperText('Gunakan hanya untuk mencatat surat lama yang sudah memiliki nomor.')
                        ->visible(fn (): bool => static::canUseLegacyNumbers())
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    TextInput::make('document_number')->label('Nomor surat existing')->maxLength(500)->unique(ignoreRecord: true)
                        ->visible(fn (Get $get): bool => (bool) $get('is_legacy_number'))
                        ->required(fn (Get $get): bool => (bool) $get('is_legacy_number'))
                        ->disabled(fn (?OutgoingLetter $record): bool => static::isNumberLocked($record)),
                    Placeholder::make('number_preview')->label('Preview nomor surat')->content(fn (Get $get): string => static::preview($get)),
                    Placeholder::make('number_status')->label('Status')->content(fn (?OutgoingLetter $record): string => $record?->status ? static::statusLabel($record->status) : 'Draft'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('document_number')->label('Nomor surat')->searchable()->sortable()->placeholder('Draft'),
            TextColumn::make('document_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('profile.name')->label('Profil')->placeholder('-')->toggleable(),
            TextColumn::make('company.code')->label('Company')->sortable(),
            TextColumn::make('department.code')->label('Department')->placeholder('-'),
            TextColumn::make('documentType.name')->label('Jenis surat')->placeholder('-')->toggleable(),
            TextColumn::make('project.code')->label('Project / Job Order')->placeholder('-')->searchable()->toggleable(),
            TextColumn::make('pic_name')->label('PIC')->placeholder('-')->searchable()->toggleable(),
            TextColumn::make('recipient')->label('Tujuan')->limit(35)->searchable()->toggleable(),
            TextColumn::make('subject')->label('Perihal')->limit(55)->searchable(),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => static::statusLabel($state))
                ->color(fn (string $state): string => match ($state) {
                    'issued' => 'success', 'archived' => 'gray', 'cancelled' => 'danger', default => 'warning'
                }),
        ])->filters([
            SelectFilter::make('letter_profile_id')->label('Profil surat')->relationship('profile', 'name'),
            SelectFilter::make('permit_company_id')->label('Entitas penerbit')->relationship('company', 'name'),
            SelectFilter::make('department_id')->label('Departemen penerbit')->relationship('department', 'name'),
            SelectFilter::make('document_type_id')->label('Jenis surat')->relationship('documentType', 'name'),
            SelectFilter::make('work_project_id')->label('Project')->relationship('project', 'code'),
            SelectFilter::make('status')->label('Status')->options(static::statusOptions()),
            Filter::make('document_date')->label('Tahun / bulan')->form([
                Select::make('year')->label('Tahun')->options(fn (): array => OutgoingLetter::query()
                    ->orderByDesc('document_date')
                    ->pluck('document_date')
                    ->map(fn ($date): int => Carbon::parse($date)->year)
                    ->unique()
                    ->mapWithKeys(fn (int $year): array => [$year => $year])
                    ->all()),
                Select::make('month')->label('Bulan')->options(collect(range(1, 12))->mapWithKeys(
                    fn (int $month): array => [$month => Carbon::create()->month($month)->translatedFormat('F')]
                )->all()),
            ])->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['year'] ?? null, fn (Builder $builder, $year): Builder => $builder->whereYear('document_date', $year))
                    ->when($data['month'] ?? null, fn (Builder $builder, $month): Builder => $builder->whereMonth('document_date', $month));
            }),
        ])->defaultSort('document_date', 'desc')->headerActions([
            CreateAction::make()->label('Buat draft surat')->visible(fn (): bool => static::canCreate()),
        ])->recordActions([
            Action::make('issue')
                ->label('Terbitkan Surat')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Pastikan data surat benar. Nomor surat akan menjadi final dan tidak dapat digunakan kembali.')
                ->visible(fn (OutgoingLetter $record): bool => static::canIssue($record))
                ->action(function (OutgoingLetter $record): void {
                    app(DocumentNumberService::class)->issue($record, auth()->user());
                    Notification::make()->title('Surat berhasil diterbitkan.')->success()->send();
                }),
            Action::make('archive')
                ->label('Arsipkan')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (OutgoingLetter $record): bool => $record->status === 'issued' && static::canManageLifecycle())
                ->action(function (OutgoingLetter $record): void {
                    $record->update(['status' => 'archived']);
                    Notification::make()->title('Surat telah diarsipkan.')->success()->send();
                }),
            Action::make('cancel')
                ->label('Batalkan draft')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (OutgoingLetter $record): bool => $record->status === 'draft' && static::canManageLifecycle())
                ->action(function (OutgoingLetter $record): void {
                    $record->update(['status' => 'cancelled']);
                    Notification::make()->title('Draft surat dibatalkan.')->success()->send();
                }),
            EditAction::make()->label('Ubah draft')->visible(fn (OutgoingLetter $record): bool => static::canEdit($record)),
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
            return filled($get('document_number')) ? (string) $get('document_number') : 'Masukkan nomor surat existing.';
        }

        if (blank($get('permit_company_id')) || blank($get('document_date'))) {
            return 'Pilih entitas penerbit dan tanggal surat untuk melihat preview.';
        }

        try {
            $letter = new OutgoingLetter([
                'letter_profile_id' => $get('letter_profile_id'),
                'permit_company_id' => $get('permit_company_id'), 'department_id' => $get('department_id'),
                'document_type_id' => $get('document_type_id'), 'work_project_id' => $get('work_project_id'),
                'location_code' => $get('location_code'), 'document_date' => $get('document_date'),
            ]);

            return app(DocumentNumberService::class)->preview($letter);
        } catch (Throwable $exception) {
            return 'Preview belum tersedia. Lengkapi data yang dipakai template nomor surat atau minta pengelola Surat memeriksa templatenya.';
        }
    }

    private static function isNumberLocked(?OutgoingLetter $record): bool
    {
        return $record?->status === 'issued' || $record?->status === 'archived';
    }

    private static function statusOptions(): array
    {
        return ['draft' => 'Draft', 'issued' => 'Terbit', 'archived' => 'Diarsipkan', 'cancelled' => 'Dibatalkan'];
    }

    private static function statusLabel(string $status): string
    {
        return static::statusOptions()[$status] ?? $status;
    }
}
