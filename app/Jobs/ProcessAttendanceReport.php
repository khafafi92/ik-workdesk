<?php

namespace App\Jobs;

use App\Models\AttendanceImport;
use App\Services\AttendanceReportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAttendanceReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $attendanceImportId
    ) {
        $this->onQueue('attendance');
    }

    public function handle(
        AttendanceReportProcessor $processor
    ): void {
        $import = AttendanceImport::query()
            ->findOrFail($this->attendanceImportId);

        $import->update([
            'status' => 'processing',
            'processed_at' => null,
            'notes' => 'Processing started at '
                . now()->format('d M Y H:i:s'),
        ]);

        $processor->process($import->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        AttendanceImport::query()
            ->whereKey($this->attendanceImportId)
            ->update([
                'status' => 'failed',
                'processed_at' => null,
                'notes' => 'Processing failed: '
                    . (
                        $exception?->getMessage()
                        ?? 'Unknown error'
                    ),
            ]);
    }
}