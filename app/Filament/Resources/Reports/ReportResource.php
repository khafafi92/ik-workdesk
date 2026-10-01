<?php

namespace App\Filament\Resources\Reports;

use App\Exports\ReportExport;
use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class ReportResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $menuPermissionCode = null;

    public static function canViewAny(): bool
    {
        $permission = static::$menuPermissionCode;

        return $permission !== null && auth()->user()?->hasPermission($permission) === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function canExport(): bool
    {
        return auth()->user()?->hasPermission('report.export') === true;
    }

    abstract public static function export(Builder $query): ReportExport;

    public static function exportFilename(?string $from = null, ?string $until = null): string
    {
        return str(static::getSlug())->replace(['/', '-'], '_')
            .'_'.($from ?? now()->startOfMonth()->toDateString())
            .'_'.($until ?? now()->endOfMonth()->toDateString()).'.xlsx';
    }
}
