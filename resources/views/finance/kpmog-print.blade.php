<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record->reference() }} · {{ \App\Models\FinanceRequest::KINDS[$record->kind] }}</title>
    <link rel="stylesheet" href="{{ asset('css/travel.css') }}">
    <link rel="stylesheet" href="{{ asset('css/finance-requests.css') }}">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { margin: 0; background: #fff; color: #111827; font: 1rem/1.4 Arial, sans-serif; }
        main { max-width: 52rem; margin: 1rem auto; padding: 0 1rem; }
        .finance-print-toolbar { display: flex; gap: .75rem; align-items: center; justify-content: end; margin-bottom: .75rem; }
        .finance-print-toolbar button { min-height: 44px; color: #004ca7; background: #fff; border: 1px solid #6b7280; border-radius: .25rem; padding: .4rem .65rem; cursor: pointer; }
        @media print {
            main { margin: 0; padding: 0; max-width: none; }
            .finance-print-toolbar { display: none; }
            .finance-travel { container-type: normal; }
            .finance-travel .finance-travel-sheet { font-size: 9pt; print-color-adjust: exact; }
            tr, .finance-kpmog-block { break-inside: avoid; }
        }
    </style>
</head>
<body>
<main class="finance-travel">
    <div class="finance-print-toolbar"><span>{{ $record->reference() }} · {{ \App\Models\FinanceRequest::STATUSES[$record->status] }}</span>@include('documents.print-button', ['files' => $record->files])</div>
    @include('finance.partials.kpmog-form', [
        'kind' => $record->kind, 'editable' => false, 'advance' => $record->data['advance_minor'] ?? 0, 'statuses' => \App\Models\FinanceRequest::STATUSES,
    ])
    @include('documents.print-attachments', ['files' => $record->files, 'downloadRoute' => 'finance.requests.file', 'documentNumber' => $record->reference()])
</main>
</body>
</html>
