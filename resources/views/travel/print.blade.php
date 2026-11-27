<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ strtoupper($kind) }} {{ $record->displayNumber() }}</title>
    <link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
</head>
<body class="travel-print">
    <div class="travel-print-actions">
        @include('documents.print-button', ['files' => $record->files])
        <p>{{ $record->displayNumber() }} · {{ $record->statusLabel(true) }}. Gunakan kertas A4 dan matikan header/footer browser.</p>
        @if($record->status !== 'approved')<strong>Dokumen belum disetujui seluruh pemeriksa.</strong>@endif
    </div>
    <main>
        @if($record->status !== 'approved')
            <div class="travel-print-status">{{ strtoupper($record->statusLabel(true)) }}: belum disetujui seluruh pemeriksa.</div>
        @endif
        @include('travel.partials.'.$kind)
        @include('documents.print-attachments', ['files' => $record->files, 'downloadRoute' => 'travel.file', 'documentNumber' => $record->displayNumber()])
    </main>
</body>
</html>
