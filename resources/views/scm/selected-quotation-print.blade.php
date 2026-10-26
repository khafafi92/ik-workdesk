<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Penawaran terpilih · {{ $cbe->snapshot['request']['number'] }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111827;background:#fff;margin:2rem;line-height:1.5}
        h1{font-size:1.5rem}p,th,td{overflow-wrap:anywhere}
        table{width:100%;border-collapse:collapse;margin:1rem 0;min-width:40rem}
        th,td{border:1px solid #6b7280;padding:.6rem;text-align:left;vertical-align:top}
        button{padding:.75rem;min-height:44px}button:focus-visible,.scroll:focus-visible{outline:3px solid #111827;outline-offset:3px}
        .scroll{overflow:auto}
        @media(max-width:600px){body{margin:1rem}}
        @media print{button{display:none}body{margin:0;font-size:10pt}table{min-width:0}@page{size:A4 landscape;margin:12mm}thead{display:table-header-group}tr{break-inside:avoid}}
    </style>
</head>
<body>
    <button type="button" onclick="window.print()">Cetak / simpan PDF</button>
    <h1>Penawaran harga vendor terpilih</h1>
    <p>{{ $cbe->snapshot['request']['number'] }} · {{ $cbe->snapshot['request']['title'] }}</p>
    <p>Perusahaan: {{ $cbe->snapshot['request']['company'] ?? 'APCA' }}</p>
    <p>Vendor: <strong>{{ $quote['vendor']['name'] }}</strong></p>
    <p>{{ $quote['number'] }} · Versi {{ $quote['version'] }} · Tanggal {{ $quote['date'] }}</p>
    <p>Data penawaran yang tersimpan pada CBE final {{ $cbe->number }}.</p>
    <div class="scroll" role="region" aria-label="Rincian harga vendor terpilih" tabindex="0">
        <table>
            <thead><tr><th>Item</th><th>Spesifikasi ditawarkan</th><th>Jumlah</th><th>Harga satuan</th><th>Diskon</th><th>Pajak</th><th>Total</th></tr></thead>
            <tbody>
                @foreach($quote['lines'] as $line)
                <tr>
                    <th>{{ collect($cbe->snapshot['items'])->firstWhere('id', $line['request_item_id'])['name'] ?? '' }}</th>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ \App\Services\Scm\Decimal::input($line['quantity_milli'], 3) }} {{ $line['unit'] }}</td>
                    <td>{{ \App\Services\Scm\Decimal::money($line['price_minor'], $quote['currency']) }}</td>
                    <td>{{ \App\Services\Scm\Decimal::money($line['discount_minor'], $quote['currency']) }}</td>
                    <td>{{ \App\Services\Scm\Decimal::money($line['tax_minor'], $quote['currency']) }}</td>
                    <td>{{ \App\Services\Scm\Decimal::money($line['total_minor'], $quote['currency']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p>Biaya tambahan: {{ \App\Services\Scm\Decimal::money($quote['extra_minor'], $quote['currency']) }}</p>
    <p><strong>Total penawaran: {{ \App\Services\Scm\Decimal::money($quote['total_minor'], $quote['currency']) }}</strong></p>
    @if(isset($quote['evaluation']))
    <h2>Evaluasi CBE</h2>
    <p>Status pajak: {{ $quote['evaluation']['non_pkp'] ? 'Non PKP (PPN tidak aktif)' : 'PKP' }}. PPN master: {{ $quote['evaluation']['vat_percent'] }}%.</p>
    <p>Diskon tambahan CBE: {{ \App\Services\Scm\Decimal::money($quote['evaluation']['additional_discount_minor'], $quote['currency']) }}. PPN CBE: {{ \App\Services\Scm\Decimal::money($quote['evaluation']['tax_minor'], $quote['currency']) }}.</p>
    <p><strong>Total setelah evaluasi CBE: {{ \App\Services\Scm\Decimal::money($quote['evaluation']['total_minor'], $quote['currency']) }}</strong></p>
    @endif
    <p>Waktu penyediaan: {{ $quote['lead_time'] }}</p>
    <p>Syarat pembayaran: {{ $quote['payment_terms'] }}</p>
    @if($quote['valid_until'])<p>Berlaku sampai: {{ $quote['valid_until'] }}</p>@endif
    @if($quote['notes'])<p>Catatan penawaran: {{ $quote['notes'] }}</p>@endif
</body>
</html>
