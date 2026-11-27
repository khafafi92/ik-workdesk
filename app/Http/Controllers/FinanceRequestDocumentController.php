<?php

namespace App\Http\Controllers;

use App\Models\FinanceRequestFile;
use App\Services\Finance\ApcaForms;
use App\Services\Finance\KpmogForms;
use App\Services\Finance\Requests;
use App\Services\ModuleAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinanceRequestDocumentController extends Controller
{
    public function print(Request $request, int $id, Requests $requests)
    {
        $this->ensureEnabled();
        $record = $requests->visible($request->user())->with(['approvals', 'travelDocument', 'paymentVoucher', 'procurement'])->findOrFail($id);

        $master = $record->kind === 'expense_report' || ($record->requiresInvoice() && array_key_exists('paid_to', $record->data));

        return response()->view($master ? 'finance.kpmog-print' : 'finance.request-print', ['record' => $record])
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, FinanceRequestFile $file, Requests $requests)
    {
        $this->ensureEnabled();
        $requests->visible($request->user())->findOrFail($file->finance_request_id);
        $disk = Storage::disk('scm');
        abort_unless(preg_match('~^finance-requests/[a-f0-9-]{36}\.(pdf|jpg|jpeg|png)$~D', $file->path) && $disk->exists($file->path), 404);

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function spreadsheet(Request $request, int $id, Requests $requests)
    {
        $this->ensureEnabled();
        $record = $requests->visible($request->user())->with(['approvals', 'paymentVoucher', 'files', 'procurement'])->findOrFail($id);
        abort_unless(ApcaForms::supports($record) || KpmogForms::supports($record), 404);
        $forms = ApcaForms::supports($record) ? app(ApcaForms::class) : app(KpmogForms::class);
        $name = Str::slug($record->reference()) ?: 'form-'.$record->id;

        return response()->streamDownload(function () use ($forms, $record): void {
            $book = $forms->workbook($record);
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(app(ModuleAccessService::class)->isEnabled('Finance'), 403);
    }
}
