<?php

namespace App\Filament\Resources\TicketCategories;

use App\Filament\Resources\TicketCategories\Pages\CreateTicketCategory;
use App\Filament\Resources\TicketCategories\Pages\EditTicketCategory;
use App\Filament\Resources\TicketCategories\Pages\ListTicketCategories;
use App\Filament\Resources\TicketCategories\Schemas\TicketCategoryForm;
use App\Filament\Resources\TicketCategories\Tables\TicketCategoriesTable;
use App\Models\TicketCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketCategoryResource extends Resource
{
    protected static ?string $model = TicketCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TicketCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Request Categories';
    }

    public static function getModelLabel(): string
    {
        return 'Request Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Request Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Service Desk';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    protected static function currentUserCanManageRequestCategories(): bool
    {
        return auth()->user()?->hasPermission('ticket-categories.manage') === true
            || auth()->user()?->hasPermission('master-data.manage') === true;
    }

    public static function shouldRegisterNavigation(): bool { return static::currentUserCanManageRequestCategories(); }
    public static function canViewAny(): bool { return static::currentUserCanManageRequestCategories(); }
    public static function canCreate(): bool { return static::currentUserCanManageRequestCategories(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::currentUserCanManageRequestCategories(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return static::currentUserCanManageRequestCategories(); }
    public static function canDeleteAny(): bool { return static::currentUserCanManageRequestCategories(); }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketCategories::route('/'),
            'create' => CreateTicketCategory::route('/create'),
            'edit' => EditTicketCategory::route('/{record}/edit'),
        ];
    }
}
