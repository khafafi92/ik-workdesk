<?php

namespace App\Filament\Resources\WorkProjects;

use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Filament\Resources\WorkProjects\Pages\CreateWorkProject;
use App\Filament\Resources\WorkProjects\Pages\EditWorkProject;
use App\Filament\Resources\WorkProjects\Pages\ListWorkProjects;
use App\Filament\Resources\WorkProjects\Schemas\WorkProjectForm;
use App\Filament\Resources\WorkProjects\Tables\WorkProjectsTable;
use App\Models\WorkProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkProjectResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = WorkProject::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?string $modelLabel = 'Project';

    protected static ?string $pluralModelLabel = 'Projects';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return WorkProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkProjectsTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkProjects::route('/'),
            'create' => CreateWorkProject::route('/create'),
            'edit' => EditWorkProject::route('/{record}/edit'),
        ];
    }
}
