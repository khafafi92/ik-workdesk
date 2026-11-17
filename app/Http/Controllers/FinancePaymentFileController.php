<?php

namespace App\Http\Controllers;

use App\Models\FinancePoPaymentFile;
use App\Services\Finance\PoPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinancePaymentFileController extends Controller
{
    public function __invoke(Request $request, FinancePoPaymentFile $file, PoPayments $payments)
    {
        $payments->order($request->user(), $file->payment->purchase_order_id);
        abort_unless(preg_match('~^finance/\d{4}/\d{2}/[a-f0-9-]{36}\.(pdf|jpg|jpeg|png)$~D', $file->path), 404);
        abort_unless(Storage::disk('scm')->exists($file->path), 404);

        return Storage::disk('scm')->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
