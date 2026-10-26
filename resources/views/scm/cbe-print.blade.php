<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $cbe->number }} · Commercial Bid Evaluation</title>
    @vite('resources/css/scm-cbe.css')
</head>
<body class="scm-cbe-print">
    <div class="scm-cbe-print-actions"><button type="button" onclick="window.print()">Cetak / simpan PDF</button><span>{{ $cbe->number }} · Versi {{ $cbe->version }} · Difinalkan {{ $cbe->finalized_at->format('d/m/Y H:i:s') }}</span></div>
    <p class="scm-cbe-sr">Perusahaan: {{ $cbe->snapshot['request']['company'] ?? 'APCA' }}</p>
    @include('scm.partials.cbe-sheet', [
        'sheetEditing' => false,
        'sheetRequest' => $cbe->snapshot['request'],
        'sheetItems' => $cbe->snapshot['items'],
        'sheetQuotes' => $cbe->snapshot['quotes'],
        'sheetNumber' => $cbe->number,
        'sheetDate' => $cbe->date->format('d/m/Y'),
        'sheetSelectedVendor' => $cbe->selected_vendor_id,
        'sheetReason' => $cbe->selection_reason,
        'sheetPreparedName' => $cbe->snapshot['prepared_by_name'] ?? null,
    ])
    <p class="scm-cbe-note">Harga akhir: {{ \App\Services\Scm\Decimal::money($cbe->final_minor, $cbe->snapshot['quotes'][0]['currency']) }}. Data menggunakan snapshot final.</p>
</body>
</html>
