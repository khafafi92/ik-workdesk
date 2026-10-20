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

class ItDeviceSummarySheet extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    public function __construct(private Builder $devices, private Builder $inspections, private Builder $findings)
    {
        $this->setNumericConversion(false);
    }

    public function title(): string
    {
        return 'Ringkasan Perangkat';
    }

    public function query(): Builder
    {
        $deviceIds = (clone $this->devices)->reorder()->select('it_devices.id');
        $findingIds = (clone $this->findings)->reorder()->select('it_inspection_follow_ups.id');
        $query = (clone $this->inspections)->whereIn('it_device_id', $deviceIds)->with([
            'device.managingDepartment', 'device.computerDetail', 'device.assignedEmployee.department', 'device.assignedEmployee.user',
        ]);

        foreach (['open', 'in_progress', 'resolved'] as $status) {
            $query->withCount(['followUps as '.$status.'_findings_count' => fn ($query) => $query->where('status', $status)->whereIn('id', clone $findingIds)]);
        }

        return $query->orderBy('it_device_id')->orderBy('checked_at')->orderBy('id');
    }

    public function headings(): array
    {
        return [...ItDeviceExportFields::headings(), 'Nomor pemeriksaan', 'Tanggal pemeriksaan', 'Petugas', 'Kondisi', 'Temuan terbuka', 'Temuan dalam proses', 'Temuan selesai', 'Total temuan belum selesai'];
    }

    public function map($inspection): array
    {
        return [
            ...ItDeviceExportFields::forDevice($inspection->device),
            $inspection->referenceNumber(), $inspection->checked_at->format('Y-m-d H:i'),
            $inspection->inspector_snapshot['name'], $inspection->overall_condition->label(),
            (int) $inspection->open_findings_count, (int) $inspection->in_progress_findings_count, (int) $inspection->resolved_findings_count,
            (int) $inspection->open_findings_count + (int) $inspection->in_progress_findings_count,
        ];
    }
}
