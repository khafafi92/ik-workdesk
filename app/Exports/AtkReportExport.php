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
            new AtkCollectionSheet('Rekap Department', [
                'Departemen', 'Barang ATK', 'Jumlah Diminta', 'Satuan',
            ], $this->departmentRequestSummaryRows()),
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

    private function departmentRequestSummaryRows(): array
    {
        return AtkRequest::query()
            ->with(['department', 'items.item'])
            ->when($this->from, fn ($query) => $query->whereDate('request_date', '>=', $this->from))
            ->when($this->until, fn ($query) => $query->whereDate('request_date', '<=', $this->until))
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->flatMap(fn (AtkRequest $request) => $request->items->map(fn ($item): array => [
                'department' => $request->department?->name ?? 'Tanpa departemen',
                'item' => $item->item?->name ?? 'Barang tidak ditemukan',
                'unit' => $item->unit,
                'quantity' => (float) $item->qty_requested,
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
