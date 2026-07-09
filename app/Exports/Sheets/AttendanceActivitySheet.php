<?php

namespace App\Exports\Sheets;

use App\Models\AttendanceImport;
use App\Models\AttendanceResult;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class AttendanceActivitySheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected AttendanceImport $import
    ) {}

    public function title(): string
    {
        return 'Activity Check';
    }

    public function collection()
    {
        return AttendanceResult::query()
            ->where('attendance_import_id', $this->import->id)
            ->orderBy('attendance_date')
            ->orderBy('employee_code')
            ->orderBy('check_time')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Check Time',
            'Type',
            'Employee ID',
            'Full Name',
            'Job Position',
            'Shift Name',
            'Location Setting Name',
            'Location GPS Name',
            'Location Address',
            'Location Coordinate',
            'Description',
            'Mobile Flag',
            'Status',
            'cek lokasi',
            'cek waktu',
            'Cek Pulang',
            // 'Matched Location',
            // 'Distance Meters',
        ];
    }

    public function map($row): array
    {
        return [
            $row->attendance_date?->format('Y-m-d'),
            $row->check_time,
            $row->check_type,
            (string) $row->employee_code,
            $row->employee_name,
            $row->job_position,
            $row->shift_name,
            $row->location_setting_name,
            $row->location_gps_name,
            $row->location_address,
            $row->location_coordinate,
            $row->description,
            $row->mobile_flag,
            $row->approval_status,
            $row->location_check,
            $row->duration_text,
            $row->checkout_check,
            // $row->matched_location_name,
            // $row->distance_meters,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
        ];
    }
}