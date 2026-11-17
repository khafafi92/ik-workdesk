@php
    $poSnapshot = $order->snapshot;
    $poEvaluation = $poSnapshot['evaluation'];
    $poCurrency = $poSnapshot['currency'];
    $poMoney = fn (int $value): string => $poCurrency === 'IDR'
        ? 'Rp '.number_format(intdiv($value, 100), 0, ',', '.').($value % 100 ? ','.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT) : '')
        : \App\Services\Scm\Decimal::money($value, $poCurrency);
    $poQuantity = fn (int $value): string => rtrim(rtrim(\App\Services\Scm\Decimal::input($value, 3), '0'), ',');
    $poCompany = $poSnapshot['company'] ?? '';
    $poLogo = config('scm.companies.'.$poCompany.'.logo');
    $poLetterhead = ($letterhead ?? false) && $poCompany === 'KPMOG';
    $poBuyer = $poSnapshot['buyer_signer'] ?? null;
    $poSecondBuyer = $poSnapshot['second_buyer_signer'] ?? null;
    if ((! $poBuyer || ! $poSecondBuyer) && \Illuminate\Support\Facades\Schema::hasTable('scm_po_approval_policies')) {
        $poPolicy = \App\Models\Scm\PoApprovalPolicy::with('buyer')->where('company', $poCompany)->first();
        $poBuyer ??= $poPolicy ? ['name' => $poPolicy->buyer->name, 'position' => $poPolicy->buyer_position] : null;
        if (! $poSecondBuyer && $poPolicy && \Illuminate\Support\Facades\Schema::hasColumn('scm_po_approval_policies', 'second_buyer_id') && $poPolicy->secondBuyer) {
            $poSecondBuyer = ['name' => $poPolicy->secondBuyer->name, 'position' => $poPolicy->second_buyer_position];
        }
    }
@endphp
<article class="scm-po-sheet{{ $poLetterhead ? ' scm-po-sheet--letterhead' : '' }}" aria-label="Purchase Order {{ $order->number }}">
    @if($poLetterhead)<img class="scm-po-letterhead" src="{{ asset('img/brands/kpmog-po-letterhead.png') }}" alt="" aria-hidden="true">@elseif($poLogo)<div class="scm-po-brand"><img src="{{ asset($poLogo) }}" alt="{{ $poSnapshot['company_name'] ?? $poCompany }}"></div>@endif
    <header class="scm-po-sheet-heading">
        <h2>PURCHASE ORDER</h2>
    </header>
    <div class="scm-po-sheet-details">
        <div>
            <h3>Supplier Name &amp; Address</h3>
            <p><strong>{{ $poSnapshot['vendor']['name'] }}</strong></p>
            <p class="scm-po-preline">{{ $order->supplier_address ?: '-' }}</p>
            @if(filled($poSnapshot['vendor']['phone'] ?? null))<p>Phone: {{ $poSnapshot['vendor']['phone'] }}</p>@endif
            <h3>Order Contact</h3>
            <p>{{ $order->order_contact ?: '-' }}</p>
            @if(filled($poSnapshot['vendor']['pic_position'] ?? null) && trim($order->order_contact ?? '') === trim($poSnapshot['vendor']['pic_name'] ?? ''))<p>{{ $poSnapshot['vendor']['pic_position'] }}</p>@endif
        </div>
        <dl>
            <div><dt>Purchase Order No.</dt><dd>{{ $order->number }}</dd></div>
            <div><dt>Date Issued</dt><dd>{{ $order->issued_at->format('F j, Y') }}</dd></div>
            <div><dt>Delivery Terms</dt><dd>{{ $order->delivery_terms }}</dd></div>
            <div><dt>Delivery Time</dt><dd>{{ $order->delivery_time }}</dd></div>
            <div><dt>Payment Term</dt><dd>{{ $order->payment_terms }}</dd></div>
            <div><dt>Quotation No.</dt><dd>{{ $poSnapshot['quotation_number'] }}</dd></div>
            <div><dt>Reff (PR/SR) No.</dt><dd>{{ $poSnapshot['request_number'] }}</dd></div>
        </dl>
    </div>
    <div class="scm-po-table-wrap" role="region" aria-label="Rincian barang PO" tabindex="0">
        <table class="scm-po-table">
            <thead><tr class="scm-po-condition"><td colspan="5">This order is subject to the condition and instruction listed bellow and other documents attached herewith</td></tr>
                <tr class="scm-po-condition"><td colspan="5">Order ini terikat dengan syarat dan instruksi yang tercantum di bawah ini serta dokumen lain yang dilampirkan</td></tr>
                <tr><th scope="col">No</th><th scope="col">Description</th><th scope="col">QTY</th><th scope="col">Unit Price</th><th scope="col">Total Amount</th></tr></thead>
            <tbody>
                @foreach ($poSnapshot['lines'] as $line)
                    <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $line['name'] }}</strong>@if(filled($line['specification']))<div class="scm-po-preline">{{ $line['specification'] }}</div>@endif</td><td>{{ $poQuantity($line['quantity_milli']) }} {{ $line['unit'] }}</td><td>@include('scm.partials.po-money', ['amount' => $line['price_minor']])</td><td>@include('scm.partials.po-money', ['amount' => $line['gross_minor']])</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="3"></td><th scope="row">TOTAL</th><td>@include('scm.partials.po-money', ['amount' => $poEvaluation['subtotal_minor']])</td></tr>
                <tr><td colspan="3"></td><th scope="row">DISCOUNT</th><td>@include('scm.partials.po-money', ['amount' => $poEvaluation['discount_minor']])</td></tr>
                @if ($poSnapshot['extra_minor'] > 0)<tr><td colspan="3"></td><th scope="row">OTHER COSTS</th><td>@include('scm.partials.po-money', ['amount' => $poSnapshot['extra_minor']])</td></tr>@endif
                <tr><td colspan="3"></td><th scope="row">SUB TOTAL</th><td>@include('scm.partials.po-money', ['amount' => $poEvaluation['net_minor']])</td></tr>
                <tr><td colspan="3"></td><th scope="row">VAT {{ ($poEvaluation['non_pkp'] ?? false) ? '0% (Non PKP)' : ($poEvaluation['vat_percent'] ?? 0).'%' }}</th><td>@include('scm.partials.po-money', ['amount' => $poEvaluation['tax_minor']])</td></tr>
                <tr class="scm-po-grand-total"><td colspan="3" class="scm-po-words">{{ \App\Services\Scm\AmountInWords::english((int) $poEvaluation['total_minor'], $poCurrency) }}</td><th scope="row">GRAND TOTAL</th><td>@include('scm.partials.po-money', ['amount' => $poEvaluation['total_minor']])</td></tr>
                <tr class="scm-po-terms"><td colspan="5">Terms &amp; Conditions applied as attachment this Purchase Order</td></tr>
            </tfoot>
        </table>
    </div>
    @if (filled($order->notes))<p class="scm-po-preline"><strong>Catatan:</strong> {{ $order->notes }}</p>@endif
    <div class="scm-po-signatures">
        <div>{{ $poSnapshot['vendor']['name'] }}<br>(SELLER)<span>Nama / tanda tangan</span></div>
        <div>{{ $poSnapshot['company_name'] ?? $poSnapshot['company'] }}<br>(BUYER)
            <div class="scm-po-buyer-signatures">@foreach([$poBuyer, $poSecondBuyer] as $signer)<div><div class="scm-po-signature-empty"></div><span>{{ $signer['name'] ?? 'Nama / tanda tangan' }}@if(filled($signer['position'] ?? null))<br>{{ $signer['position'] }}@endif</span></div>@endforeach</div>
            <div class="scm-po-signoff">
                @if($poCompany === 'KPMOG')<div class="scm-po-form-revision">KPM-FRM-058, 23 April 2024, Rev.2</div>@endif
                <table class="scm-po-initials" aria-label="Paraf persetujuan PO"><thead><tr><th scope="col">SCM</th><th scope="col">SPV</th><th scope="col">Manager</th><th scope="col">CBO</th></tr></thead><tbody><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table>
            </div>
        </div>
    </div>
    @if($poCompany === 'KPMOG' && ! $poLetterhead)<footer class="scm-po-footer">PT. KPM Oil &amp; Gas | PSW Tower, 9th Floor, Jl. Pangeran Antasari No. 75, Cilandak, Jakarta Selatan, 12430, Indonesia<br>Ph. +62 21 39708090 | Fax. +62 21 39700190 | www.kpmog.com</footer>@endif
</article>
