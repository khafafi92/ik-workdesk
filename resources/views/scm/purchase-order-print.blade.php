<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draft PO {{ $order->number }}</title>
    @vite('resources/css/scm.css')
</head>
<body class="scm-po-print">
    <div class="scm-po-print-actions"><button type="button" onclick="window.print()">Cetak / simpan PDF</button><span>Draft PO {{ $order->number }}</span></div>
    @include('scm.partials.purchase-order-sheet', ['order' => $order])
</body>
</html>
