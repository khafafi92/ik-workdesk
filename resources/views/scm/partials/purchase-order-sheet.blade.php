@php
    $poSnapshot = $order->snapshot;
    $poEvaluation = $poSnapshot['evaluation'];
    $poCurrency = $poSnapshot['currency'];
    $poMoney = fn (int $value): string => \App\Services\Scm\Decimal::money($value, $poCurrency);
    $poQuantity = fn (int $value): string => rtrim(rtrim(\App\Services\Scm\Decimal::input($value, 3), '0'), ',');
@endphp
<article class="scm-po-sheet" aria-label="Draft Purchase Order {{ $order->number }}">
    <header class="scm-po-sheet-heading">
        <h2>PURCHASE ORDER</h2>
        <p>Draft · Belum ditandatangani</p>
    </header>
    <div class="scm-po-sheet-details">
        <div>
            <h3>Supplier Name &amp; Address</h3>
            <p><strong>{{ $poSnapshot['vendor']['name'] }}</strong></p>
            <p class="scm-po-preline">{{ $order->supplier_address ?: '-' }}</p>
            <h3>Order Contact</h3>
            <p>{{ $order->order_contact ?: '-' }}</p>
        </div>
        <dl>
            <div><dt>Purchase Order No.</dt><dd>{{ $order->number }}</dd></div>
            <div><dt>Date Issued</dt><dd>{{ $order->issued_at->format('d/m/Y') }}</dd></div>
            <div><dt>Delivery Terms</dt><dd>{{ $order->delivery_terms }}</dd></div>
            <div><dt>Delivery Time</dt><dd>{{ $order->delivery_time }}</dd></div>
            <div><dt>Payment Term</dt><dd>{{ $order->payment_terms }}</dd></div>
            <div><dt>Quotation No.</dt><dd>{{ $poSnapshot['quotation_number'] }}</dd></div>
            <div><dt>Reff (PR/SR) No.</dt><dd>{{ $poSnapshot['request_number'] }}</dd></div>
        </dl>
    </div>
    <p>Rincian pesanan mengacu pada CBE dan quotation yang dipilih.</p>
    <div class="scm-po-table-wrap" role="region" aria-label="Rincian barang PO" tabindex="0">
        <table class="scm-po-table">
            <thead><tr><th scope="col">No</th><th scope="col">Description</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Unit Price</th><th scope="col">Total Amount</th></tr></thead>
            <tbody>
                @foreach ($poSnapshot['lines'] as $line)
                    <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $line['name'] }}</strong>@if(filled($line['specification']))<div class="scm-po-preline">{{ $line['specification'] }}</div>@endif</td><td>{{ $poQuantity($line['quantity_milli']) }}</td><td>{{ $line['unit'] }}</td><td>{{ $poMoney($line['price_minor']) }}</td><td>{{ $poMoney($line['gross_minor']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <dl class="scm-po-totals">
        <div><dt>Total</dt><dd>{{ $poMoney((int) $poEvaluation['subtotal_minor']) }}</dd></div>
        <div><dt>Discount</dt><dd>{{ $poMoney((int) $poEvaluation['discount_minor']) }}</dd></div>
        @if ($poSnapshot['extra_minor'] > 0)<div><dt>Biaya tambahan</dt><dd>{{ $poMoney($poSnapshot['extra_minor']) }}</dd></div>@endif
        <div><dt>Sub total</dt><dd>{{ $poMoney((int) $poEvaluation['net_minor']) }}</dd></div>
        <div><dt>VAT {{ ($poEvaluation['non_pkp'] ?? false) ? '0% (Non PKP)' : ($poEvaluation['vat_percent'] ?? 0).'%' }}</dt><dd>{{ $poMoney((int) $poEvaluation['tax_minor']) }}</dd></div>
        <div class="scm-po-grand-total"><dt>Grand total</dt><dd>{{ $poMoney((int) $poEvaluation['total_minor']) }}</dd></div>
    </dl>
    @if (filled($order->notes))<p class="scm-po-preline"><strong>Catatan:</strong> {{ $order->notes }}</p>@endif
    <div class="scm-po-signatures">
        <div>SELLER<span>Nama / tanda tangan</span></div>
        <div>{{ $poSnapshot['company_name'] ?? $poSnapshot['company'] }} (BUYER)
            <div class="scm-po-signature-roles"><span>SCM</span><span>SPV</span><span>Manager</span><span>CBO</span></div>
        </div>
    </div>
</article>
