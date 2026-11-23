<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Models\AttendanceImport;
use App\Services\AttendanceReportProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Throwable;

class AttendanceReportCenter extends Page
{
    protected array $extraBodyAttributes = ['data-attendance-flow' => 'true'];

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedChartBarSquare;

    protected string $view =
        'filament.pages.attendance-report-center';

    #[Url(as: 'periode')]
    public ?int $attendanceImportId = null;

    public function mount(): void
    {
        $this->attendanceImportId ??= AttendanceImport::query()
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
        return auth()->user()?->hasPermission('attendance.report-center') === true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('attendance.report-center') === true
            || auth()->user()?->hasPermission('attendance.view') === true
            || AttendanceImportResource::canViewAny();
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public static function getNavigationLabel(): string
    {
        return 'Attendance Report';
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

    public function getTitle(): string|Htmlable
    {
        return 'Attendance Report';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Attendance Report';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Upload data, process reports, then see the location of absences and the total working hours.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')->label('Upload new data')->icon('heroicon-o-arrow-up-tray')
                ->url($this->getUploadUrl())->visible(AttendanceImportResource::canCreate()),
            Action::make('manageUploads')->label('Manage uploads')->color('gray')
                ->url(AttendanceImportResource::getUrl())->visible(AttendanceImportResource::canViewAny()),
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'processed' => 'Completed',
            'processing' => 'Processing',
            'failed' => 'Failed to process',
            'uploaded', 'draft' => 'Waiting for process',
            default => '-',
        };
    }

    public function processAction(): Action
    {
        return Action::make('process')
            ->label('Process report')
            ->visible(fn (): bool => auth()->user()?->hasPermission('attendance.manage') === true
                && in_array($this->selectedImport?->status, ['uploaded', 'draft', 'failed'], true))
            ->action(function (): void {
                abort_unless(auth()->user()?->hasPermission('attendance.manage'), 403);
                $import = AttendanceImport::find($this->attendanceImportId);
                abort_unless($import && in_array($import->status, ['uploaded', 'draft', 'failed'], true), 409);
                try {
                    app(AttendanceReportProcessor::class)->process($import);
                    $this->redirect(AttendanceImportResource::getUrl('results', ['record' => $import]));
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title('Process not yet successful')
                        ->body('Check files and information failures on the report.')->send();
                } finally {
                    unset($this->selectedImport);
                }
            });
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
            .$this->attendanceImportId
            .'/results#activity-check'
        );
    }

    public function getWorkHourUrl(): string
    {
        if (! $this->attendanceImportId) {
            return '#';
        }

        return url(
            '/panel/attendance-imports/'
            .$this->attendanceImportId
            .'/results#work-hour-summary'
        );
    }

    public function getUploadUrl(): string
    {
        return url('/panel/attendance-imports/create');
    }
}
