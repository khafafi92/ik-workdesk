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

    protected static ?string $navigationLabel = 'Requirements Summary';

    protected static ?string $title = 'Office Supply Requirements Summary';

    protected static string|UnitEnum|null $navigationGroup = 'ATK';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.atk-requirement-summary';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('atk.summary') === true;
    }

    protected function getViewData(): array
    {
        $requirements = AtkRequestItem::query()
            ->with([
                'item:id,code,name,unit',
                'request:id,request_number,request_date,requester_id,department_id,permit_company_id,purpose,status',
                'request.requester:id,name',
                'request.department:id,code,name',
                'request.company:id,code',
            ])
            ->whereHas('request', fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']))
            ->whereColumn('qty_received', '<', 'qty_requested')
            ->orderByDesc('atk_request_id')
            ->orderBy('id')
            ->get();

        $totalOutstanding = $requirements->sum(fn (AtkRequestItem $item): float => $this->outstandingQuantity($item));
        $totalAwaitingIssue = $requirements->sum(fn (AtkRequestItem $item): float => $this->awaitingIssueQuantity($item));
        $totalAwaitingReceipt = $requirements->sum(fn (AtkRequestItem $item): float => $this->awaitingReceiptQuantity($item));

        return [
            'requirements' => $requirements,
            'requirementCount' => $requirements->count(),
            'requestCount' => $requirements->pluck('atk_request_id')->unique()->count(),
            'totalOutstanding' => $totalOutstanding,
            'totalAwaitingIssue' => $totalAwaitingIssue,
            'totalAwaitingReceipt' => $totalAwaitingReceipt,
        ];
    }

    public function outstandingQuantity(AtkRequestItem $item): float
    {
        return max(0, (float) $item->qty_requested - (float) $item->qty_received);
    }

    public function awaitingIssueQuantity(AtkRequestItem $item): float
    {
        return $item->outstandingRequested();
    }

    public function awaitingReceiptQuantity(AtkRequestItem $item): float
    {
        return $item->awaitingReceipt();
    }

    /** @return array{label: string, detail: string, tone: string} */
    public function nextAction(AtkRequestItem $item): array
    {
        $awaitingIssue = $this->awaitingIssueQuantity($item);
        $awaitingReceipt = $this->awaitingReceiptQuantity($item);

        if ($item->status === 'waiting_procurement') {
            return [
                'label' => 'Awaiting procurement',
                'detail' => 'The items are not ready to issue.',
                'tone' => 'procurement',
            ];
        }

        if ($awaitingIssue > 0 && $awaitingReceipt > 0) {
            return [
                'label' => 'Awaiting issue and receipt confirmation',
                'detail' => 'Some items were issued. Prepare the remaining items.',
                'tone' => 'mixed',
            ];
        }

        if ($awaitingReceipt > 0) {
            return [
                'label' => 'Awaiting receipt confirmation',
                'detail' => 'The items were issued. The requester still needs to confirm receipt.',
                'tone' => 'receipt',
            ];
        }

        if ($item->status === 'ready') {
            return [
                'label' => 'Ready to hand over',
                'detail' => 'The items are ready for GA to issue.',
                'tone' => 'ready',
            ];
        }

        return [
            'label' => 'Awaiting issue',
            'detail' => 'No items have been issued for this request.',
            'tone' => 'issue',
        ];
    }
}
