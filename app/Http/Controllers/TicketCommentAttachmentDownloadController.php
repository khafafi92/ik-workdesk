<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\TicketComment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketCommentAttachmentDownloadController extends Controller
{
    public function __invoke(
        TicketComment $ticketComment,
        int $attachmentIndex
    ): StreamedResponse {
        $ticketComment->loadMissing('ticket');

        abort_unless(
            $ticketComment->ticket
                && TicketResource::canView($ticketComment->ticket),
            403
        );

        $attachments = array_values(
            array_filter((array) $ticketComment->attachments)
        );
        $filePath = $attachments[$attachmentIndex] ?? null;

        abort_unless(is_string($filePath), 404);

        $disk = $this->resolveDisk($filePath);

        abort_unless($disk !== null, 404);

        return $disk->download(
            $filePath,
            basename($filePath)
        );
    }

    private function resolveDisk(string $filePath): ?FilesystemAdapter
    {
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local');
        }

        // Compatibility for attachments uploaded before private storage.
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public');
        }

        return null;
    }
}
