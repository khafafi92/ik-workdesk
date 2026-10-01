<?php

namespace App\Filament\Resources\LetterProfiles;

use App\Filament\Resources\LetterProfiles\Pages\CreateLetterProfile;
use App\Filament\Resources\LetterProfiles\Pages\EditLetterProfile;
use App\Filament\Resources\LetterProfiles\Pages\ListLetterProfiles;
use App\Models\LetterProfile;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LetterProfileResource extends Resource
{
    protected static ?string $model = LetterProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Profil Surat';

    protected static ?string $modelLabel = 'Profil Surat';

    protected static ?string $pluralModelLabel = 'Profil Surat';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil surat')
                ->description('Profil menentukan kelompok formulir dan format nomor yang digunakan. Buat profil baru jika ada unit atau kebutuhan surat baru.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode profil')
                        ->helperText('Kode internal untuk membedakan profil, misalnya HR-APCA atau PROJECT-BD-KPMOG.')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('Nama profil')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Select::make('permit_company_id')
                        ->label('Entitas default')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('department_id')
                        ->label('Departemen default')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Kosongkan bila profil dapat digunakan oleh lebih dari satu departemen.'),
                    Select::make('form_variant')
                        ->label('Bentuk formulir')
                        ->options([
                            'general' => 'Surat umum',
                            'hr' => 'Surat HR',
                            'project_bd' => 'Surat Team Project/BD',
                        ])
                        ->helperText('Menentukan kelompok isian yang tampil saat membuat surat.')
                        ->default('general')
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                    Textarea::make('description')
                        ->label('Keterangan')
                        ->helperText('Catat penggunaan profil agar admin lain memilih profil yang tepat.')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Profil')->searchable()->sortable(),
                TextColumn::make('company.code')->label('Entitas')->placeholder('-')->sortable(),
                TextColumn::make('department.code')->label('Departemen')->placeholder('Lebih dari satu')->sortable(),
                TextColumn::make('form_variant')
                    ->label('Formulir')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hr' => 'HR',
                        'project_bd' => 'Team Project/BD',
                        default => 'Umum',
                    }),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('permit_company_id')->label('Entitas')->relationship('company', 'name'),
                SelectFilter::make('form_variant')->label('Bentuk formulir')->options([
                    'general' => 'Surat umum',
                    'hr' => 'Surat HR',
                    'project_bd' => 'Surat Team Project/BD',
                ]),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()->label('Buat profil surat'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah profil'),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('letters.profiles') === true
            && auth()->user()?->hasPermission('letters.manage') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Surat';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLetterProfiles::route('/'),
            'create' => CreateLetterProfile::route('/create'),
            'edit' => EditLetterProfile::route('/{record}/edit'),
        ];
    }
}
