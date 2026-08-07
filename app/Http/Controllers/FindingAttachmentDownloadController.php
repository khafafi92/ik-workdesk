<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\WorkTaskFinding;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FindingAttachmentDownloadController extends Controller
{
    public function __invoke(
        WorkTaskFinding $workTaskFinding,
        string $attachmentType,
        int $attachmentIndex
    ): StreamedResponse {
        $workTaskFinding->loadMissing('workTask.ticket');
        $workTask = $workTaskFinding->workTask;
        $canView = $workTask?->ticket
            ? TicketResource::canView($workTask->ticket)
            : ($workTask && WorkTaskResource::canView($workTask));

        abort_unless($canView, 403);

        $field = $attachmentType === 'response'
            ? 'response_attachments'
            : 'attachments';
        $attachments = array_values(
            array_filter((array) $workTaskFinding->{$field})
        );
        $filePath = $attachments[$attachmentIndex] ?? null;

        abort_unless(is_string($filePath), 404);

        $disk = $this->resolveDisk($filePath);

        abort_unless($disk !== null, 404);

        return $disk->download($filePath, basename($filePath));
    }

    private function resolveDisk(string $filePath): ?FilesystemAdapter
    {
        foreach (['local', 'public'] as $diskName) {
            if (Storage::disk($diskName)->exists($filePath)) {
                return Storage::disk($diskName);
            }
        }

        return null;
    }
}
