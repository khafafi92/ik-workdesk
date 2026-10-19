<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ItMaintenanceFindingsSheet extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    public function __construct(private Builder $findings)
    {
        $this->setNumericConversion(false);
    }

    public function title(): string
    {
        return 'Temuan dan Perawatan';
    }

    public function query(): Builder
    {
        return (clone $this->findings)->with(['inspection', 'result', 'assignee', 'resolver'])->orderBy('it_inspection_id')->orderBy('id');
    }

    public function headings(): array
    {
        return [...ItDeviceExportFields::headings(), 'Nomor pemeriksaan', 'Tanggal pemeriksaan', 'Petugas pemeriksaan', 'Kelompok', 'Pemeriksaan', 'Kondisi saat diperiksa', 'Temuan', 'Rencana perawatan / tindak lanjut', 'Penanggung jawab', 'Batas waktu tindak lanjut', 'Status tindak lanjut saat ekspor', 'Catatan penyelesaian', 'Diselesaikan oleh', 'Waktu penyelesaian'];
    }

    public function map($finding): array
    {
        $inspection = $finding->inspection;

        return [
            ...ItDeviceExportFields::forInspection($inspection),
            $inspection->inspection_number, $inspection->checked_at->format('Y-m-d H:i'), $inspection->inspector_snapshot['name'],
            $finding->result->item_snapshot['section'], $finding->result->item_snapshot['label'], $finding->result->condition->label(),
            $finding->result->finding, $finding->action_plan, $finding->assignee?->name, $finding->due_at?->format('Y-m-d H:i'),
            $finding->status->label(), $finding->resolution_notes, $finding->resolver?->name, $finding->resolved_at?->format('Y-m-d H:i'),
        ];
    }
}
