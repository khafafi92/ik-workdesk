<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
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

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.atk-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    protected function getViewData(): array
    {
        return [
            'newRequests' => AtkRequest::query()->where('status', 'submitted')->count(),
            'waitingProcurement' => AtkRequestItem::query()->where('status', 'waiting_procurement')->count(),
            'readyToIssue' => AtkRequestItem::query()->where('status', 'ready')->count(),
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

    public function requestItemSummary(AtkRequest $request): string
    {
        return $request->items
            ->map(fn (AtkRequestItem $item): string => trim(implode(' ', [
                $item->item?->name,
                number_format((float) $item->qty_requested, 2, ',', '.'),
                $item->unit,
            ])))
            ->implode('; ');
    }

    public function requestStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Baru, perlu ditinjau GA',
            'processing' => 'Sedang diproses',
            'partially_fulfilled' => 'Sebagian dipenuhi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
}
