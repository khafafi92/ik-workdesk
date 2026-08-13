<?php

namespace App\Filament\Resources\PermitCompanies;

use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Filament\Resources\PermitCompanies\Pages\CreatePermitCompany;
use App\Filament\Resources\PermitCompanies\Pages\EditPermitCompany;
use App\Filament\Resources\PermitCompanies\Pages\ListPermitCompanies;
use App\Filament\Resources\PermitCompanies\RelationManagers\KblisRelationManager;
use App\Filament\Resources\PermitCompanies\Schemas\PermitCompanyForm;
use App\Filament\Resources\PermitCompanies\Tables\PermitCompaniesTable;
use App\Models\PermitCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermitCompanyResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = PermitCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PermitCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermitCompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [KblisRelationManager::class];
    }

    public static function getNavigationLabel(): string
    {
        return 'Permit & KBLI';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermitCompanies::route('/'),
            'create' => CreatePermitCompany::route('/create'),
            'edit' => EditPermitCompany::route('/{record}/edit'),
        ];
    }
}
