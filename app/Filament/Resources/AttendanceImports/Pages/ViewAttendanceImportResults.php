<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Pages\AttendanceReportCenter;
use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Models\AttendanceImport;
use App\Models\AttendanceResult;
use App\Models\WorkHourRecord;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

class ViewAttendanceImportResults extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = AttendanceImportResource::class;

    protected string $view = 'filament.resources.attendance-imports.pages.view-attendance-import-results';

    public ?string $search = null;

    public string $locationCheck = '';

    public string $checkoutCheck = '';

    public int $resultsPerPage = 25;

    public int $workHourPerPage = 25;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasPermission('attendance.view') === true
            || AttendanceImportResource::canViewAny();
    }

    public static function authorizeResourceAccess(): void
    {
        // Results are also available to readers without access to the upload resource.
        // Filament calls this guard on both mount and subsequent Livewire requests.
        abort_unless(static::canAccess(), 403);
    }

    public function getBreadcrumbs(): array
    {
        if (auth()->user()?->hasPermission('attendance.view')) {
            return [
                AttendanceReportCenter::getUrl() => 'Report Center',
                $this->getBreadcrumb(),
            ];
        }

        return parent::getBreadcrumbs();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Attendance Results';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Attendance Results';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->getImport()->period_name;
    }

    public function getImport(): AttendanceImport
    {
        /** @var AttendanceImport $record */
        $record = $this->getRecord();

        return $record;
    }

    public function applyFilters(): void
    {
        $this->resetResultPages();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'locationCheck',
            'checkoutCheck',
        ]);

        $this->resetResultPages();
    }

    public function updatedLocationCheck(): void
    {
        $this->resetPage('resultsPage');
    }

    public function updatedCheckoutCheck(): void
    {
        $this->resetPage('resultsPage');
    }

    public function updatedResultsPerPage(): void
    {
        $this->resetPage('resultsPage');
    }

    public function updatedWorkHourPerPage(): void
    {
        $this->resetPage('workSummaryPage');
    }

    public function getResults(): LengthAwarePaginator
    {
        return $this->resultsQuery()
            ->orderByRaw(
                'CASE WHEN attendance_date IS NULL THEN 1 ELSE 0 END'
            )
            ->orderBy('attendance_date')
            ->orderBy('employee_code')
            ->orderBy('check_time')
            ->paginate($this->resultsPerPage, ['*'], 'resultsPage');
    }

    public function getWorkHourSummaries(): LengthAwarePaginator
    {
        return $this->workHourSummaryQuery()
            ->orderBy('employee_code')
            ->paginate($this->workHourPerPage, ['*'], 'workSummaryPage');
    }

    public function getStats(): array
    {
        $importId = (int) $this->getImport()->getKey();

        /*
        |--------------------------------------------------------------------------
        | Semua statistik Attendance dihitung dalam satu query
        |--------------------------------------------------------------------------
        */

        $attendanceStats = AttendanceResult::query()
            ->where('attendance_import_id', $importId)
            ->selectRaw(
                'COUNT(*) FILTER (
                WHERE attendance_date IS NOT NULL
            ) AS results'
            )
            ->selectRaw(
                'COUNT(*) FILTER (
                WHERE location_check = ?
            ) AS location_ok',
                ['Sesuai']
            )
            ->selectRaw(
                'COUNT(*) FILTER (
                WHERE location_check = ?
            ) AS location_not_ok',
                ['Tidak Sesuai']
            )
            ->selectRaw(
                'COUNT(*) FILTER (
                WHERE location_check = ?
            ) AS location_unconfigured',
                ['Lokasi Belum Dikonfigurasi']
            )
            ->selectRaw(
                'COUNT(*) FILTER (
                WHERE checkout_check = ?
            ) AS checkout_late',
                ['Pulang 19:00 UP']
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Statistik Work Hour tetap satu query tersendiri
        |--------------------------------------------------------------------------
        */

        $workHourSummaries = WorkHourRecord::query()
            ->where('attendance_import_id', $importId)
            ->whereNull('work_date')
            ->count();

        return [
            'results' => (int) ($attendanceStats?->results ?? 0),

            'location_ok' => (int) (
                $attendanceStats?->location_ok ?? 0
            ),

            'location_not_ok' => (int) (
                $attendanceStats?->location_not_ok ?? 0
            ),

            'location_unconfigured' => (int) (
                $attendanceStats?->location_unconfigured ?? 0
            ),

            'checkout_late' => (int) (
                $attendanceStats?->checkout_late ?? 0
            ),

            'work_hour_summaries' => $workHourSummaries,
        ];
    }

    private function resultsQuery(): Builder
    {
        return AttendanceResult::query()
            ->select([
                'id',
                'attendance_import_id',
                'attendance_date',
                'check_time',
                'check_type',
                'employee_code',
                'employee_name',
                'job_position',
                'shift_name',
                'location_setting_name',
                'location_gps_name',
                'location_address',
                'location_coordinate',
                'description',
                'mobile_flag',
                'approval_status',
                'location_check',
                'duration_text',
                'checkout_check',
            ])
            ->where(
                'attendance_import_id',
                $this->getImport()->id
            )
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $search = '%'.trim(
                        (string) $this->search
                    ).'%';

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->whereLike(
                                    'employee_code',
                                    $search
                                )
                                ->orWhereLike(
                                    'employee_name',
                                    $search
                                )
                                ->orWhereLike(
                                    'job_position',
                                    $search
                                )
                                ->orWhereLike(
                                    'location_gps_name',
                                    $search
                                )
                                ->orWhereLike(
                                    'location_address',
                                    $search
                                )
                                ->orWhereLike(
                                    'matched_location_name',
                                    $search
                                );
                        }
                    );
                }
            )
            ->when(
                filled($this->locationCheck),
                fn (Builder $query): Builder => $query->where(
                    'location_check',
                    $this->locationCheck
                )
            )
            ->when(
                filled($this->checkoutCheck),
                fn (Builder $query): Builder => $query->where(
                    'checkout_check',
                    $this->checkoutCheck
                )
            );
    }

    private function workHourSummaryQuery(): Builder
    {
        return WorkHourRecord::query()
            ->select([
                'id',
                'attendance_import_id',
                'employee_code',
                'employee_name',
                'work_date',
                'work_hours_text',
                'raw_data',
            ])
            ->where(
                'attendance_import_id',
                $this->getImport()->id
            )
            ->whereNull('work_date')
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $search = '%'.trim(
                        (string) $this->search
                    ).'%';

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->whereLike(
                                    'employee_code',
                                    $search
                                )
                                ->orWhereLike(
                                    'employee_name',
                                    $search
                                );
                        }
                    );
                }
            );
    }

    private function resetResultPages(): void
    {
        $this->resetPage('resultsPage');
        $this->resetPage('workSummaryPage');
    }
}
