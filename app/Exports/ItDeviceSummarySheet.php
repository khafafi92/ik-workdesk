<?php

namespace App\Exports;

use App\Enums\ItInspectionCondition;
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
        $inspectionIds = (clone $this->inspections)->select('it_inspections.id');
        $query = (clone $this->devices)->with([
            'managingDepartment', 'computerDetail', 'assignedEmployee.department', 'assignedEmployee.user',
            'inspections' => fn ($query) => $query->whereIn('id', clone $inspectionIds)->orderByDesc('checked_at')->orderByDesc('id')->limit(1),
        ])->withCount(['inspections as final_inspections_count' => fn ($query) => $query->whereIn('id', clone $inspectionIds)]);

        foreach (['open', 'in_progress', 'resolved'] as $status) {
            $query->addSelect([$status.'_findings_count' => (clone $this->findings)
                ->where('status', $status)
                ->whereHas('inspection', fn ($query) => $query->whereColumn('it_inspections.it_device_id', 'it_devices.id'))
                ->selectRaw('count(*)')]);
        }

        return $query;
    }

    public function headings(): array
    {
        return [...ItDeviceExportFields::headings(), 'Jumlah pemeriksaan final dalam filter', 'Nomor pemeriksaan terakhir dalam filter', 'Tanggal pemeriksaan terakhir dalam filter', 'Petugas pemeriksaan terakhir', 'Kondisi terakhir dalam filter', 'Temuan terbuka', 'Temuan dalam proses', 'Temuan selesai', 'Total temuan belum selesai'];
    }

    public function map($device): array
    {
        $last = $device->inspections->first();

        return [
            ...ItDeviceExportFields::forDevice($device),
            (int) $device->final_inspections_count, $last?->inspection_number, $last?->checked_at?->format('Y-m-d H:i'),
            $last?->inspector_snapshot['name'] ?? '', $last?->overall_condition?->label() ?? ItInspectionCondition::Unchecked->label(),
            (int) $device->open_findings_count, (int) $device->in_progress_findings_count, (int) $device->resolved_findings_count,
            (int) $device->open_findings_count + (int) $device->in_progress_findings_count,
        ];
    }
}
