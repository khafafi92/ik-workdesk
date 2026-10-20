<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ItInspectionExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison
{
    public function __construct(private Builder $results)
    {
        $this->setNumericConversion(false);
    }

    public function query(): Builder
    {
        return $this->results->with(['inspection', 'followUp.assignee', 'followUp.resolver'])->orderBy('it_inspection_id')->orderBy('id');
    }

    public function headings(): array
    {
        return [...ItDeviceExportFields::headings(), 'Nomor pemeriksaan', 'Tanggal pemeriksaan', 'Petugas', 'Kelompok', 'Pemeriksaan', 'Hasil', 'Nilai / rincian', 'Catatan', 'Temuan', 'Tindak lanjut', 'Penanggung jawab', 'Status tindak lanjut', 'Catatan penyelesaian', 'Diselesaikan oleh', 'Waktu penyelesaian'];
    }

    public function map($result): array
    {
        $inspection = $result->inspection;
        $followUp = $result->followUp;

        return [
            ...ItDeviceExportFields::forInspection($inspection),
            $inspection->referenceNumber(), $inspection->checked_at->format('Y-m-d H:i'), $inspection->inspector_snapshot['name'],
            $result->item_snapshot['section'], $result->item_snapshot['label'], $result->condition->label(),
            $result->measured_value['value'] ?? ($result->measured_value ? json_encode($result->measured_value, JSON_UNESCAPED_UNICODE) : ''),
            $result->notes, $result->finding, $followUp?->action_plan, $followUp?->assignee?->name, $followUp?->status?->label(),
            $followUp?->resolution_notes, $followUp?->resolver?->name, $followUp?->resolved_at?->format('Y-m-d H:i'),
        ];
    }
}
