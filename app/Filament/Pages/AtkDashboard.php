<?php

namespace App\Filament\Pages;

use App\Filament\LocalizedPage as Page;
use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AtkDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Office Supplies Dashboard';

    protected static ?string $title = 'Office Supplies Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.atk-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.dashboard') === true;
    }

    protected function getViewData(): array
    {
        return [
            'newRequests' => AtkRequest::query()->where('status', 'submitted')->count(),
            'awaitingReceipt' => AtkRequestItem::query()->where('status', 'issued')
                ->whereColumn('qty_received', '<', 'qty_issued')
                ->count(),
            'lowStock' => AtkItem::query()
                ->whereNotNull('minimum_stock')
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'pendingRequests' => AtkRequest::query()
                ->with(['requester', 'department', 'company', 'items.item'])
                ->whereIn('status', ['submitted', 'processing', 'partially_fulfilled'])
                ->latest('submitted_at')
                ->limit(10)
                ->get(),
            'pendingRequestCount' => AtkRequest::query()
                ->whereIn('status', ['submitted', 'processing', 'partially_fulfilled'])
                ->count(),
            'requestsUrl' => AtkRequestResource::canViewAny()
                ? AtkRequestResource::getUrl('index')
                : null,
        ];
    }

    public function requestStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'New, needs GA review',
            'processing' => 'Processing',
            'partially_fulfilled' => 'Partially fulfilled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
}
