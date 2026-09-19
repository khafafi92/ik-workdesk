<?php

namespace App\Filament\Resources\ActivityCategories;

use App\Filament\Resources\ActivityCategories\Pages\CreateActivityCategory;
use App\Filament\Resources\ActivityCategories\Pages\EditActivityCategory;
use App\Filament\Resources\ActivityCategories\Pages\ListActivityCategories;
use App\Filament\Resources\ActivityCategories\Schemas\ActivityCategoryForm;
use App\Filament\Resources\ActivityCategories\Tables\ActivityCategoriesTable;
use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Models\ActivityCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivityCategoryResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = ActivityCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Activity Categories';

    protected static ?string $modelLabel = 'Activity Category';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return ActivityCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityCategoriesTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityCategories::route('/'),
            'create' => CreateActivityCategory::route('/create'),
            'edit' => EditActivityCategory::route('/{record}/edit'),
        ];
    }
}
