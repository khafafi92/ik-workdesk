<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PO {{ $order->number }}</title>
    @vite('resources/css/scm.css')
</head>
<body class="scm-po-print{{ ($order->snapshot['company'] ?? '') === 'KPMOG' ? ' scm-po-print--letterhead' : '' }}">
    <div class="scm-po-print-actions"><button type="button" onclick="window.print()">Cetak / simpan PDF</button><span>PO {{ $order->number }}</span><p>Pilih kertas A4 dan hilangkan centang Headers and footers pada pengaturan cetak browser.</p></div>
    @include('scm.partials.purchase-order-sheet', ['order' => $order, 'letterhead' => true])
</body>
</html>
