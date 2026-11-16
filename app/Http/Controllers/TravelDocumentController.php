<?php

namespace App\Http\Controllers;

use App\Models\TravelFile;
use App\Services\Travel\TravelAmounts;
use App\Services\Travel\TravelDocumentAdministration;
use App\Services\Travel\TravelWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TravelDocumentController extends Controller
{
    public function print(Request $request, int $document, TravelWorkflow $workflow)
    {
        $record = $workflow->document($request->user(), $document);
        $values = $workflow->formData($record);
        $totals = $record->kind === 'spd' ? app(TravelAmounts::class)->estimate($values['costs'])
            : app(TravelAmounts::class)->actual($values['expenses'], $values['advance_one'], $values['advance_two']);
        $approvalPlan = in_array($record->status, ['draft', 'returned'], true)
            ? app(TravelDocumentAdministration::class)->preview($record->company, (int) $record->department_id, $record->kind,
                $record->employee_snapshot, (int) ($record->data['requester_id'] ?? $record->created_by), $record->data['approval_override'] ?? null,
                TravelWorkflow::travelType($record->project_numbering_id))['plan'] : null;

        return response()->view('travel.print', ['record' => $record, 'kind' => $record->kind, 'values' => $values,
            'employee' => $record->employee_snapshot, 'totals' => $totals, 'editable' => false, 'printing' => true,
            'employees' => collect(), 'approvalPlan' => $approvalPlan, 'requesterSnapshot' => $record->data['requester_snapshot'] ?? null])->header('Cache-Control', 'private, no-store');
    }

    public function file(Request $request, TravelFile $file, TravelWorkflow $workflow)
    {
        $workflow->document($request->user(), $file->travel_document_id);
        abort_unless(preg_match('~^travel/\d{4}/\d{2}/[a-f0-9-]{36}\.(pdf|png|jpg)$~D', $file->path)
            && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name,
            ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
