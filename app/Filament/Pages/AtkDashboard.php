<?php

namespace App\Filament\Pages;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AtkDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Dashboard ATK';

    protected static ?string $title = 'Dashboard ATK';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.atk-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    protected function getViewData(): array
    {
        return [
            'submitted' => AtkRequest::query()->whereIn('status', ['submitted', 'processing'])->count(),
            'partial' => AtkRequest::query()->where('status', 'partially_fulfilled')->count(),
            'lowStock' => AtkItem::query()
                ->whereNotNull('minimum_stock')
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'completedThisMonth' => AtkRequest::query()
                ->where('status', 'completed')
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];
    }
}
