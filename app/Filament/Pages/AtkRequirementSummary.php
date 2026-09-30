<?php

namespace App\Filament\Pages;

use App\Models\AtkRequestItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AtkRequirementSummary extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?string $navigationLabel = 'Rekap Kebutuhan';

    protected static ?string $title = 'Rekap Kebutuhan ATK';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.atk-requirement-summary';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    protected function getViewData(): array
    {
        return [
            'requirements' => AtkRequestItem::query()
                ->selectRaw('permit_companies.code as company_code, atk_items.code, atk_items.name, atk_items.unit, SUM(atk_request_items.qty_requested - atk_request_items.qty_received) as outstanding_quantity')
                ->join('atk_items', 'atk_items.id', '=', 'atk_request_items.atk_item_id')
                ->join('atk_requests', 'atk_requests.id', '=', 'atk_request_items.atk_request_id')
                ->leftJoin('permit_companies', 'permit_companies.id', '=', 'atk_requests.permit_company_id')
                ->whereNotIn('atk_requests.status', ['completed', 'cancelled'])
                ->groupBy('permit_companies.code', 'atk_items.code', 'atk_items.name', 'atk_items.unit')
                ->havingRaw('SUM(atk_request_items.qty_requested - atk_request_items.qty_received) > 0')
                ->orderBy('permit_companies.code')
                ->orderBy('atk_items.name')
                ->get(),
        ];
    }
}
