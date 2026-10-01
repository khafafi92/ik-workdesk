<?php

namespace App\Filament\Resources\TaskCategories;

use App\Filament\Resources\TaskCategories\Pages\CreateTaskCategory;
use App\Filament\Resources\TaskCategories\Pages\EditTaskCategory;
use App\Filament\Resources\TaskCategories\Pages\ListTaskCategories;
use App\Filament\Resources\TaskCategories\Schemas\TaskCategoryForm;
use App\Filament\Resources\TaskCategories\Tables\TaskCategoriesTable;
use App\Models\TaskCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskCategoryResource extends Resource
{
    protected static ?string $model = TaskCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaskCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Task Categories';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Task Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Daily Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    protected static function currentUserCanManageTaskCategories(): bool
    {
        return auth()->user()?->hasPermission('task-categories.manage') === true
            || auth()->user()?->hasPermission('master-data.manage') === true;
    }

    public static function shouldRegisterNavigation(): bool { return static::currentUserCanManageTaskCategories(); }
    public static function canViewAny(): bool { return static::currentUserCanManageTaskCategories(); }
    public static function canCreate(): bool { return static::currentUserCanManageTaskCategories(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::currentUserCanManageTaskCategories(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return static::currentUserCanManageTaskCategories(); }
    public static function canDeleteAny(): bool { return static::currentUserCanManageTaskCategories(); }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskCategories::route('/'),
            'create' => CreateTaskCategory::route('/create'),
            'edit' => EditTaskCategory::route('/{record}/edit'),
        ];
    }
}
