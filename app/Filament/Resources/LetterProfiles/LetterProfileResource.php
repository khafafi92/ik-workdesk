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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Letter Profiles';

    protected static ?string $modelLabel = 'Letter Profiles';

    protected static ?string $pluralModelLabel = 'Letter Profiles';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mail profile')
                ->description('Profile determines the form group and format the number used. Create a new profile if there are new units or needs mail.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Profile code')
                        ->helperText('Internal code for distinguishing profiles, for example HR-APCA or PROJECT-BD-KPMOG.')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('Profile name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Select::make('permit_company_id')
                        ->label('Default entity')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('department_id')
                        ->label('Departemen default')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Empty if profile can be used by more than one department.'),
                    Select::make('form_variant')
                        ->label('Form forms')
                        ->options([
                            'general' => 'General mail',
                            'hr' => 'HR Letter',
                            'project_bd' => 'Team Project/BD Mail',
                        ])
                        ->helperText('Determines the content group that shows up when creating mail.')
                        ->default('general')
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    Textarea::make('description')
                        ->label('Description')
                        ->helperText('Note the use of the profile to make other admins choose the correct profile.')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Profiles')->searchable()->sortable(),
                TextColumn::make('company.code')->label('Entity')->placeholder('-')->sortable(),
                TextColumn::make('department.code')->label('Department')->placeholder('More than one')->sortable(),
                TextColumn::make('form_variant')
                    ->label('Form')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hr' => 'HR',
                        'project_bd' => 'Team Project/BD',
                        default => 'General',
                    }),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('permit_company_id')->label('Entity')->relationship('company', 'name'),
                SelectFilter::make('form_variant')->label('Form forms')->options([
                    'general' => 'General mail',
                    'hr' => 'HR Letter',
                    'project_bd' => 'Team Project/BD Mail',
                ]),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()->label('Create a mail profile'),
            ])
            ->recordActions([
                EditAction::make()->label('Change profile'),
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
