<?php

namespace App\Filament\Resources\LegalSubjectCategories;

use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Filament\Resources\LegalSubjectCategories\Pages\CreateLegalSubjectCategory;
use App\Filament\Resources\LegalSubjectCategories\Pages\EditLegalSubjectCategory;
use App\Filament\Resources\LegalSubjectCategories\Pages\ListLegalSubjectCategories;
use App\Models\LegalSubjectCategory;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegalSubjectCategoryResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = LegalSubjectCategory::class;

    protected static ?string $menuPermissionCode = 'master.subject-categories.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Subject Category')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Subject Category')->searchable()->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->defaultSort('name');
    }

    public static function getNavigationLabel(): string
    {
        return 'Subject Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalSubjectCategories::route('/'),
            'create' => CreateLegalSubjectCategory::route('/create'),
            'edit' => EditLegalSubjectCategory::route('/{record}/edit'),
        ];
    }
}
