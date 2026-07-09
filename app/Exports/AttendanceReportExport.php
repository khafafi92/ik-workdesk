<?php

namespace App\Exports;

use App\Exports\Sheets\AttendanceActivitySheet;
use App\Exports\Sheets\AttendanceWorkHourSummarySheet;
use App\Models\AttendanceImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceReportExport implements WithMultipleSheets
{
    public function __construct(
        protected AttendanceImport $import
    ) {}

    public function sheets(): array
    {
        return [
            new AttendanceActivitySheet($this->import),
            new AttendanceWorkHourSummarySheet($this->import),
        ];
    }
}