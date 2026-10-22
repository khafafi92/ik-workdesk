<?php

namespace App\Http\Controllers;

use App\Models\Scm\Attachment;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Services\Scm\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScmDocumentController extends Controller
{
    public function download(Request $http, Attachment $attachment)
    {
        $actor = $http->user();
        if ($attachment->request_id) {
            abort_unless(Access::views($actor, Procurement::findOrFail($attachment->request_id)), 403);
        } elseif ($attachment->quotation_id) {
            $quote = Quotation::with('candidate')->findOrFail($attachment->quotation_id);
            abort_unless(Access::manages($actor, Procurement::findOrFail($quote->candidate->request_id)), 403);
        } elseif ($attachment->cbe_id) {
            $cbe = Cbe::findOrFail($attachment->cbe_id);
            abort_unless(Access::manages($actor, Procurement::findOrFail($cbe->request_id)), 403);
        } else {
            abort_unless($attachment->vendor_document_id && Access::manages($actor), 403);
        }
        abort_unless(preg_match('/^\d{4}\/\d{2}\/[a-f0-9-]{36}\.(pdf|jpg|jpeg|png)$/D', $attachment->path), 404);
        abort_unless(Storage::disk('scm')->exists($attachment->path), 404);

        return Storage::disk('scm')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function print(Request $http, Cbe $cbe)
    {
        abort_unless(Access::manages($http->user(), Procurement::findOrFail($cbe->request_id)), 403);
        abort_unless($cbe->status === 'final', 404);

        return response()->view('scm.cbe-print', ['cbe' => $cbe])->header('Cache-Control', 'private, no-store');
    }
}
