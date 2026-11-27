<?php

namespace App\Http\Controllers;

use App\Services\SubmissionReports;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionReportDownloadController
{
    public function __invoke(Request $request, SubmissionReports $reports, string $type): StreamedResponse
    {
        abort_unless($reports->canExport($request->user()) && isset($reports->available($request->user())[$type]), 403);
        try {
            $filters = $reports->filters($request->query(), $type);
        } catch (ValidationException $exception) {
            abort(422, $exception->validator->errors()->first());
        }
        $query = $reports->query($request->user(), $type, $filters);

        return response()->streamDownload(function () use ($query, $reports, $type): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, SubmissionReports::COLUMNS, ',', '"', '');
            foreach ($query->lazy(250) as $record) {
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value)
                    ? "'".$value : $value, $reports->row($record, $type));
                $row[8] = $row[8] === null ? null : number_format($row[8] / 100, 2, '.', '');
                fputcsv($output, $row, ',', '"', '');
            }
            fclose($output);
        }, 'laporan-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
