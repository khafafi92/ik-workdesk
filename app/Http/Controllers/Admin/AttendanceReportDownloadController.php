<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportDownloadController extends Controller
{
    public function download(
        Request $request,
        AttendanceImport $attendanceImport
    ) {
        abort_unless(
            $request->user()?->hasPermission('attendance.view') === true,
            403
        );

        $periodName = str($attendanceImport->period_name)
            ->replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', ' '], '-')
            ->lower();

        $fileName = 'attendance-report-'.$periodName.'.xlsx';

        return Excel::download(
            new AttendanceReportExport($attendanceImport),
            $fileName
        );
    }
}
