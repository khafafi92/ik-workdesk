<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

trait AdminOnlyResource
{
    protected static function currentUserCanManageMasterData(): bool
    {
        return auth()->user()
            ?->hasPermission('master-data.manage') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canView(Model $record): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canCreate(): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentUserCanManageMasterData();
    }

    public static function canDeleteAny(): bool
    {
        return static::currentUserCanManageMasterData();
    }
}
