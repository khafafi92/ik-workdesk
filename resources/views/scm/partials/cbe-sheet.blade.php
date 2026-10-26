@php
    $sheetQuotes = collect($sheetQuotes)->values();
    $vendorColumns = max(3, $sheetQuotes->count());
    $sheetBrand = config('scm.companies.'.($sheetRequest['company'] ?? 'APCA')) ?? config('scm.companies.APCA');
    $budget = $sheetRequest['estimated_total_minor'] ?? null;
    $money = fn ($value, $currency) => ($value < 0 ? '-' : '').str_replace('IDR ', 'Rp ', \App\Services\Scm\Decimal::money(abs($value), $currency));
    $usesCbePricing = $sheetEditing || $sheetQuotes->contains(fn ($quote) => isset($quote['evaluation']));
    $sheetVatPercent = $sheetQuotes->first()['evaluation']['vat_percent'] ?? $sheetVatPercent ?? (int) config('scm.cbe_vat_percent');
@endphp
<p class="scm-cbe-scroll-help">Form mengikuti susunan Excel. Geser lembar ke samping untuk melihat seluruh kolom pada layar kecil.</p>
<div class="scm-cbe-canvas-scroll" role="region" aria-label="Lembar CBE sesuai master Excel" tabindex="0">
<section class="scm-cbe-sheet" aria-label="Commercial Bid Evaluation" style="--cbe-vendors:{{ $vendorColumns }}">
    <header class="scm-cbe-header">
        <h2>COMMERCIAL EVALUATION<br><span>SUMMARY OF BIDS</span></h2>
        <div class="scm-cbe-brand"><img src="{{ asset(($sheetRequest['company'] ?? 'APCA') === 'KPMOG' ? 'img/brands/kpmog-cbe.jpeg' : $sheetBrand['logo']) }}" alt="{{ $sheetBrand['logo_alt'] }}" width="180" height="52"><strong>{{ ($sheetRequest['company'] ?? 'APCA') === 'KPMOG' ? 'PT KPM OIL & GAS' : $sheetBrand['logo_alt'] }}</strong></div>
    </header>
    <div class="scm-cbe-identity">
        <dl class="scm-cbe-project"><div><dt>PROJECT</dt><dd>{{ $sheetRequest['location'] ?? '' }}</dd></div><div><dt>DEPARTMENT</dt><dd>{{ $sheetRequest['department_name'] ?? '' }}</dd></div></dl>
        <dl class="scm-cbe-document-meta"><div><dt>Number</dt><dd>{{ $sheetNumber ?? '' }}</dd></div><div><dt>@if($sheetEditing)<label for="scm-cbeForm-date">Date</label>@else Date @endif</dt><dd>@if($sheetEditing)<input id="scm-cbeForm-date" wire:model="cbeForm.date" type="date" required>@else{{ $sheetDate }}@endif</dd></div></dl>
    </div>
    <div class="scm-cbe-scroll" role="region" aria-label="Tabel perbandingan CBE" tabindex="0">
        <table class="scm-cbe-table">
            <caption class="scm-cbe-sr">Summary of bids: rincian harga, syarat penawaran, dan evaluasi anggaran</caption>
            <colgroup><col class="scm-cbe-col-no"><col class="scm-cbe-col-description"><col class="scm-cbe-col-qty"><col class="scm-cbe-col-unit">@for($v = 0; $v < $vendorColumns; $v++)<col class="scm-cbe-col-price"><col class="scm-cbe-col-price">@endfor<col class="scm-cbe-col-remarks"></colgroup>
            <thead>
                <tr><th rowspan="3" scope="col">NO</th><th rowspan="3" scope="col">DESCRIPTION</th><th rowspan="3" scope="col">Qty</th><th rowspan="3" scope="col">Unit of<br>Measure</th>@for($v = 0; $v < $vendorColumns; $v++)<th colspan="2" scope="colgroup">{{ $v + 1 }}</th>@endfor<th rowspan="3" scope="col">Remarks</th></tr>
                <tr>@for($v = 0; $v < $vendorColumns; $v++)
                    @php
                        $quote = $sheetQuotes->get($v);
                    @endphp
                    <th colspan="2" scope="colgroup">@if($quote){{ $quote['vendor']['name'] }}<small>{{ $quote['number'] }} · Versi {{ $quote['version'] }} · {{ $quote['currency'] }}</small>@else<span class="scm-cbe-sr">Belum dipilih</span>@endif</th>@endfor</tr>
                <tr>@for($v = 0; $v < $vendorColumns; $v++)<th scope="col">Unit Price</th><th scope="col">Total</th>@endfor</tr>
            </thead>
            <tbody>
                @foreach($sheetItems as $index => $item)
                <tr class="scm-cbe-item-row"><th scope="row" class="scm-cbe-center">{{ $index + 1 }}</th><td><strong>{{ $item['name'] }}</strong><div>{{ $item['specification'] }}</div></td><td class="scm-cbe-center">{{ \App\Services\Scm\Decimal::input($item['quantity_milli'], 3) }}</td><td class="scm-cbe-center">{{ $item['unit'] }}</td>
                    @for($v = 0; $v < $vendorColumns; $v++)
                        @php
                            $quote = $sheetQuotes->get($v);
                            $line = $quote ? collect($quote['lines'])->firstWhere('request_item_id', $item['id']) : null;
                        @endphp
                        <td class="scm-cbe-money">@if($line){{ $money($line['price_minor'], $quote['currency']) }}@endif</td>
                        <td class="scm-cbe-money">@if($line){{ $money($usesCbePricing ? $line['total_minor'] - $line['tax_minor'] : $line['total_minor'], $quote['currency']) }}
                            @if($line['quantity_milli'] !== $item['quantity_milli'] || mb_strtolower(trim($line['unit'])) !== mb_strtolower(trim($item['unit'])))<strong class="scm-cbe-warning">Belum sebanding: jumlah/satuan berbeda ({{ \App\Services\Scm\Decimal::input($line['quantity_milli'], 3) }} {{ $line['unit'] }}).</strong>@endif
                            @elseif($quote)<strong class="scm-cbe-warning">Belum sebanding: item belum ditawarkan.</strong>@endif</td>
                    @endfor
                    <td>{{ $item['notes'] ?? '' }}@foreach($sheetQuotes as $remarkQuote)
                        @php
                            $remarkLine = collect($remarkQuote['lines'])->firstWhere('request_item_id', $item['id']);
                        @endphp
                        @if($remarkLine && trim($remarkLine['description']) !== trim($item['specification']))<small>{{ $remarkQuote['vendor']['name'] }}: {{ $remarkLine['description'] }}</small>@endif
                        @if($index === 0 && filled($remarkQuote['notes'] ?? ''))<small>{{ $remarkQuote['vendor']['name'] }}: {{ $remarkQuote['notes'] }}</small>@endif
                    @endforeach</td>
                </tr>
                @endforeach
                @for($blank = count($sheetItems); $blank < 16; $blank++)<tr class="scm-cbe-blank" aria-hidden="true">@for($cell = 0; $cell < 5 + 2 * $vendorColumns; $cell++)<td></td>@endfor</tr>@endfor
                @foreach(['subtotal' => 'Sub Total', 'discount' => 'Discount', 'others' => 'Others- Loading Unloading', 'net' => 'Total', 'tax' => $usesCbePricing ? 'PPN '.$sheetVatPercent.'%' : 'PPN', 'freight' => 'Transportation (Freight) Cost', 'grand' => 'Grand Total', 'rate' => 'USD rate', 'idr' => 'Grand Total', 'budget' => 'Total Budget', 'difference' => 'Differences', 'incoterms' => 'Incoterms', 'payment' => 'Terms of Payment', 'delivery' => 'Promised Delivery Date', 'required' => 'Required Delivery Date', 'expiry' => "Expiration Date of Bidder's Quotation"] as $row => $label)
                <tr data-cbe-row="{{ $row }}" @class(['scm-cbe-summary-row', 'scm-cbe-grand' => in_array($row, ['grand', 'idr']), 'scm-cbe-idr' => $row === 'idr', 'scm-cbe-difference' => $row === 'difference'])><td></td><th scope="row" @if($row === 'idr') aria-label="Grand Total (IDR)" @endif>{{ $label }}</th><td></td><td></td>
                    @for($v = 0; $v < $vendorColumns; $v++)
                    @php
                        $quote = $sheetQuotes->get($v);
                        $lines = collect($quote['lines'] ?? []);
                        $discount = $lines->sum('discount_minor');
                        $tax = $lines->sum('tax_minor');
                        $subtotal = $lines->sum('total_minor') + $discount - $tax;
                        $evaluation = $quote['evaluation'] ?? null;
                        $pricingError = $quote['evaluation_error'] ?? null;
                        $nonPkp = $evaluation['non_pkp'] ?? (bool) ($cbeForm['commercial_terms'][$quote['id'] ?? 0]['non_pkp'] ?? false);
                        $discount = $evaluation['discount_minor'] ?? $discount;
                        $tax = $evaluation['tax_minor'] ?? $tax;
                        $total = $evaluation['total_minor'] ?? $quote['total_minor'] ?? 0;
                    @endphp
                    <td class="scm-cbe-summary-control" wire:key="cbe-control-{{ $row }}-{{ $quote['id'] ?? 'empty-'.$v }}">
                        @if($quote && $sheetEditing && $row === 'discount')<span class="scm-cbe-control-label">Tambahan</span><x-scm.field model="cbeForm.commercial_terms.{{ $quote['id'] }}.discount" label="Diskon tambahan {{ $quote['vendor']['name'] }} (nominal)" inputmode="decimal" :live="true" />
                        @elseif($quote && $row === 'tax')
                            @if($sheetEditing)<label class="scm-check scm-cbe-non-pkp"><input type="checkbox" wire:model.live="cbeForm.commercial_terms.{{ $quote['id'] }}.non_pkp"><span>Non PKP<span class="scm-cbe-sr"> {{ $quote['vendor']['name'] }}</span></span></label>
                            @elseif($evaluation){{ $nonPkp ? 'Non PKP' : 'PKP' }}@endif
                        @endif
                    </td>
                    <td wire:key="cbe-summary-{{ $row }}-{{ $quote['id'] ?? 'empty-'.$v }}" @class(['scm-cbe-money' => in_array($row, ['subtotal', 'discount', 'others', 'net', 'tax', 'grand', 'idr', 'budget', 'difference'])])>
                        @if($quote)
                            @switch($row)
                                @case('subtotal') {{ $money($subtotal, $quote['currency']) }} @break
                                @case('discount')
                                    @if(!$pricingError){{ $money($discount, $quote['currency']) }}@endif
                                    @if($pricingError)<strong class="scm-cbe-warning" role="alert">{{ $pricingError }}</strong>@endif
                                    @break
                                @case('others') {{ $money($quote['extra_minor'], $quote['currency']) }} @break
                                @case('net') {{ $pricingError ? 'Periksa diskon' : $money($evaluation['net_minor'] ?? $subtotal - $discount + $quote['extra_minor'], $quote['currency']) }} @break
                                @case('tax')
                                    @if($sheetEditing)<div class="scm-field"><label class="scm-cbe-sr" for="cbe-vat-{{ $quote['id'] }}">PPN {{ $sheetVatPercent }}% {{ $quote['vendor']['name'] }}</label><input id="cbe-vat-{{ $quote['id'] }}" class="scm-cbe-vat" value="{{ $pricingError ? 'Periksa diskon' : $money($tax, $quote['currency']) }}" readonly @disabled($nonPkp)></div>
                                    @else{{ $money($tax, $quote['currency']) }}@endif
                                    @if($nonPkp)<span class="scm-cbe-sr">Non PKP: PPN tidak aktif</span>@endif @break
                                @case('freight') <span class="scm-cbe-sr">Belum dirinci terpisah</span> @break
                                @case('grand') <strong>{{ $pricingError ? 'Periksa diskon' : $money($total, $quote['currency']) }}</strong> @break
                                @case('rate') <span class="scm-cbe-sr">{{ $quote['currency'] === 'USD' ? 'Belum dicatat' : 'Tidak digunakan' }}</span> @break
                                @case('idr') {{ $pricingError ? 'Periksa diskon' : ($quote['currency'] === 'IDR' ? $money($total, 'IDR') : 'Belum dikonversi') }} @break
                                @case('budget') {{ $budget !== null ? $money($budget, 'IDR') : '' }} @break
                                @case('difference') {{ $pricingError ? 'Periksa diskon' : ($budget !== null && $quote['currency'] === 'IDR' ? $money($total - $budget, 'IDR') : 'Belum dapat dibandingkan') }} @break
                                @case('incoterms') <span class="scm-cbe-sr">Belum dicatat</span> @break
                                @case('payment') {{ $quote['payment_terms'] }} @break
                                @case('delivery') {{ $quote['lead_time'] }} @break
                                @case('required') {{ filled($sheetRequest['needed_at'] ?? null) ? \Illuminate\Support\Carbon::parse($sheetRequest['needed_at'])->format('d/m/Y') : 'Belum dicatat' }} @break
                                @case('expiry') {{ filled($quote['valid_until'] ?? null) ? \Illuminate\Support\Carbon::parse($quote['valid_until'])->format('d/m/Y') : 'Belum dicatat' }} @break
                            @endswitch
                        @endif
                    </td>
                    @endfor
                    <td></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="scm-cbe-decision">
        <dl><div><dt>COMMENT</dt><dd>{{ $sheetRequest['notes'] ?? '' }}</dd></div></dl>
        @if($sheetEditing)
            <x-scm.select model="cbeForm.selected_vendor_id" label="RECOMMENDED VENDOR" :options="$sheetQuotes->mapWithKeys(fn ($q) => [$q['vendor']['id'] => $q['vendor']['name']])" />
            <x-scm.field model="cbeForm.selection_reason" label="REASON" multiline />
        @else
            <dl><div><dt>RECOMMENDED VENDOR</dt><dd>{{ $sheetQuotes->firstWhere('vendor.id', $sheetSelectedVendor)['vendor']['name'] ?? 'Belum dipilih' }}</dd></div><div><dt>REASON</dt><dd>{{ $sheetReason }}</dd></div></dl>
        @endif
    </div>
    <div class="scm-cbe-signatures" role="group" aria-label="Kolom tanda tangan CBE">
        @foreach(['Prepared By:', 'Acknowledged By:', 'Crosschecked By:', 'Approved By:'] as $role)
        <div class="scm-cbe-signature"><strong>{{ $role }}</strong><div class="scm-cbe-signature-space"><span class="scm-cbe-sr">Belum ditandatangani</span></div><div>Name: {{ $loop->first ? ($sheetPreparedName ?? '') : '' }}</div><div>Date:</div></div>
        @endforeach
    </div>
    @if(($sheetRequest['company'] ?? 'APCA') === 'KPMOG')<p class="scm-cbe-revision">KPM_OG-FRM-063, 23 April 2024, Rev.3</p>@endif
</section>
</div>
<p class="scm-cbe-source">{{ $sheetRequest['kind'] ?? 'PR/SR' }}: {{ $sheetRequest['number'] }} · {{ $sheetRequest['title'] }}</p>
<p class="scm-cbe-note">{{ $usesCbePricing ? 'Discount menampilkan diskon quotation ditambah diskon tambahan CBE (opsional, kosong berarti nol). PPN master '.$sheetVatPercent.'% dihitung setelah diskon dan biaya tambahan. Non PKP menonaktifkan PPN; pajak nominal quotation tidak ditambahkan lagi.' : 'Total baris sudah termasuk diskon dan pajak nominal quotation.' }} Others mengikuti biaya tambahan quotation; freight belum dirinci terpisah. Kurs dan Incoterms yang kosong belum dicatat. Total Budget memakai estimasi PR/SR (IDR); Differences adalah total CBE dikurangi estimasi. Kolom tanda tangan CBE belum ditandatangani; persetujuan PR/SR dicatat pada form permintaan.</p>
