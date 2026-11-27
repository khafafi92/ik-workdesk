<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record->kind }} {{ $record->number }}</title>
    @vite('resources/css/scm.css')
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { margin: 0; background: #fff; color: #111827; font-family: Arial, sans-serif; }
        main { max-width: 70rem; margin: 1rem auto; padding-inline: 1rem; }
        .scm-print-controls { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .75rem; }
        .scm-print-controls button { min-height: 44px; padding: .45rem .75rem; color: #004ca7; background: #fff; border: 1px solid #6b7280; border-radius: .25rem; font: inherit; }
        @media print {
            main { max-width: none; margin: 0; padding: 0; }
            .scm-print-controls { display: none; }
            .scm-request-sheet { padding: 0; border: 0; }
            .scm-sheet-table-wrap { overflow: visible; }
            .scm-sheet-table { min-width: 0; }
            .scm-sheet-table thead { display: table-header-group; }
            .scm-sheet-table tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
<main>
    <div class="scm-print-controls">
        <span>{{ $record->number }} · {{ $record->status === 'submitted' ? 'Menunggu penerimaan' : \App\Services\Scm\Workflow::STATUSES[$record->status] }}</span>
        @include('documents.print-button', ['files' => $record->attachments])
    </div>
    @include('filament.pages.scm.partials.request-sheet', ['sheetEditing' => false, 'canSignPrepared' => false, 'waitingForRequestedSignature' => false])
    @include('documents.print-attachments', ['files' => $record->attachments, 'downloadRoute' => 'scm.download', 'documentNumber' => $record->number])
</main>
</body>
</html>
