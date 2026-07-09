<?php

namespace App\Exports\Sheets;

use App\Models\AttendanceImport;
use App\Models\WorkHourRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AttendanceWorkHourSummarySheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected AttendanceImport $import
    ) {}

    public function title(): string
    {
        return 'Work Hour Summary';
    }

    public function collection()
    {
        return WorkHourRecord::query()
            ->where('attendance_import_id', $this->import->id)
            ->whereNull('work_date')
            ->orderBy('employee_code')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Full Name',
            $this->import->period_name,
        ];
    }

    public function map($row): array
    {
        return [
            (string) $row->employee_code,
            $row->employee_name,
            $row->work_hours_text,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }
}