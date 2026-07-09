<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceImport;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportDownloadController extends Controller
{
    public function download(AttendanceImport $attendanceImport)
    {
        $periodName = str($attendanceImport->period_name)
            ->replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', ' '], '-')
            ->lower();

        $fileName = 'attendance-report-' . $periodName . '.xlsx';

        return Excel::download(
            new AttendanceReportExport($attendanceImport),
            $fileName
        );
    }
}