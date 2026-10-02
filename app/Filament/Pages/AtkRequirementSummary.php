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
                'label' => 'Menunggu pengadaan',
                'detail' => 'Barang belum tersedia untuk diserahkan.',
                'tone' => 'procurement',
            ];
        }

        if ($awaitingIssue > 0 && $awaitingReceipt > 0) {
            return [
                'label' => 'Perlu diserahkan dan dikonfirmasi',
                'detail' => 'Sebagian barang sudah diserahkan; sisanya masih perlu disiapkan.',
                'tone' => 'mixed',
            ];
        }

        if ($awaitingReceipt > 0) {
            return [
                'label' => 'Menunggu konfirmasi penerimaan',
                'detail' => 'Barang sudah diserahkan, tetapi peminta belum mengonfirmasi penerimaan.',
                'tone' => 'receipt',
            ];
        }

        if ($item->status === 'ready') {
            return [
                'label' => 'Siap diserahkan',
                'detail' => 'Barang telah siap dan menunggu penyerahan oleh GA.',
                'tone' => 'ready',
            ];
        }

        return [
            'label' => 'Perlu diproses dan diserahkan',
            'detail' => 'Belum ada barang yang diserahkan untuk kebutuhan ini.',
            'tone' => 'issue',
        ];
    }
}
