<?php

namespace App\Exports;

use App\Exports\Sheets\AtkCollectionSheet;
use App\Models\AtkDepartmentBalance;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkUsageTransaction;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AtkReportExport implements WithMultipleSheets
{
    public function __construct(
        protected ?CarbonInterface $from = null,
        protected ?CarbonInterface $until = null,
    ) {}

    public function sheets(): array
    {
        return [
            new AtkCollectionSheet('Rekap Bulanan', [
                'Bulan Permintaan', 'Departemen', 'Entitas', 'Kode Barang', 'Barang ATK', 'Ukuran', 'Satuan',
                'Jumlah Permintaan', 'Jumlah Diminta', 'Jumlah Diserahkan', 'Jumlah Diterima',
            ], $this->monthlyRequestSummaryRows()),
            new AtkCollectionSheet('Rekap Department', [
                'Departemen', 'Barang ATK', 'Jumlah Diterima', 'Satuan',
            ], $this->departmentReceiptSummaryRows()),
            new AtkCollectionSheet('Permintaan', [
                'Nomor Permintaan', 'Tanggal', 'Entitas', 'Peminta', 'Departemen', 'Keperluan', 'Status', 'Item', 'Jumlah Diminta', 'Jumlah Diserahkan', 'Jumlah Diterima', 'Satuan',
            ], $this->requestRows()),
            new AtkCollectionSheet('Stok Gudang Utama', [
                'Kode', 'Barang ATK', 'Kategori', 'Stok Gudang Utama', 'Stok Minimum', 'Satuan', 'Status',
            ], $this->warehouseRows()),
            new AtkCollectionSheet('Stok Departemen', [
                'Departemen', 'Kode', 'Barang ATK', 'Saldo', 'Satuan', 'Penerimaan Terakhir', 'Pemakaian Terakhir',
            ], $this->departmentRows()),
            new AtkCollectionSheet('Pemakaian', [
                'Tanggal', 'Departemen', 'Kode', 'Barang ATK', 'Jumlah', 'Satuan', 'Keperluan', 'Dicatat Oleh',
            ], $this->usageRows()),
        ];
    }

    private function monthlyRequestSummaryRows(): array
    {
        return AtkRequest::query()
            ->with(['department', 'company', 'items.item'])
            ->when($this->from, fn ($query) => $query->whereDate('request_date', '>=', $this->from))
            ->when($this->until, fn ($query) => $query->whereDate('request_date', '<=', $this->until))
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('request_date')
            ->get()
            ->flatMap(fn (AtkRequest $request) => $request->items->map(fn ($item): array => [
                'month' => $request->request_date?->format('Y-m'),
                'department_id' => $request->department_id,
                'department' => $request->department?->name ?? 'Tanpa departemen',
                'company_id' => $request->permit_company_id,
                'company' => $request->company?->code ?? 'Belum ditetapkan',
                'item_id' => $item->atk_item_id,
                'code' => $item->item?->code ?? '',
                'item' => $item->item?->name ?? 'Barang tidak ditemukan',
                'size' => $item->item?->size ?? '',
                'unit' => $item->unit,
                'request_id' => $request->id,
                'requested' => (float) $item->qty_requested,
                'issued' => (float) $item->qty_issued,
                'received' => (float) $item->qty_received,
            ]))
            ->groupBy(fn (array $row): string => serialize([
                $row['month'] ?? '',
                $row['department_id'] ?? '',
                $row['company_id'] ?? '',
                $row['item_id'] ?? '',
                $row['unit'],
            ]))
            ->map(fn ($rows): array => [
                $rows->first()['month'],
                $this->safe($rows->first()['department']),
                $this->safe($rows->first()['company']),
                $this->safe($rows->first()['code']),
                $this->safe($rows->first()['item']),
                $this->safe($rows->first()['size']),
                $rows->first()['unit'],
                $rows->pluck('request_id')->unique()->count(),
                $rows->sum('requested'),
                $rows->sum('issued'),
                $rows->sum('received'),
            ])
            ->sortBy(fn (array $row): string => implode('|', [$row[0], $row[1], $row[4], $row[6]]))
            ->values()
            ->all();
    }

    private function requestRows(): array
    {
        return AtkRequest::query()
            ->with(['requester', 'department', 'company', 'items.item'])
            ->when($this->from, fn ($query) => $query->whereDate('request_date', '>=', $this->from))
            ->when($this->until, fn ($query) => $query->whereDate('request_date', '<=', $this->until))
            ->orderByDesc('request_date')
            ->get()
            ->flatMap(fn (AtkRequest $request) => $request->items->map(fn ($item): array => [
                $request->request_number,
                $request->request_date?->format('Y-m-d'),
                $request->company?->code ?? 'Belum ditetapkan',
                $request->requester?->name,
                $request->department?->name,
                $this->safe($request->purpose),
                str($request->status)->replace('_', ' ')->title()->toString(),
                $item->item?->name,
                (float) $item->qty_requested,
                (float) $item->qty_issued,
                (float) $item->qty_received,
                $item->unit,
            ]))->all();
    }

    private function departmentReceiptSummaryRows(): array
    {
        return AtkRequest::query()
            ->with(['department', 'items.item'])
            ->when($this->from, fn ($query) => $query->whereDate('request_date', '>=', $this->from))
            ->when($this->until, fn ($query) => $query->whereDate('request_date', '<=', $this->until))
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->flatMap(fn (AtkRequest $request) => $request->items
                ->filter(fn ($item): bool => (float) $item->qty_received > 0)
                ->map(fn ($item): array => [
                    'department' => $request->department?->name ?? 'Tanpa departemen',
                    'item' => $item->item?->name ?? 'Barang tidak ditemukan',
                    'unit' => $item->unit,
                    'quantity' => (float) $item->qty_received,
                ]))
            ->groupBy(fn (array $row): string => "{$row['department']}|{$row['item']}|{$row['unit']}")
            ->map(fn ($rows): array => [
                $rows->first()['department'],
                $rows->first()['item'],
                $rows->sum('quantity'),
                $rows->first()['unit'],
            ])
            ->sortBy(fn (array $row): string => "{$row[0]}|{$row[1]}")
            ->values()
            ->all();
    }

    private function warehouseRows(): array
    {
        return AtkItem::query()->orderBy('name')->get()->map(fn (AtkItem $item): array => [
            $item->code,
            $this->safe($item->name),
            $this->safe($item->category),
            (float) $item->current_stock,
            $item->minimum_stock === null ? null : (float) $item->minimum_stock,
            $item->unit,
            $item->is_active ? 'Aktif' : 'Tidak aktif',
        ])->all();
    }

    private function departmentRows(): array
    {
        return AtkDepartmentBalance::query()->with(['department', 'item'])->orderBy('department_id')->get()->map(fn (AtkDepartmentBalance $balance): array => [
            $balance->department?->name,
            $balance->item?->code,
            $balance->item?->name,
            (float) $balance->qty_available,
            $balance->item?->unit,
            $balance->last_received_at?->format('Y-m-d H:i'),
            $balance->last_used_at?->format('Y-m-d H:i'),
        ])->all();
    }

    private function usageRows(): array
    {
        return AtkUsageTransaction::query()
            ->with(['department', 'item', 'user'])
            ->when($this->from, fn ($query) => $query->whereDate('usage_date', '>=', $this->from))
            ->when($this->until, fn ($query) => $query->whereDate('usage_date', '<=', $this->until))
            ->orderByDesc('usage_date')
            ->get()
            ->map(fn (AtkUsageTransaction $usage): array => [
                $usage->usage_date?->format('Y-m-d'),
                $usage->department?->name,
                $usage->item?->code,
                $usage->item?->name,
                (float) $usage->qty_used,
                $usage->item?->unit,
                $this->safe($usage->purpose),
                $usage->user?->name,
            ])->all();
    }

    private function safe(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
