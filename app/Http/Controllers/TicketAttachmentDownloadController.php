<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentDownloadController extends Controller
{
    public function __invoke(
        Ticket $ticket,
        int $attachmentIndex
    ): StreamedResponse {
        abort_unless(TicketResource::canView($ticket), 403);

        $attachments = array_values(
            array_filter((array) $ticket->attachments)
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
