<?php

namespace App\Filament\Pages;

use App\Models\AttendanceImport;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AttendanceReportCenter extends Page
{
    protected string $view =
        'filament.pages.attendance-report-center';

    public ?int $attendanceImportId = null;

    public function mount(): void
    {
        $this->attendanceImportId = AttendanceImport::query()
            ->orderByDesc('id')
            ->value('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()
            ?->hasPermission('attendance.view') === true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()
            ?->hasPermission('attendance.view') === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public static function getNavigationLabel(): string
    {
        return 'Report Center';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Page Information
    |--------------------------------------------------------------------------
    */

    public function getTitle(): string | Htmlable
    {
        return 'Attendance Report Center';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Attendance Report Center';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Pilih periode upload, lalu buka Activity Check atau Total Jam Kerja.';
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance Periods
    |--------------------------------------------------------------------------
    */

    public function getImports()
    {
        return AttendanceImport::query()
            ->select([
                'id',
                'period_name',
                'attendance_file_name',
                'work_hour_file_name',
                'status',
                'notes',
                'processed_at',
                'created_at',
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function getSelectedImportProperty(): ?AttendanceImport
    {
        if (! $this->attendanceImportId) {
            return null;
        }

        return AttendanceImport::query()
            ->select([
                'id',
                'period_name',
                'attendance_file_name',
                'work_hour_file_name',
                'status',
                'notes',
                'processed_at',
                'created_at',
            ])
            ->find($this->attendanceImportId);
    }

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */

    public function getActivityUrl(): string
    {
        if (! $this->attendanceImportId) {
            return '#';
        }

        return url(
            '/panel/attendance-imports/'
            . $this->attendanceImportId
            . '/results#activity-check'
        );
    }

    public function getWorkHourUrl(): string
    {
        if (! $this->attendanceImportId) {
            return '#';
        }

        return url(
            '/panel/attendance-imports/'
            . $this->attendanceImportId
            . '/results#work-hour-summary'
        );
    }

    public function getUploadUrl(): string
    {
        return url('/panel/attendance-imports/create');
    }
}