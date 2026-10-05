<?php

namespace App\Http\Controllers;

use App\Models\GlobalChatAttachment;
use App\Models\GlobalChatMessage;
use App\Services\GlobalChatAccessService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlobalChatAttachmentDownloadController extends Controller
{
    public function __invoke(
        GlobalChatMessage $message,
        GlobalChatAttachment $attachment,
        GlobalChatAccessService $access,
    ): StreamedResponse|Response {
        abort_unless(
            (int) $attachment->global_chat_message_id === (int) $message->getKey(),
            404
        );

        $user = auth()->user();
        abort_unless(
            $user !== null
                && $access->canAccessCompany($user, (int) $message->permit_company_id),
            404
        );

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
