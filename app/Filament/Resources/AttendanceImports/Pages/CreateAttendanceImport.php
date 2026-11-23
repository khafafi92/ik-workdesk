<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Pages\AttendanceReportCenter;
use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Services\AttendanceReportProcessor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class CreateAttendanceImport extends CreateRecord
{
    protected static string $resource = AttendanceImportResource::class;

    protected static bool $canCreateAnother = false;

    protected array $extraBodyAttributes = ['data-attendance-flow' => 'true'];

    public function getTitle(): string|Htmlable
    {
        return 'Upload Attendance';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Select two Excel files. Period will be automatically determined from date on Total Clock File.';
    }

    public function getBreadcrumbs(): array
    {
        return [AttendanceReportCenter::getUrl() => 'Attendance Report', 'Upload'];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(
            auth()->user()?->hasPermission('attendance.manage') ? 'Upload and process' : 'Upload data'
        );
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')->label('Back')->color('gray')->url(AttendanceReportCenter::getUrl());
    }

    protected function afterCreate(): void
    {
        if (! auth()->user()?->hasPermission('attendance.manage')) {
            return;
        }

        try {
            app(AttendanceReportProcessor::class)->process($this->record);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->record->refresh();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return match ($this->record->status) {
            'processed' => Notification::make()->success()->title('Report completed'),
            'failed' => Notification::make()->danger()->title('File saved, process not successfully')
                ->body('Check the description on the report, then fix the file and reprocess.'),
            default => Notification::make()->success()->title('File successfully uploaded')
                ->body('Waiting for the user with an attendance process permit.'),
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->record->status === 'processed'
            ? AttendanceImportResource::getUrl('results', ['record' => $this->record])
            : AttendanceReportCenter::getUrl(['periode' => $this->record->id]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by_user_id'] = auth()->id();
        $data['period_name'] = 'Waiting for process';
        $data['status'] = 'uploaded';
        $data['attendance_file_path'] = $this->normalizeFilePath($data['attendance_file_path'] ?? null);
        $data['work_hour_file_path'] = $this->normalizeFilePath($data['work_hour_file_path'] ?? null);

        if (
            ! empty($data['attendance_file_path'])
            && empty($data['attendance_file_name'])
        ) {
            $data['attendance_file_name'] = basename($data['attendance_file_path']);
        }

        if (
            ! empty($data['work_hour_file_path'])
            && empty($data['work_hour_file_name'])
        ) {
            $data['work_hour_file_name'] = basename($data['work_hour_file_path']);
        }

        return $data;
    }

    private function normalizeFilePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return filled($path) ? (string) $path : null;
    }
}
