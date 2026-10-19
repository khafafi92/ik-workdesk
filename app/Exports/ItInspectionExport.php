<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ItInspectionExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private Builder $results) {}

    public function query(): Builder
    {
        return $this->results->with(['inspection', 'followUp.assignee', 'followUp.resolver'])->orderBy('it_inspection_id')->orderBy('id');
    }

    public function headings(): array
    {
        return ['Nomor pemeriksaan', 'Tanggal', 'Petugas', 'Kode perangkat', 'Nama perangkat', 'Jenis', 'Nomor aset', 'Serial number', 'Department pengelola', 'Lokasi', 'Kelompok', 'Pemeriksaan', 'Hasil', 'Nilai / rincian', 'Catatan', 'Temuan', 'Tindak lanjut', 'Penanggung jawab', 'Status tindak lanjut', 'Catatan penyelesaian', 'Diselesaikan oleh', 'Waktu penyelesaian'];
    }

    public function map($result): array
    {
        $inspection = $result->inspection;
        $device = $inspection->device_snapshot;
        $followUp = $result->followUp;

        return [
            $inspection->inspection_number, $inspection->checked_at->format('Y-m-d H:i'), $inspection->inspector_snapshot['name'],
            $device['code'], $device['name'], $device['device_type'], $device['asset_number'] ?? '', $device['serial_number'] ?? '',
            $device['managing_department']['name'] ?? '', $inspection->location_snapshot['location']['gps_name'] ?? $inspection->location_snapshot['detail'] ?? '',
            $result->item_snapshot['section'], $result->item_snapshot['label'], $result->condition->label(),
            $result->measured_value['value'] ?? ($result->measured_value ? json_encode($result->measured_value, JSON_UNESCAPED_UNICODE) : ''),
            $result->notes, $result->finding, $followUp?->action_plan, $followUp?->assignee?->name, $followUp?->status?->label(),
            $followUp?->resolution_notes, $followUp?->resolver?->name, $followUp?->resolved_at?->format('Y-m-d H:i'),
        ];
    }
}
