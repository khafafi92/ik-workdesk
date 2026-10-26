<?php

namespace App\Http\Controllers;

use App\Models\Scm\Attachment;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\Scm\RequestApproval;
use App\Models\Scm\UserSignature;
use App\Services\Scm\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScmDocumentController extends Controller
{
    public function profileSignature(Request $http, UserSignature $signature)
    {
        abort_unless(Access::signs($http->user()) && (int) $signature->user_id === (int) $http->user()->id, 403);

        return $this->signatureImage($signature);
    }

    public function preparedSignature(Request $http, Procurement $procurement)
    {
        abort_unless(Access::viewsRequest($http->user(), $procurement), 403);
        abort_unless($procurement->prepared_signed_at && $procurement->preparedSignature, 404);

        return $this->signatureImage($procurement->preparedSignature);
    }

    public function approvalSignature(Request $http, RequestApproval $approval)
    {
        abort_unless(Access::viewsRequest($http->user(), $approval->request), 403);
        abort_unless($approval->status === 'signed' && $approval->signature, 404);

        return $this->signatureImage($approval->signature);
    }

    private function signatureImage(UserSignature $signature)
    {
        abort_unless(preg_match('/^signatures\/[a-f0-9-]{36}\.(png|jpg)$/D', $signature->path), 404);
        abort_unless(Storage::disk('scm')->exists($signature->path), 404);

        return Storage::disk('scm')->response($signature->path, 'tanda-tangan', [
            'Content-Type' => $signature->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $http, Attachment $attachment)
    {
        $actor = $http->user();
        if ($attachment->request_id) {
            abort_unless(Access::viewsRequest($actor, Procurement::findOrFail($attachment->request_id)), 403);
        } elseif ($attachment->quotation_id) {
            $quote = Quotation::with('candidate')->findOrFail($attachment->quotation_id);
            $request = Procurement::with('cbe')->findOrFail($quote->candidate->request_id);
            $selected = $request->cbe?->status === 'final'
                && (int) $request->cbe->selected_vendor_id === (int) $quote->candidate->vendor_id
                && in_array((int) $quote->id, array_map('intval', $request->cbe->quotation_ids), true);
            abort_unless(Access::manages($actor, $request) || ($selected && Access::views($actor, $request)), 403);
        } elseif ($attachment->cbe_id) {
            $cbe = Cbe::findOrFail($attachment->cbe_id);
            $request = Procurement::findOrFail($cbe->request_id);
            abort_unless(Access::manages($actor, $request) || ($cbe->status === 'final' && Access::views($actor, $request)), 403);
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
        $request = Procurement::findOrFail($cbe->request_id);
        abort_unless(Access::manages($http->user(), $request) || ($cbe->status === 'final' && Access::views($http->user(), $request)), 403);
        abort_unless($cbe->status === 'final', 404);

        return response()->view('scm.cbe-print', ['cbe' => $cbe])->header('Cache-Control', 'private, no-store');
    }

    public function selectedQuotation(Request $http, Cbe $cbe)
    {
        $request = Procurement::findOrFail($cbe->request_id);
        abort_unless(Access::manages($http->user(), $request) || ($cbe->status === 'final' && Access::views($http->user(), $request)), 403);
        abort_unless($cbe->status === 'final', 404);
        $quote = collect($cbe->snapshot['quotes'])->firstWhere('vendor.id', $cbe->selected_vendor_id);
        abort_unless($quote, 404);

        return response()->view('scm.selected-quotation-print', ['cbe' => $cbe, 'quote' => $quote])->header('Cache-Control', 'private, no-store');
    }
}
